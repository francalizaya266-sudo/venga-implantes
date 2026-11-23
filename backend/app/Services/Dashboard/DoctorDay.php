<?php

namespace App\Services\Dashboard;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Consultation;
use Illuminate\Database\Eloquent\Collection;

/** Lo que el doctor necesita al entrar: a quién atiende ahora y lo que dejó sin terminar. */
final class DoctorDay
{
    public function __construct(public readonly int $doctorId) {}

    /**
     * El siguiente paciente de hoy: quien recepción ya pasó al sillón y todavía no tiene su atención, quien está
     * en sala (el que llegó primero) o, si nadie llegó, la próxima cita por hora. No cuenta la atención que el
     * doctor ya empezó (esa va en "Atenciones sin terminar"), ni a quien no llegó y ya pasó su hora: eso es una
     * inasistencia por marcar, no un paciente que viene.
     */
    public function next(): ?Appointment
    {
        return $this->upcoming(1)->first();
    }

    /**
     * Los que vienen, en el orden en que se atienden: primero quien ya pasó al sillón sin que el doctor abriera
     * su atención (recepción tocó "Pasó a consulta"), luego los que esperan en sala (por orden de llegada) y
     * después las citas que faltan, por hora. El segundo es el "Después:" de "Tu próximo paciente".
     *
     * @return Collection<int, Appointment>
     */
    public function upcoming(int $count = 2): Collection
    {
        return Appointment::query()
            ->where('doctor_id', $this->doctorId)
            ->startsOn(today())
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->inChairWithoutConsultation())
                ->orWhere(fn ($query) => $query->waitingNow())
                ->orWhere(fn ($query) => $query
                    ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Pending])
                    ->where('ends_at', '>', now())))
            ->with(['patient.medicalBackground', 'treatment', 'planItem'])
            ->orderByRaw('case status when ? then 0 when ? then 1 else 2 end', [AppointmentStatus::InProgress->value, AppointmentStatus::CheckedIn->value])
            ->orderByRaw('case when status = ? then checked_in_at end', [AppointmentStatus::CheckedIn->value])
            ->orderBy('starts_at')
            ->limit($count)
            ->get();
    }

    /** Si hoy atiende: tiene alguna cita que no se canceló, ya sea que venga, esté en sala o ya se atendió. */
    public function hasAppointmentsToday(): bool
    {
        return Appointment::query()
            ->where('doctor_id', $this->doctorId)
            ->startsOn(today())
            ->where('status', '!=', AppointmentStatus::Cancelled)
            ->exists();
    }

    /** Cuántos pacientes suyos esperan en sala ahora. */
    public function waiting(): int
    {
        return Appointment::query()
            ->where('doctor_id', $this->doctorId)
            ->waitingNow()
            ->count();
    }

    /** La última vez que el paciente se atendió (con cualquier doctor), para saber qué se hizo. */
    public function lastVisit(Appointment $appointment): ?Appointment
    {
        return Appointment::query()
            ->where('patient_id', $appointment->patient_id)
            ->where('status', AppointmentStatus::Completed)
            ->where('starts_at', '<', $appointment->starts_at)
            ->with('treatment')
            ->latest('starts_at')
            ->first();
    }

    /** @return Collection<int, Consultation> Sus atenciones abiertas, la más antigua primero. */
    public function openConsultations(): Collection
    {
        return Consultation::query()
            ->where('doctor_id', $this->doctorId)
            ->whereNull('closed_at')
            ->with(['patient', 'appointment.treatment'])
            ->oldest('attended_at')
            ->get();
    }
}
