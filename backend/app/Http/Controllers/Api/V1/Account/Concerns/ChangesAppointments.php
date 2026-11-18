<?php

namespace App\Http\Controllers\Api\V1\Account\Concerns;

use App\Exceptions\BookingException;
use App\Http\Resources\V1\AppointmentCardResource;
use App\Http\Resources\V1\SlotPickerResource;
use App\Models\Appointment;
use App\Services\Booking\AppointmentChange;
use App\Services\Booking\WebAvailability;
use App\Support\Dates;
use Illuminate\Http\JsonResponse;

/**
 * Cambiar el horario de una cita, igual desde Mi cuenta que desde el enlace de WhatsApp: lo que se muestra,
 * los horarios de cada día y el cambio en sí.
 */
trait ChangesAppointments
{
    /**
     * Cómo está la cita ahora, si el cambio es al instante o lo aprueba el consultorio, y los días y horarios libres.
     *
     * @return array<string, mixed>
     */
    protected function changeScreen(Appointment $appointment): array
    {
        $change = app(AppointmentChange::class);
        $appointment->loadMissing(['patient', 'doctor', 'treatment']);
        $canChange = $change->canChange($appointment);
        $days = $canChange ? $change->days($appointment) : [];
        $pending = $change->pendingRequest($appointment);

        return [
            'appointment' => [
                'what' => $appointment->treatment->name.' · '.$appointment->patient->first_name,
                'now' => Dates::day($appointment->starts_at->toImmutable()).', '.Dates::time($appointment->starts_at->format('H:i')),
                'doctor' => $appointment->doctor->name,
            ],
            'can_change' => $canChange,
            'automatic' => $change->isAutomatic($appointment),
            // Una sesión con fecha coordinada con el doctor siempre pasa por el consultorio.
            'fixed_date' => (bool) $appointment->fixed_date,
            'max_options' => $change->maxOptions(),
            'pending_request' => $pending ? AppointmentCardResource::optionsText($pending) : null,
            ...$this->changePicker($appointment, $days, $days[0] ?? null),
        ];
    }

    /**
     * Los días (si se piden) y los horarios libres de un día para mover la cita.
     *
     * @param  list<string>|null  $days
     * @return array<string, mixed>
     */
    protected function changePicker(Appointment $appointment, ?array $days, ?string $date): array
    {
        $slots = $date ? app(AppointmentChange::class)->slots($appointment, $date) : collect();

        return SlotPickerResource::make([
            'days' => $days,
            'date' => $date,
            'slots' => $slots,
            'suggested' => app(WebAvailability::class)->suggested($slots),
        ])->resolve();
    }

    /**
     * Cambia la cita o envía el pedido. Si el horario ya no está, responde 422 con el motivo para elegir otro.
     *
     * @param  list<string>  $chosen  Horarios elegidos: "Y-m-d H:i".
     */
    protected function applyChange(Appointment $appointment, array $chosen, ?string $reason): JsonResponse
    {
        try {
            $message = app(AppointmentChange::class)->save($appointment, $chosen, $reason);
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $message]);
    }
}
