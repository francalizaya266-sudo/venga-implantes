<?php

namespace App\Services\Agenda;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * La agenda de cada doctor en el calendario de su celular. El celular se suscribe a un enlace secreto (.ics) y lo
 * vuelve a leer cada cierto tiempo: las citas nuevas, movidas o canceladas se ven solas. Va solo lo que sirve para
 * organizarse (hora, duración, tratamiento y el nombre corto del paciente): nada clínico ni celulares.
 */
final class DoctorCalendarFeed
{
    /** También la última semana, para ver lo que ya pasó. */
    public const PAST_DAYS = 7;

    /** Hasta tres meses adelante: los controles ya agendados también. */
    public const FUTURE_DAYS = 90;

    /** Letras y números al azar: imposible de adivinar. */
    public const TOKEN_LENGTH = 40;

    /** El enlace del doctor, o null si todavía no lo creó. */
    public function url(Doctor $doctor): ?string
    {
        if (blank($doctor->calendar_token)) {
            return null;
        }

        return route('doctor.calendar', ['doctor' => $doctor->getKey(), 'token' => $doctor->calendar_token]);
    }

    /** Crea el enlace, o lo cambia por uno nuevo: el anterior deja de funcionar al instante. */
    public function renew(Doctor $doctor): string
    {
        $hadLink = filled($doctor->calendar_token);

        $doctor->forceFill(['calendar_token' => Str::random(self::TOKEN_LENGTH)])->save();

        if ($hadLink) {
            $doctor->auditEvent('calendar_link_changed');
        }

        return (string) $this->url($doctor);
    }

    /**
     * El doctor dueño del enlace, o null si el enlace no vale. No dice por qué: un enlace inventado, uno ya cambiado,
     * el de un doctor sin enlace, el de un doctor inactivo y el de alguien que ya no entra al panel dan lo mismo. La
     * clave se compara en tiempo constante, y se compara siempre, aunque no haya con qué, para que la respuesta tarde
     * lo mismo.
     */
    public function find(int $doctorId, string $token): ?Doctor
    {
        $doctor = Doctor::query()->with('user')->find($doctorId);
        $expected = (string) $doctor?->calendar_token;
        /** @var User|null $user */
        $user = $doctor?->user;

        $matches = hash_equals($expected !== '' ? $expected : str_repeat('-', self::TOKEN_LENGTH), $token);

        return $matches && $expected !== '' && $doctor?->is_active && $user?->is_active ? $doctor : null;
    }

    /**
     * Las citas que van al calendario: las de la última semana y los próximos meses que ocupan su agenda (sin las
     * canceladas ni las de quien no vino, que dejan el horario libre).
     *
     * @return Collection<int, Appointment>
     */
    public function appointments(Doctor $doctor): Collection
    {
        return Appointment::query()
            ->whereBelongsTo($doctor)
            ->with(['patient', 'treatment'])
            ->whereIn('status', AppointmentStatus::occupying())
            ->where('starts_at', '>=', today()->subDays(self::PAST_DAYS))
            ->where('starts_at', '<', today()->addDays(self::FUTURE_DAYS + 1))
            ->orderBy('starts_at')
            ->get();
    }
}
