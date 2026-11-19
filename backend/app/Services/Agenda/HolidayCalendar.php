<?php

namespace App\Services\Agenda;

use App\Support\PeruvianHolidays;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Los feriados nacionales del Perú de un año: los que el sistema ya conoce y, además, los que trae Nager.Date
 * (date.nager.at), una API pública y gratis, sin cuenta ni clave. Así, un feriado nuevo por ley llega solo, sin
 * tocar el código. Solo se pide el año: no sale ningún dato del consultorio ni de los pacientes.
 *
 * Si Nager.Date no responde, queda la lista del sistema y se vuelve a intentar en unos minutos.
 */
final class HolidayCalendar
{
    private const NAGER_DATE_URL = 'https://date.nager.at/api/v3/PublicHolidays/%d/PE';

    /**
     * @return array<string, string> Fecha ("2027-04-01") => nombre, en orden de fecha.
     */
    public function of(int $year): array
    {
        return $this->load($year)['holidays'];
    }

    /** ¿La lista de ese año ya incluye lo de Nager.Date? False si no respondió y es solo la del sistema. */
    public function includesNagerDate(int $year): bool
    {
        return $this->load($year)['from_nager_date'];
    }

    /**
     * Se guarda un día (o unos minutos si Nager.Date no respondió) para no pedirla en cada pantalla.
     *
     * @return array{holidays: array<string, string>, from_nager_date: bool}
     */
    private function load(int $year): array
    {
        // Sin año (un formulario que todavía no lo tiene) no hay feriados ni consulta.
        if ($year < 1) {
            return ['holidays' => [], 'from_nager_date' => false];
        }

        $key = "feriados.{$year}";
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $fromNagerDate = $this->fromNagerDate($year);

        // Con la misma fecha queda el nombre del sistema; de Nager.Date se suman las fechas que el sistema no tenía.
        $holidays = collect(PeruvianHolidays::of($year))->union($fromNagerDate ?? [])->sortKeys()->all();
        $loaded = ['holidays' => $holidays, 'from_nager_date' => $fromNagerDate !== null];

        Cache::put($key, $loaded, $fromNagerDate !== null ? now()->addDay() : now()->addMinutes(10));

        return $loaded;
    }

    /**
     * Los feriados nacionales de ese año según Nager.Date, o null si no respondió o no trajo ninguno.
     *
     * @return array<string, string>|null
     */
    private function fromNagerDate(int $year): ?array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(5)
                ->get(sprintf(self::NAGER_DATE_URL, $year));
        } catch (ConnectionException $e) {
            // Queda la lista del sistema, pero el motivo va al registro de errores (por ejemplo, un PHP sin los
            // certificados para HTTPS).
            report($e);

            return null;
        }

        if ($response->failed() || ! is_array($response->json())) {
            return null;
        }

        $holidays = collect($response->json())
            ->filter(fn (mixed $holiday) => is_array($holiday)
                && is_string($holiday['date'] ?? null)
                && preg_match('/^'.$year.'-\d{2}-\d{2}$/', $holiday['date'])
                && filled($holiday['localName'] ?? null)
                // Solo los de todo el país: no los de una región.
                && ($holiday['global'] ?? true) === true)
            ->mapWithKeys(fn (array $holiday) => [$holiday['date'] => (string) $holiday['localName']])
            ->all();

        return $holidays === [] ? null : $holidays;
    }
}
