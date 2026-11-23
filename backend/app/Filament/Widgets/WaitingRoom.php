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
 * más espera a quien acaba de llegar. La espera va en rojo con Appointment::LONG_WAIT_MINUTES de demora, que
 * se cuenta desde su hora: quien llegó temprano espera sin estar demorado. Es de quien ve todas las agendas; el
 * doctor ve a quien lo espera en "Tu próximo paciente". Quien quedó "en sala" horas después de su cita ya no
 * sale: es una cita sin cerrar. Se actualiza sola cada 30 s.
 */
class WaitingRoom extends Widget
{
    use RefreshesWithToday;

    /** Adónde lleva "En sala ahora", el número de arriba. */
    public const ANCHOR = 'sala-de-espera';

    /** Minutos después del fin de su cita en que "Atendiendo a…" pregunta si ya terminó: quizá nadie lo marcó. */
    public const OVERRUN_MINUTES = 30;

    /** Minutos antes de su hora desde los que se dice "llegó temprano": por eso espera sin estar demorado. */
    private const EARLY_MINUTES = 10;

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
     * Quienes están en sala ahora, con lo justo para saber cuánto llevan y si ya están demorados: lo que cuenta
     * "En sala ahora". Como esta tarjeta, es de quien ve todas las agendas: cuenta a todos.
     *
     * @return Collection<int, Appointment>
     */
    public static function waiting(): Collection
    {
        return Appointment::query()
            ->waitingNow()
            ->get(['id', 'status', 'starts_at', 'checked_in_at'])
            ->values()
            ->toBase();
    }

    /** @return array{rooms: list<array{name: string, busy: bool, status: string, overrun: bool, url: string|null, waiting: int}>, patients: list<array{name: string, detail: string, waited: string, long: bool, url: string}>} */
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
                self::arrivedEarly($appointment) ? 'llegó temprano' : null,
                $severalDoctors ? $appointment->doctor?->name : null,
            ])->filter()->implode(' · '),
            'waited' => Dates::duration((int) $appointment->waitingMinutes()),
            'long' => $appointment->waitsTooLong(),
            'url' => AppointmentResource::getUrl('view', ['record' => $appointment]),
        ])->all()];
    }

    /**
     * Los doctores con citas hoy, en su orden: "Atendiendo a Rosa Quispe, hace 28 min" o "Libre", y cuántos lo
     * esperan en sala. Si tiene a alguien en consulta desde antes y nadie lo cerró, se ve aquí también; cuando su
     * cita terminó hace más de OVERRUN_MINUTES, se pregunta si ya terminó y lleva a la cita para cerrarla.
     *
     * @return list<array{name: string, busy: bool, status: string, overrun: bool, url: string|null, waiting: int}>
     */
    private function rooms(): array
    {
        $doctors = Doctor::query()
            ->whereIn('id', Appointment::query()->startsOn(today())->where('status', '!=', AppointmentStatus::Cancelled)->select('doctor_id'))
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        if ($doctors->isEmpty()) {
            return [];
        }

        // El último que pasó a consulta con cada doctor.
        $inConsultation = Appointment::query()
            ->startsOn(today())
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

        return $doctors->map(fn (Doctor $doctor): array => $this->room($doctor, $inConsultation->get($doctor->id), (int) ($waiting[$doctor->id] ?? 0)))
            ->values()
            ->all();
    }

    /** @return array{name: string, busy: bool, status: string, overrun: bool, url: string|null, waiting: int} */
    private function room(Doctor $doctor, ?Appointment $current, int $waiting): array
    {
        $overrun = $current !== null && self::overran($current);

        return [
            'name' => $doctor->name,
            'busy' => $current !== null,
            'status' => $current === null ? 'Libre' : $this->seeing($current, $overrun),
            'overrun' => $overrun,
            'url' => $overrun ? AppointmentResource::getUrl('view', ['record' => $current]) : null,
            'waiting' => $waiting,
        ];
    }

    /**
     * "Atendiendo a Rosa Quispe, hace 28 min": a quién tiene en consulta y desde cuándo, si se anotó. Si su cita
     * terminó hace rato, agrega "su cita era hasta las 09:30, ¿ya terminó?".
     */
    private function seeing(Appointment $appointment, bool $overrun): string
    {
        /** @var Patient $patient */
        $patient = $appointment->patient;
        /** @var CarbonInterface|null $startedAt */
        $startedAt = $appointment->started_at;
        /** @var CarbonInterface $endsAt */
        $endsAt = $appointment->ends_at;

        return 'Atendiendo a '.$patient->full_name
            .($startedAt !== null ? ', hace '.Dates::duration((int) $startedAt->diffInMinutes(now())) : '')
            .($overrun ? ' · su cita era hasta las '.$endsAt->format('H:i').', ¿ya terminó?' : '');
    }

    /** Sigue "en atención" más de OVERRUN_MINUTES después del fin de su cita: quizá nadie marcó que terminó. */
    private static function overran(Appointment $appointment): bool
    {
        /** @var CarbonInterface $endsAt */
        $endsAt = $appointment->ends_at;

        return $endsAt->lt(now()->subMinutes(self::OVERRUN_MINUTES));
    }

    /** Llegó EARLY_MINUTES o más antes de su hora: por eso puede llevar rato en sala sin estar demorado. */
    private static function arrivedEarly(Appointment $appointment): bool
    {
        /** @var CarbonInterface|null $arrivedAt */
        $arrivedAt = $appointment->checked_in_at;
        /** @var CarbonInterface $startsAt */
        $startsAt = $appointment->starts_at;

        return $arrivedAt !== null && $arrivedAt->lte($startsAt->copy()->subMinutes(self::EARLY_MINUTES));
    }
}
