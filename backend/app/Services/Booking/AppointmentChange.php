<?php

namespace App\Services\Booking;

use App\Enums\RescheduleRequestStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\RescheduleRequest;
use App\Models\Setting;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Cambiar el horario de una cita desde el enlace o desde Mi cuenta.
 * Nivel 1: el paciente elige otro horario y se cambia solo. Nivel 2: propone
 * hasta 3 horarios y el consultorio aprueba uno.
 */
class AppointmentChange
{
    public function __construct(
        private readonly WebAvailability $availability,
        private readonly PatientChangePolicy $policy,
        private readonly AppointmentService $appointments,
        private readonly RescheduleRequestService $requests,
    ) {}

    public function canChange(Appointment $appointment): bool
    {
        return $this->policy->canChange($appointment);
    }

    /** El cambio se hace al instante; si no, el consultorio aprueba uno de los horarios propuestos. */
    public function isAutomatic(Appointment $appointment): bool
    {
        return $this->policy->rescheduleMode($appointment) === RescheduleMode::Automatic;
    }

    /** Cuántos horarios puede proponer cuando el cambio lo aprueba el consultorio. */
    public function maxOptions(): int
    {
        return (int) Setting::get('booking.max_reschedule_options', 3);
    }

    /**
     * Los próximos días con horarios libres para mover la cita, con su mismo doctor.
     *
     * @return list<string> Días en Y-m-d.
     */
    public function days(Appointment $appointment): array
    {
        return array_map(
            fn (CarbonImmutable $day) => $day->toDateString(),
            $this->availability->days($appointment->treatment, $this->doctors($appointment), moving: $appointment),
        );
    }

    /**
     * Los horarios libres de un día para mover la cita (su propio horario no cuenta como ocupado).
     *
     * @return Collection<int, array{time: string, doctor_id: int, recommended: bool, waste: int}>
     */
    public function slots(Appointment $appointment, string $date): Collection
    {
        return $this->availability->slots($appointment->treatment, $this->doctors($appointment), CarbonImmutable::parse($date), $appointment);
    }

    /**
     * Cambia la cita al horario elegido o envía el pedido con los horarios propuestos.
     *
     * @param  list<string>  $chosen  Horarios elegidos: "Y-m-d H:i".
     * @return string Lo que se le dice al paciente al terminar.
     *
     * @throws BookingException
     */
    public function save(Appointment $appointment, array $chosen, ?string $reason): string
    {
        if (! $this->policy->canChange($appointment)) {
            throw BookingException::notChangeable();
        }

        $starts = array_map(fn (string $key) => CarbonImmutable::parse($key), $chosen);

        if ($this->isAutomatic($appointment)) {
            $this->appointments->rescheduleByPatient($appointment, $starts[0]);

            return '¡Listo! Tu cita se cambió al '.mb_strtolower(Dates::day($starts[0])).', '.Dates::time($starts[0]->format('H:i'));
        }

        if (count($starts) > $this->maxOptions()) {
            throw new BookingException('Puedes proponer hasta '.$this->maxOptions().' horarios. Quita uno para elegir otro.');
        }

        $this->requests->requestByPatient($appointment, $starts, filled($reason) ? $reason : null);

        return 'Enviamos tu pedido. Te avisaremos por WhatsApp cuando el consultorio elija uno de tus horarios. Mientras tanto, tu cita sigue como estaba.';
    }

    public function pendingRequest(Appointment $appointment): ?RescheduleRequest
    {
        return $appointment->rescheduleRequests()->where('status', RescheduleRequestStatus::Pending)->with('options')->latest('id')->first();
    }

    /**
     * Se mueve con el mismo doctor.
     *
     * @return Collection<int, Doctor>
     */
    private function doctors(Appointment $appointment): Collection
    {
        return Doctor::query()->whereKey($appointment->doctor_id)->get();
    }
}
