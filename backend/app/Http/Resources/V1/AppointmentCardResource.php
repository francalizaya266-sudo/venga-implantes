<?php

namespace App\Http\Resources\V1;

use App\Enums\AppointmentStatus;
use App\Enums\PatientRelationship;
use App\Models\Appointment;
use App\Models\RescheduleRequest;
use App\Models\RescheduleRequestOption;
use App\Services\Booking\PatientChangePolicy;
use App\Support\Dates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La tarjeta de una cita. Es la misma en el enlace de WhatsApp, en Mi cuenta y en el inicio: mismo orden y
 * mismas palabras ("Confirmar asistencia", "Cambiar horario", "Cancelar cita").
 *
 * @mixin Appointment
 */
class AppointmentCardResource extends JsonResource
{
    /** De quién es la cita, o null si es del titular del celular ("Tu cita"). */
    private ?string $name = null;

    /** Se reservó con este número sin entrar desde el celular de la familia y nadie la confirmó. */
    private bool $guest = false;

    /** Muestra "Antes de la cita" a quien todavía no contó su salud. */
    private bool $askHealth = false;

    /** El pedido de cambio de horario que espera respuesta, donde se conoce. */
    private ?RescheduleRequest $pendingRequest = null;

    public function shownAs(?string $name, bool $guest = false, bool $askHealth = false, ?RescheduleRequest $pendingRequest = null): static
    {
        $this->name = $name;
        $this->guest = $guest;
        $this->askHealth = $askHealth;
        $this->pendingRequest = $pendingRequest;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $policy = app(PatientChangePolicy::class);
        $status = $this->status;
        $day = Dates::day($this->starts_at->toImmutable());
        $hour = Dates::time($this->starts_at->format('H:i'));
        $canChange = $policy->canChange($this->resource);
        $patient = $this->patient;

        return [
            'id' => $this->id,
            'kicker' => $this->name ? 'Cita de '.$this->name : 'Tu cita',
            // "Pendiente" es la palabra del consultorio; al paciente se le dice lo que le toca hacer.
            'status' => $status === AppointmentStatus::Pending ? 'Por confirmar' : $status->getLabel(),
            'tone' => match (true) {
                $status === AppointmentStatus::Confirmed => 'ok',
                $status === AppointmentStatus::Cancelled => 'bad',
                $status === AppointmentStatus::Pending => null,
                default => 'quiet',
            },
            'when' => $day.', '.$hour,
            'what' => $this->treatment->name.' · '.$this->doctor->name,
            // La página no pide códigos al reservar: quien recibe una cita que no esperaba tiene que poder decir que no es suya.
            'guest_notice' => $this->guest && $status === AppointmentStatus::Pending,
            'ask_health' => $this->askHealth && ! ($this->guest && $status === AppointmentStatus::Pending) ? [
                'patient_id' => $patient->id,
                'text' => ($patient->relationship === PatientRelationship::Self ? 'Cuéntanos de tu salud' : 'Cuéntanos de la salud de '.$patient->first_name).' en dos minutos.',
            ] : null,
            'pending_request' => $this->pendingRequest ? self::optionsText($this->pendingRequest) : null,
            'can_change' => $canChange,
            'can_confirm' => $canChange && $status === AppointmentStatus::Pending,
            'can_cancel' => $policy->canSelfCancel($this->resource),
            'cancel_min_hours' => $policy->selfCancelMinHours(),
            // La hora ya termina en punto ("3:00 p. m."): no se le pone otro.
            'cancel_message' => 'Hola, necesito cancelar la cita de '.$patient->full_name.' del '.mb_strtolower($day).' a las '.rtrim($hour, '.').'.',
        ];
    }

    /** "lunes 3 de noviembre a las 9:00 a. m.; martes 4 de noviembre a las 10:00 a. m. o …". */
    public static function optionsText(RescheduleRequest $request): string
    {
        return $request->options
            ->map(fn (RescheduleRequestOption $option) => mb_strtolower(Dates::day($option->starts_at->toImmutable())).' a las '.Dates::time($option->starts_at->format('H:i')))
            ->join('; ', ' o ');
    }
}
