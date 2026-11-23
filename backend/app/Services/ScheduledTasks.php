<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Las tareas automáticas (recordatorios, cuotas, lista de espera, copias de seguridad) corren solo si la computadora
 * ejecuta el programador cada minuto: en un hosting es un cron; en una laptop con Windows hay que encenderlo. Cada
 * pasada deja su latido con la hora, y así el panel sabe si están corriendo.
 */
final class ScheduledTasks
{
    /** La hora de la última pasada (segundos desde 1970). Otras partes del sistema la leen con esta misma clave. */
    public const HEARTBEAT = 'scheduler.last_run_at';

    /** Con más de una hora sin latido, no están corriendo. */
    public const STOPPED_AFTER_MINUTES = 60;

    /**
     * Desde cuándo se espera el primer latido. Una instalación recién hecha no avisa enseguida: tiene la misma hora
     * para encenderlas que cualquier otra.
     */
    private const WATCHING_SINCE = 'scheduler.watching_since';

    /** @param  string  $os  Dónde corre el sistema (PHP_OS_FAMILY): en Windows es la laptop del consultorio. */
    public function __construct(private string $os = PHP_OS_FAMILY) {}

    /** Lo anota cada pasada del programador (routes/console.php). */
    public static function beat(): void
    {
        Cache::forever(self::HEARTBEAT, now()->getTimestamp());
    }

    public function lastRunAt(): ?CarbonImmutable
    {
        $timestamp = Cache::get(self::HEARTBEAT);

        return is_numeric($timestamp) ? CarbonImmutable::createFromTimestamp((int) $timestamp, config('app.timezone')) : null;
    }

    /** Corrieron en la última hora. */
    public function areRunning(): bool
    {
        $last = $this->lastRunAt();

        return $last !== null && $last->gte(now()->subMinutes(self::STOPPED_AFTER_MINUTES));
    }

    /**
     * Hay que avisar: llevan más de una hora sin correr o, si nunca corrieron, pasó una hora desde que el panel empezó a
     * esperarlas. Sin esa hora de gracia, una instalación nueva avisaría antes de que nadie haya podido encenderlas.
     */
    public function areStopped(): bool
    {
        if ($this->lastRunAt() !== null) {
            return ! $this->areRunning();
        }

        Cache::add(self::WATCHING_SINCE, now()->getTimestamp());

        return (int) Cache::get(self::WATCHING_SINCE) < now()->subMinutes(self::STOPPED_AFTER_MINUTES)->getTimestamp();
    }

    /** De dónde son: "de esta computadora" en la laptop con Windows, "del servidor" en un hosting. */
    public function ofMachine(): string
    {
        return $this->onWindows() ? 'de esta computadora' : 'del servidor';
    }

    /** Cómo encenderlas, igual en Hoy, en Copias de seguridad y en la puesta a punto del consultorio. */
    public function howToTurnOn(): string
    {
        return $this->onWindows()
            ? 'Para encenderlas, abre la carpeta del sistema, entra a infra › windows, haz doble clic en «encender-tareas-automaticas.bat» y acepta el permiso que pide Windows. Se hace una sola vez: desde ahí corren solas mientras la computadora esté prendida.'
            : 'Pide a quien instaló el sistema que encienda la tarea que corre cada minuto en el servidor: está en su guía de instalación.';
    }

    private function onWindows(): bool
    {
        return $this->os === 'Windows';
    }
}
