<?php

namespace App\Services\Dashboard;

use App\Models\Doctor;
use App\Services\Agenda\GapFinder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Qué días se atiende, con la misma regla que la Agenda ("no se atiende"): el horario de los doctores menos los
 * bloqueos (feriados, vacaciones, cursos). Un día sin horario, o bloqueado entero, no se atiende. Para que "Hoy"
 * diga, en un domingo o un feriado, que el consultorio no atiende y cuándo vuelve.
 */
final class OpenDays
{
    /** Hasta cuántos días adelante se busca: un mes cubre unas vacaciones largas. */
    public const LOOK_AHEAD_DAYS = 31;

    public function __construct(private readonly GapFinder $gaps) {}

    /**
     * El primer día con atención desde ese día (incluido) y dentro de un mes; null si en ese mes no atiende nadie.
     *
     * @param  Collection<int, Doctor>  $doctors
     */
    public function firstFrom(CarbonInterface $day, Collection $doctors): ?CarbonImmutable
    {
        if ($doctors->isEmpty()) {
            return null;
        }

        $from = CarbonImmutable::instance($day)->startOfDay();
        $load = $this->gaps->occupancy($doctors, $from, $from->addDays(self::LOOK_AHEAD_DAYS));
        $first = collect($load)->filter(fn (?int $occupancy) => $occupancy !== null)->keys()->first();

        return $first === null ? null : CarbonImmutable::parse($first);
    }
}
