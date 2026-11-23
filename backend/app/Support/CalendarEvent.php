<?php

namespace App\Support;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\Web\ClinicInfo;
use Carbon\CarbonInterface;

/** La cita como archivo de calendario (.ics), para guardarla en el celular con un toque. */
final class CalendarEvent
{
    /** El calendario avisa dos horas antes: alcanza para llegar al consultorio. */
    private const REMINDER_MINUTES = 120;

    public static function forAppointment(Appointment $appointment, ClinicInfo $clinic): string
    {
        $stamp = fn (CarbonInterface $moment): string => $moment->copy()->utc()->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.self::escape($clinic->name()).'//Citas//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:cita-'.$appointment->getKey().'@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'web'),
            'DTSTAMP:'.$stamp(now()),
            'DTSTART:'.$stamp($appointment->starts_at),
            'DTEND:'.$stamp($appointment->ends_at),
            'SUMMARY:'.self::escape($appointment->treatment->name.' · '.$clinic->name()),
            $clinic->fullAddress() ? 'LOCATION:'.self::escape($clinic->fullAddress()) : null,
            'DESCRIPTION:'.self::escape("Cita de {$appointment->patient->first_name} con {$appointment->doctor->name}."),
            'BEGIN:VALARM',
            'ACTION:DISPLAY',
            'DESCRIPTION:'.self::escape('Tu cita en '.$clinic->name()),
            'TRIGGER:-PT'.self::REMINDER_MINUTES.'M',
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return collect($lines)->filter()->map(self::fold(...))->implode("\r\n")."\r\n";
    }

    /**
     * La agenda de un doctor, para suscribirse desde el calendario del celular: cada cita con su hora, su duración,
     * el tratamiento y el nombre corto del paciente. Nada clínico ni celulares, y sin alarmas (el calendario del
     * celular ya avisa a su modo). El celular la vuelve a leer sola cada cierto tiempo.
     *
     * @param  iterable<Appointment>  $appointments  Con el paciente y el tratamiento ya cargados.
     */
    public static function forDoctorAgenda(iterable $appointments, ClinicInfo $clinic): string
    {
        $stamp = fn (CarbonInterface $moment): string => $moment->copy()->utc()->format('Ymd\THis\Z');
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'web';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.self::escape($clinic->name()).'//Agenda//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::escape('Citas · '.$clinic->name()),
            'X-WR-TIMEZONE:'.config('app.timezone'),
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
        ];

        foreach ($appointments as $appointment) {
            $state = $appointment->status === AppointmentStatus::Pending ? 'Por confirmar' : $appointment->status->getLabel();

            array_push(
                $lines,
                'BEGIN:VEVENT',
                'UID:agenda-'.$appointment->getKey().'@'.$host,
                'DTSTAMP:'.$stamp(now()),
                'DTSTART:'.$stamp($appointment->starts_at),
                'DTEND:'.$stamp($appointment->ends_at),
                'SUMMARY:'.self::escape($appointment->patient->short_name.' · '.$appointment->treatment->name),
                'DESCRIPTION:'.self::escape($appointment->treatment->name.' · '.str_replace("\u{00A0}", ' ', Dates::duration($appointment->durationMinutes())).' · '.$state),
                'STATUS:'.($appointment->status === AppointmentStatus::Pending ? 'TENTATIVE' : 'CONFIRMED'),
                $clinic->fullAddress() ? 'LOCATION:'.self::escape($clinic->fullAddress()) : null,
                'END:VEVENT',
            );
        }

        $lines[] = 'END:VCALENDAR';

        return collect($lines)->filter()->map(self::fold(...))->implode("\r\n")."\r\n";
    }

    /** En un .ics la coma, el punto y coma y el salto de línea separan campos: van escapados. */
    private static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
    }

    /** Las líneas de más de 75 bytes siguen en la siguiente, que empieza con un espacio (sin partir una letra con tilde). */
    private static function fold(string $line): string
    {
        $parts = [];

        while (strlen($line) > 75) {
            $head = mb_strcut($line, 0, 75, 'UTF-8');
            $parts[] = $head;
            $line = ' '.substr($line, strlen($head));
        }

        $parts[] = $line;

        return implode("\r\n", $parts);
    }
}
