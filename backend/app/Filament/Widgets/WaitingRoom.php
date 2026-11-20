<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Filament\Concerns\RefreshesWithToday;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Tables\AppointmentsTable;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Arriba, cada doctor que atiende hoy: a quién tiene en consulta y desde hace cuánto, o si está libre, y cuántos
 * lo esperan; así se sabe a quién pasar sin recorrer la agenda. Debajo, quién está en sala y hace cuánto, de quien
 * más espera a quien acaba de llegar. Desde
 * TodayAppointments::LONG_WAIT_MINUTES la espera va en rojo. Es de quien ve todas las agendas; el doctor ve a
 * quien lo espera en "Tu próximo paciente". Quien quedó "en sala" horas después de su cita ya no sale: es una
 * cita sin cerrar. Se actualiza sola cada 30 s.
 */
class WaitingRoom extends Widget
{
    use RefreshesWithToday;

    /** Adónde lleva "En sala ahora", el número de arriba. */
    public const ANCHOR = 'sala-de-espera';

    /** La sala de espera se ve apenas abre "Hoy", sin un recuadro vacío mientras carga. */
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.waiting-room';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->managesAllAgendas();
    }

    /**
     * Cuántos minutos lleva cada uno de los que están en sala: lo que cuenta "En sala ahora". Como esta tarjeta,
     * es de quien ve todas las agendas: cuenta a todos.
     *
     * @return Collection<int, int>
     */
    public static function waitingMinutes(): Collection
    {
        return Appointment::query()
            ->waitingNow()
            ->get(['id', 'status', 'checked_in_at'])
            ->map(fn (Appointment $appointment) => (int) $appointment->waitingMinutes())
            ->values()
            ->toBase();
    }

    /** @return array{rooms: list<array{name: string, busy: bool, status: string, waiting: int}>, patients: list<array{name: string, detail: string, waited: string, long: bool, url: string}>} */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $severalDoctors = AppointmentsTable::showsDoctor($user);

        $waiting = AppointmentResource::getEloquentQuery()
            ->with(['patient', 'treatment', 'doctor'])
            ->waitingNow()
            ->orderBy('checked_in_at')
            ->get();

        return ['rooms' => $this->rooms(), 'patients' => $waiting->map(fn (Appointment $appointment) => [
            'name' => $appointment->patient->full_name,
            'detail' => collect([
                $appointment->treatment->name,
                'cita de las '.$appointment->starts_at->format('H:i'),
                $severalDoctors ? $appointment->doctor?->name : null,
            ])->filter()->implode(' · '),
            'waited' => Dates::duration((int) $appointment->waitingMinutes()),
            'long' => $appointment->waitingMinutes() >= TodayAppointments::LONG_WAIT_MINUTES,
            'url' => AppointmentResource::getUrl('view', ['record' => $appointment]),
        ])->all()];
    }

    /**
     * Los doctores con citas hoy, en su orden: "Atendiendo a Rosa Quispe, hace 28 min" o "Libre", y cuántos lo
     * esperan en sala. Si tiene a alguien en consulta desde antes y nadie lo cerró, se ve aquí también.
     *
     * @return list<array{name: string, busy: bool, status: string, waiting: int}>
     */
    private function rooms(): array
    {
        $doctors = Doctor::query()
            ->whereHas('appointments', fn ($appointments) => $appointments
                ->whereDate('starts_at', today())
                ->where('status', '!=', AppointmentStatus::Cancelled))
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        if ($doctors->isEmpty()) {
            return [];
        }

        // El último que pasó a consulta con cada doctor.
        $inConsultation = Appointment::query()
            ->whereDate('starts_at', today())
            ->where('status', AppointmentStatus::InProgress)
            ->with('patient')
            ->orderBy('started_at')
            ->get()
            ->keyBy('doctor_id');
        $waiting = Appointment::query()
            ->waitingNow()
            ->toBase()
            ->selectRaw('doctor_id, count(*) as waiting')
            ->groupBy('doctor_id')
            ->pluck('waiting', 'doctor_id');

        return $doctors->map(function (Doctor $doctor) use ($inConsultation, $waiting): array {
            /** @var Appointment|null $current */
            $current = $inConsultation->get($doctor->id);

            return [
                'name' => $doctor->name,
                'busy' => $current !== null,
                'status' => $current === null ? 'Libre' : $this->seeing($current),
                'waiting' => (int) ($waiting[$doctor->id] ?? 0),
            ];
        })->values()->all();
    }

    /** "Atendiendo a Rosa Quispe, hace 28 min": a quién tiene en consulta y desde cuándo, si se anotó. */
    private function seeing(Appointment $appointment): string
    {
        /** @var Patient $patient */
        $patient = $appointment->patient;
        /** @var CarbonInterface|null $startedAt */
        $startedAt = $appointment->started_at;

        return 'Atendiendo a '.$patient->full_name
            .($startedAt !== null ? ', hace '.Dates::duration((int) $startedAt->diffInMinutes(now())) : '');
    }
}
