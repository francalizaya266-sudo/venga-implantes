<?php

namespace App\Services\Patients;

use App\Exceptions\DniLookupException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Los nombres y apellidos de un DNI según RENIEC, por medio de Decolecta, para no escribirlos a mano al registrar
 * a un paciente. Sale el número de DNI y nada más: ningún otro dato del consultorio ni del paciente.
 */
final class DniLookup
{
    /** Las palabras que van en minúscula dentro de un nombre ("De la Cruz", "Del Carpio"). */
    private const PARTICLES = ['de', 'del', 'la', 'las', 'los', 'y'];

    /** Sin clave no se consulta: el botón ni aparece. */
    public function isEnabled(): bool
    {
        return filled(config('consultorio.dni_lookup.token'));
    }

    /**
     * @return array{first_name: string, last_name: string}|null Null si RENIEC no tiene ese DNI.
     *
     * @throws DniLookupException Si el servicio no respondió o ya no quedan consultas.
     */
    public function find(string $dni): ?array
    {
        try {
            $response = Http::withToken((string) config('consultorio.dni_lookup.token'))
                ->acceptJson()
                ->timeout(5)
                ->get(config('consultorio.dni_lookup.url'), ['numero' => $dni]);
        } catch (ConnectionException) {
            throw DniLookupException::unavailable();
        }

        if (in_array($response->status(), [400, 404, 422], true)) {
            return null;
        }

        if ($response->status() === 429) {
            throw DniLookupException::limitReached();
        }

        if ($response->failed()) {
            // Una clave vencida o mal copiada también cae aquí: queda en el registro de errores para el administrador.
            report($response->toException());

            throw DniLookupException::unavailable();
        }

        if (blank($response->json('first_name'))) {
            return null;
        }

        return [
            'first_name' => $this->properName($response->json('first_name')),
            'last_name' => $this->properName($response->json('first_last_name').' '.$response->json('second_last_name')),
        ];
    }

    /** RENIEC los da en mayúsculas: "DE LA CRUZ ÑAUPARI" queda "De la Cruz Ñaupari". */
    private function properName(string $name): string
    {
        return Str::of($name)
            ->squish()
            ->lower()
            ->explode(' ')
            ->map(fn (string $word, int $index) => $index > 0 && in_array($word, self::PARTICLES, true) ? $word : Str::ucfirst($word))
            ->implode(' ');
    }
}
