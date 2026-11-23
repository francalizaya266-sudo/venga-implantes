<?php

namespace App\Services\Notices;

use App\Enums\AppointmentStatus;
use App\Enums\MessageTemplateKey;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\WhatsappMessage;
use App\Support\Dates;
use Carbon\CarbonInterface;

/**
 * Lo que dice un aviso en la pantalla del celular: corto y sin nada privado, porque se lee con el celular bloqueado.
 * Dice de quién es, el día, la hora y con qué doctor; el tratamiento y los montos se ven al abrir Mi cuenta.
 */
class PhoneNoticeText
{
    /** @return array{title: string, body: string, url: string, tag: string} */
    public function for(WhatsappMessage $message): array
    {
        /** @var MessageTemplateKey $key */
        $key = $message->template_key;
        /** @var array<string, string> $params */
        $params = $message->params ?? [];
        $name = (string) ($params['nombre'] ?? '');

        if (in_array($key, [MessageTemplateKey::InstallmentReminder, MessageTemplateKey::InstallmentOverdue], true)) {
            return $this->installment($message, $key, $name, $params);
        }

        /** @var Appointment|null $appointment */
        $appointment = $message->appointment;
        [$title, $body] = $this->appointmentText($key, $appointment, $params);

        return [
            'title' => $title,
            'body' => $this->addressed($name, $body),
            'url' => '/mi-cuenta#proximas',
            'tag' => $appointment ? 'cita-'.$appointment->getKey() : 'aviso-'.$message->getKey(),
        ];
    }

    /**
     * @param  array<string, string>  $params
     * @return array{0: string, 1: string}
     */
    private function appointmentText(MessageTemplateKey $key, ?Appointment $appointment, array $params): array
    {
        /** @var CarbonInterface|null $startsAt */
        $startsAt = $appointment?->starts_at;
        /** @var Doctor|null $doctor */
        $doctor = $appointment?->doctor;
        /** @var AppointmentStatus|null $status */
        $status = $appointment?->status;

        $when = $startsAt ? self::when($startsAt) : trim('el '.($params['fecha'] ?? '').' a las '.($params['hora'] ?? ''));
        $withDoctor = $doctor ? ' con '.$doctor->name_with_article : '';
        $toConfirm = $status === AppointmentStatus::Pending ? ' Confírmala en Mi cuenta.' : '';

        return match ($key) {
            MessageTemplateKey::BookingConfirmation => ['Cita reservada', self::sentence($when.$withDoctor)],
            MessageTemplateKey::Reminder => ['Recordatorio de cita', self::sentence($when.$withDoctor).$toConfirm],
            MessageTemplateKey::Rescheduled => ['La cita cambió de horario', self::sentence('ahora es '.$when.$withDoctor)],
            MessageTemplateKey::RescheduleApproved => ['Aprobamos el cambio de horario', self::sentence('ahora es '.$when.$withDoctor)],
            MessageTemplateKey::RescheduleRejected => ['No pudimos mover la cita', self::sentence('sigue '.$when).' Escríbenos y buscamos otra opción.'],
            MessageTemplateKey::Cancelled => ['Cita cancelada', self::sentence('se canceló la cita '.($startsAt ? Dates::ofDay($startsAt, withDate: true).' a las '.Dates::time($startsAt) : 'del '.($params['fecha'] ?? ''))).' Escríbenos y te ayudamos a elegir otro horario.'],
            default => ['Nueva cita', self::sentence($when.$withDoctor)],
        };
    }

    /**
     * @param  array<string, string>  $params
     * @return array{title: string, body: string, url: string, tag: string}
     */
    private function installment(WhatsappMessage $message, MessageTemplateKey $key, string $name, array $params): array
    {
        $installment = $params['cuota'] ?? 'cuota';
        $date = $params['fecha'] ?? '';
        $overdue = $key === MessageTemplateKey::InstallmentOverdue;

        return [
            'title' => $overdue ? 'Cuota vencida' : 'Cuota por vencer',
            'body' => $this->addressed($name, 'la '.$installment.($overdue ? ' venció el ' : ' vence el ').$date.'. El monto está en Mi cuenta.'),
            'url' => '/mi-cuenta#planes',
            'tag' => 'aviso-'.$message->getKey(),
        ];
    }

    /** "Ana: mañana a las…": en una familia, el aviso dice de quién es. */
    private function addressed(string $name, string $text): string
    {
        return $name !== '' ? $name.': '.$text : ucfirst($text);
    }

    /** Con su punto al final, sin repetirlo después de "p. m.". */
    private static function sentence(string $text): string
    {
        return str_ends_with($text, '.') ? $text : $text.'.';
    }

    /** "hoy a las 3:00 p. m.", "mañana a las…" o "el lunes 16 de noviembre a las…". */
    private static function when(CarbonInterface $startsAt): string
    {
        $day = match (true) {
            $startsAt->isToday() => 'hoy',
            $startsAt->isTomorrow() => 'mañana',
            default => 'el '.$startsAt->translatedFormat('l j \d\e F'),
        };

        return $day.' a las '.Dates::time($startsAt);
    }
}
