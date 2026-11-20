<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Filament\Actions\AppointmentActions;
use App\Filament\Concerns\RefreshesWithToday;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\LabOrders\LabOrderResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Support\ClinicalAlerts;
use App\Filament\Support\VisitFlags;
use App\Models\Appointment;
use App\Models\LabOrder;
use App\Models\User;
use App\Services\Clinical\ConsentService;
use App\Services\Dashboard\DoctorDay;
use App\Support\Dates;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;

/**
 * Para quien atiende: su próximo paciente con lo que tiene que ver antes de llamarlo (alergias, enfermedades,
 * embarazo, si su trabajo de laboratorio ya llegó, qué se le hizo la última vez), "Atender" y quién viene después.
 * Las atenciones que dejó abiertas van en su propia tarjeta, al costado (OpenConsultations).
 */
class NextPatient extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use RefreshesWithToday;

    /** Tu próximo paciente se ve apenas abre "Hoy", sin un recuadro vacío mientras carga. */
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.next-patient';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user?->doctorId() === null || ! $user->canSeeClinical()) {
            return false;
        }

        // El administrador que además atiende lo ve los días que tiene citas; los demás días, "Hoy" es de la clínica.
        return ! $user->managesAllAgendas() || (new DoctorDay($user->doctorId()))->hasAppointmentsToday();
    }

    /** El mismo "Atender" de la lista: abre la atención con su plantilla y odontograma. */
    public function startAction(): Action
    {
        return AppointmentActions::start()
            ->record(fn (array $arguments) => AppointmentResource::getEloquentQuery()->find($arguments['appointment'] ?? null));
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $day = new DoctorDay($user->doctorId());
        $upcoming = $day->upcoming();
        $next = $upcoming->get(0);
        $after = $upcoming->get(1);
        $waiting = $day->waiting();

        return [
            'next' => $next ? $this->nextData($day, $next) : null,
            'after' => $after ? $this->afterData($after) : null,
            'othersWaiting' => max(0, $waiting - ($next?->status === AppointmentStatus::CheckedIn ? 1 : 0)),
        ];
    }

    /** @return array<string, mixed> */
    private function nextData(DoctorDay $day, Appointment $appointment): array
    {
        $patient = $appointment->patient;
        $last = $day->lastVisit($appointment);
        $waiting = $appointment->waitingMinutes();
        // Minutos desde su hora, si ya pasó y todavía no llega (con 5 de tolerancia).
        $late = (int) $appointment->starts_at->diffInMinutes(now());
        $late = $waiting === null && $late >= 5 ? $late : null;

        return [
            'id' => $appointment->getKey(),
            ...$this->summary($appointment),
            'age' => $patient->age !== null ? $patient->age.($patient->age === 1 ? ' año' : ' años') : null,
            'state' => match (true) {
                $waiting !== null => 'Llegó hace '.Dates::duration($waiting),
                $late !== null => 'Va '.Dates::duration($late).' tarde',
                $appointment->status === AppointmentStatus::Confirmed => 'Confirmó, aún no llega',
                default => 'Aún no confirma',
            },
            // Color solo para lo que pide atención: alguien esperando o que se atrasa. Lo demás, en gris.
            'stateColor' => match (true) {
                $waiting !== null => $waiting >= TodayAppointments::LONG_WAIT_MINUTES ? 'danger' : 'warning',
                $late !== null => 'warning',
                default => 'gray',
            },
            'hasBackground' => $patient->medicalBackground !== null,
            'alerts' => ClinicalAlerts::withSeverity($patient),
            // Si el tratamiento pide consentimiento y aún no lo firmó, se firma antes de empezar.
            'consentMissing' => app(ConsentService::class)->signedForVisit($appointment) === false,
            // Si espera un trabajo de laboratorio: si ya llegó o no, antes de llamarlo (para colocar una corona, por ejemplo).
            'lab' => LabOrderResource::getEloquentQuery()->forVisitOf($patient->id)->get()
                ->map(fn (LabOrder $order): ?array => $order->beforeVisit())
                ->filter()
                ->values()
                ->all(),
            'last' => $last ? $last->treatment->name.' · '.$last->starts_at->translatedFormat('j M Y') : null,
            'notes' => $appointment->patient_note,
            // Lo que recepción (o el mismo doctor) anotó al agendar: "viene con dolor", "trae su radiografía".
            'internalNotes' => $appointment->internal_notes,
            'historyUrl' => PatientResource::getUrl('clinical', ['record' => $patient]),
        ];
    }

    /**
     * Quién viene después, para saberlo sin bajar a la agenda: "11:30 Ana Silva · Evaluación · ya está en sala".
     *
     * @return array{time: string, text: string}
     */
    private function afterData(Appointment $appointment): array
    {
        $summary = $this->summary($appointment);

        return [
            'time' => $summary['time'],
            'text' => collect([
                $summary['name'],
                $summary['reason'],
                $appointment->waitingMinutes() !== null ? 'ya está en sala' : null,
            ])->filter()->implode(' · '),
        ];
    }

    /**
     * La hora, el nombre y a qué viene: lo que dicen de cada uno "Tu próximo paciente" y su "Después".
     *
     * @return array{time: string, name: string, reason: string}
     */
    private function summary(Appointment $appointment): array
    {
        return [
            'time' => $appointment->starts_at->format('H:i'),
            'name' => $appointment->patient->full_name,
            'reason' => collect([
                $appointment->planItem?->label() ?? $appointment->treatment->name,
                mb_strtolower(VisitFlags::session($appointment->planItem) ?? ''),
            ])->filter()->implode(' · '),
        ];
    }
}
