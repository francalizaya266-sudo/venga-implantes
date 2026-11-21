<?php

namespace App\Filament\Support;

use App\Enums\AppointmentStatus;
use App\Filament\Pages\Agenda;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\DefaultGlobalSearchProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Ctrl + K busca y también hace: arriba, lo que se puede hacer con lo escrito ("nueva cita", "llegó sin cita", ir
 * a la agenda de una fecha como "mañana", "viernes" o "25/11"); después, lo de siempre (pacientes, doctores,
 * insumos, proveedores); y al final las próximas citas de los pacientes que coinciden. Sin IA: palabras y fechas.
 */
final class PanelSearch extends DefaultGlobalSearchProvider
{
    /** Cuántas citas se muestran como mucho. */
    private const APPOINTMENTS = 5;

    private const MONTHS = ['enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12];

    private const WEEKDAYS = ['lunes' => 1, 'martes' => 2, 'miercoles' => 3, 'jueves' => 4, 'viernes' => 5, 'sabado' => 6, 'domingo' => 7];

    public function getResults(string $query): GlobalSearchResults
    {
        $results = GlobalSearchResults::make();
        $actions = $this->actions($query);

        if ($actions !== []) {
            $results->category('Acciones', $actions);
        }

        $appointments = $this->appointments($query);

        // Las citas del paciente van junto a él, antes de los doctores, insumos y proveedores.
        foreach (parent::getResults($query)?->getCategories() ?? [] as $name => $found) {
            $results->category($name, $found);

            if ($name === PatientResource::getPluralModelLabel() && $appointments !== []) {
                $results->category('Próximas citas', $appointments);
                $appointments = [];
            }
        }

        if ($appointments !== []) {
            $results->category('Próximas citas', $appointments);
        }

        return $results;
    }

    /**
     * Lo que se puede hacer con lo escrito: cada palabra tiene que ser el comienzo de una palabra de la acción.
     *
     * @return list<GlobalSearchResult>
     */
    private function actions(string $query): array
    {
        $words = self::words($query);
        $actions = [];

        if ($words === [] || ! Agenda::canAccess()) {
            return [];
        }

        if ($date = self::dateIn($query)) {
            $actions[] = new GlobalSearchResult(
                title: 'Ver la agenda del '.$date->translatedFormat('l j \d\e F'),
                url: Agenda::getUrl(['date' => $date->toDateString(), 'mode' => 'day']),
                details: ['Ir a' => $date->isToday() ? 'Hoy' : ($date->isTomorrow() ? 'Mañana' : ucfirst($date->translatedFormat('l')))],
            );
        }

        if (AppointmentResource::canCreate()) {
            if (self::matches($words, 'nueva cita agendar reservar separar dar hora')) {
                $actions[] = new GlobalSearchResult(title: 'Nueva cita', url: AppointmentResource::newUrl(), details: ['Abre' => 'La ventana para agendar, sobre la agenda']);
            }

            if (self::matches($words, 'llego sin cita paciente sin cita atender ahora walk in')) {
                $actions[] = new GlobalSearchResult(title: 'Llegó sin cita', url: Agenda::getUrl(['action' => 'walkIn']), details: ['Abre' => 'El primer hueco de hoy, y queda en sala']);
            }
        }

        return $actions;
    }

    /**
     * Las próximas citas (de hoy en adelante, sin cerrar) de los pacientes con ese nombre, documento o celular. Abren
     * el panel de la cita sobre su día en la agenda.
     *
     * @return list<GlobalSearchResult>
     */
    private function appointments(string $query): array
    {
        if (mb_strlen(trim($query)) < 3 || ! AppointmentResource::canViewAny() || ! Agenda::canAccess()) {
            return [];
        }

        /** @var Builder<Appointment> $appointments */
        $appointments = AppointmentResource::getEloquentQuery();

        return $appointments
            ->with(['patient', 'treatment', 'doctor'])
            ->whereIn('patient_id', Patient::query()->search($query)->select('id'))
            ->whereIn('status', AppointmentStatus::open())
            ->where('starts_at', '>=', today())
            ->orderBy('starts_at')
            ->limit(self::APPOINTMENTS)
            ->get()
            ->map(function (Appointment $appointment): GlobalSearchResult {
                /** @var CarbonInterface $startsAt */
                $startsAt = $appointment->starts_at;
                /** @var AppointmentStatus $status */
                $status = $appointment->status;
                /** @var Patient $patient */
                $patient = $appointment->patient;
                /** @var Treatment $treatment */
                $treatment = $appointment->treatment;
                /** @var Doctor $doctor */
                $doctor = $appointment->doctor;

                return new GlobalSearchResult(
                    title: $patient->full_name.' · '.self::when($startsAt),
                    url: Agenda::getUrl([
                        'date' => $startsAt->toDateString(),
                        'mode' => 'day',
                        'action' => 'appointment',
                        'actionArguments' => ['id' => $appointment->getKey()],
                    ]),
                    details: [
                        'Tratamiento' => $treatment->name,
                        'Doctor' => $doctor->name,
                        'Estado' => $status->getLabel(),
                    ],
                );
            })
            ->values()
            ->all();
    }

    /** "Hoy 10:30", "Mañana 9:00" o "Lunes 23 de noviembre, 11:00". */
    private static function when(CarbonInterface $startsAt): string
    {
        return match (true) {
            $startsAt->isToday() => 'Hoy '.$startsAt->format('H:i'),
            $startsAt->isTomorrow() => 'Mañana '.$startsAt->format('H:i'),
            default => ucfirst($startsAt->translatedFormat('l j \d\e F')).', '.$startsAt->format('H:i'),
        };
    }

    /**
     * Una fecha escrita como se dice: "hoy", "mañana", "pasado mañana", "ayer", "viernes" (el próximo, o hoy si es
     * viernes), "25", "25/11", "25/11/2026", "2026-11-25", "25 de noviembre" o "25 nov". Puede ir con "ir a", "el",
     * "agenda del". Un día y mes sin año que ya pasó hace más de un mes es del año que viene.
     */
    public static function dateIn(string $query): ?CarbonImmutable
    {
        $text = preg_replace('/^(ir( a)?( la)?( agenda)?( del?| al)?|agenda( del?)?|ver( la agenda)?( del?)?|el|este|proximo)\s+/', '', implode(' ', self::words($query))) ?? '';
        $today = CarbonImmutable::today();

        return self::namedDay($text, $today) ?? self::numericDate($text, $today) ?? self::spelledDate($text, $today);
    }

    /** "hoy", "mañana", "pasado mañana", "ayer" o un día de la semana (el próximo, o hoy si es ese día). */
    private static function namedDay(string $text, CarbonImmutable $today): ?CarbonImmutable
    {
        $offsets = ['hoy' => 0, 'manana' => 1, 'pasado manana' => 2, 'ayer' => -1];

        return match (true) {
            array_key_exists($text, $offsets) => $today->addDays($offsets[$text]),
            array_key_exists($text, self::WEEKDAYS) => $today->addDays((self::WEEKDAYS[$text] - $today->dayOfWeekIso + 7) % 7),
            default => null,
        };
    }

    /**
     * "2026-11-25", "25/11/26", "25/11" o "25" (un día de este mes). Un número de más de dos cifras es un DNI o un
     * celular, no una fecha.
     */
    private static function numericDate(string $text, CarbonImmutable $today): ?CarbonImmutable
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m)) {
            return self::safeDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (! preg_match('/^(\d{1,2})(?:[\/\-.](\d{1,2})(?:[\/\-.](\d{2}|\d{4}))?)?$/', $text, $m)) {
            return null;
        }

        return self::dated((int) $m[1], isset($m[2]) ? (int) $m[2] : $today->month, isset($m[3]) ? (int) str_pad($m[3], 4, '20', STR_PAD_LEFT) : null, $today);
    }

    /** "25 de noviembre", "25 nov" o "25 de noviembre de 2026": del mes basta con las tres primeras letras. */
    private static function spelledDate(string $text, CarbonImmutable $today): ?CarbonImmutable
    {
        if (! preg_match('/^(\d{1,2})\s+(?:de\s+)?([a-z]{3,})(?:\s+(?:del?\s+)?(\d{4}))?$/', $text, $m)) {
            return null;
        }

        $month = collect(self::MONTHS)->first(fn (int $number, string $name) => str_starts_with($name, $m[2]));

        return $month ? self::dated((int) $m[1], $month, isset($m[3]) ? (int) $m[3] : null, $today) : null;
    }

    private static function dated(int $day, int $month, ?int $year, CarbonImmutable $today): ?CarbonImmutable
    {
        $date = self::safeDate($year ?? $today->year, $month, $day);

        // "5/1" escrito en noviembre es el enero que viene, no el que pasó.
        if ($date !== null && $year === null && $date->lt($today->subMonth())) {
            $date = self::safeDate($today->year + 1, $month, $day);
        }

        return $date;
    }

    private static function safeDate(int $year, int $month, int $day): ?CarbonImmutable
    {
        return checkdate($month, $day, $year) && $year >= 2000 && $year <= 2100 ? CarbonImmutable::create($year, $month, $day) : null;
    }

    /**
     * Cada palabra escrita es el comienzo de alguna palabra de la acción: "nue", "cita nueva" o "llego" sirven.
     *
     * @param  list<string>  $words
     */
    private static function matches(array $words, string $keywords): bool
    {
        $known = explode(' ', $keywords);

        return collect($words)->every(fn (string $word) => collect($known)->contains(fn (string $keyword) => str_starts_with($keyword, $word)));
    }

    /** @return list<string> Lo escrito en minúsculas, sin tildes ni signos. */
    private static function words(string $query): array
    {
        $plain = Str::of($query)->ascii()->lower()->replaceMatches('/[^a-z0-9\/\-.\s]+/', ' ')->squish()->toString();

        return $plain === '' ? [] : explode(' ', $plain);
    }
}
