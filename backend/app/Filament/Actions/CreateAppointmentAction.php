<?php

namespace App\Filament\Actions;

use App\Enums\PatientRelationship;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Filament\Support\Notify;
use App\Filament\Support\SlotPicker;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Booking\AppointmentService;
use App\Services\Clinical\TreatmentPlanService;
use App\Services\Patients\PatientRegistrar;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * "Nueva cita", siempre en una ventana: sobre la agenda llega con el doctor, el día y la hora que se tocaron, y
 * desde otra pantalla (la ficha, la lista de espera, Hoy), con lo que el enlace ya sabe del paciente. El paciente
 * nuevo se registra en la misma ventana.
 */
final class CreateAppointmentAction
{
    public static function make(string $name = 'createAppointment'): Action
    {
        return Action::make($name)
            ->label('Nueva cita')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Nueva cita')
            // Ancha, en dos columnas: elegir a la izquierda y el resumen a la derecha, sin una columna larga que bajar.
            ->modalWidth(Width::SixExtraLarge)
            ->extraModalWindowAttributes(['class' => 'appointment-window'])
            // "Agendar cita" siempre a la vista, aunque el formulario sea largo (paciente nuevo, repetir).
            ->stickyModalFooter()
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalSubmitActionLabel('Agendar cita')
            ->modalSubmitAction(fn (Action $action) => $action->size(Size::Large))
            ->visible(fn () => AppointmentResource::canCreate())
            ->schema(fn (Schema $schema) => AppointmentForm::configure($schema))
            ->fillForm(fn (array $arguments) => self::defaults($arguments))
            ->action(function (array $data, Action $action): void {
                $result = ['booked' => [], 'skipped' => []];

                Notify::run(
                    function () use ($data, &$result): void {
                        $result = self::bookAll($data);
                    },
                    'Cita creada',
                    $action,
                    function (Notification $notification) use ($data, &$result): Notification {
                        return $notification->title(self::createdMessage($data, $result))->actions(self::createdActions($result));
                    },
                );
            });
    }

    /**
     * Lo que se dice al crearla: si es un sobrecupo, cuántas quedaron si se repite (y qué fechas se saltaron por no
     * tener lugar) y si hay un aviso por enviar: solo se prepara si se pidió y la cita es de ahora en adelante.
     *
     * @param  array<string, mixed>  $data
     * @param  array{booked: list<Appointment>, skipped: list<CarbonImmutable>}  $result
     */
    public static function createdMessage(array $data, array $result): string
    {
        $first = $result['booked'][0];
        $count = count($result['booked']);
        $notified = (bool) ($data['notify'] ?? true);

        if ($first->starts_at->isPast()) {
            return 'Cita registrada en una hora que ya pasó. No se preparó aviso al paciente.';
        }

        $created = match (true) {
            $count > 1 => $count.' citas creadas, una cada '.((int) $data['repeat_every'] === 1 ? 'semana' : (int) $data['repeat_every'].' semanas').'.',
            $first->is_overbooked => 'Sobrecupo agendado.',
            default => 'Cita creada.',
        };

        $notice = match (true) {
            ! $notified => 'No se preparó aviso al paciente.',
            Patient::query()->whereKey($first->patient_id)->whereNull('account_id')->exists() => 'No tiene celular: no se preparó aviso al paciente.',
            self::noticeGoesToPhone($first) => 'El aviso le llegará a la pantalla de su celular.',
            $count > 1 => 'El aviso de la primera está listo en Avisos.',
            default => 'El aviso al paciente está listo en Avisos.',
        };

        $skipped = collect($result['skipped'])->map(fn (CarbonImmutable $date) => $date->translatedFormat('D j M'));

        return collect([
            $created,
            $notice,
            // "nov." ya trae su punto.
            $skipped->isNotEmpty() ? 'Sin lugar, no se agendó: '.rtrim($skipped->implode(' · '), '.').'.' : null,
        ])->filter()->implode(' ');
    }

    /**
     * Después de agendar, a un toque: abrir la cita y, si se preparó su aviso, enviarlo ya por WhatsApp (por ejemplo,
     * mientras el paciente sigue en el teléfono). Antes había que buscarla en la agenda o el aviso en Avisos.
     *
     * @param  array{booked: list<Appointment>, skipped: list<CarbonImmutable>}  $result
     * @return list<Action>
     */
    private static function createdActions(array $result): array
    {
        $appointment = $result['booked'][0];
        $noticeReady = ! self::noticeGoesToPhone($appointment)
            && WhatsappMessage::query()->where('appointment_id', $appointment->getKey())->pending()->exists();

        return array_values(array_filter([
            $noticeReady
                ? Action::make('sendNotice')
                    ->label('Enviar aviso ahora')
                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->color('success')
                    ->url(AppointmentResource::getUrl('view', ['record' => $appointment, 'action' => 'whatsapp']))
                    ->button()
                : null,
            Action::make('viewAppointment')
                ->label('Ver cita')
                ->url(AppointmentResource::getUrl('view', ['record' => $appointment]))
                ->link(),
        ]));
    }

    /** El paciente pidió los avisos solo en su celular: el de esta cita sale allí solo, sin pasar por Avisos. */
    private static function noticeGoesToPhone(Appointment $appointment): bool
    {
        return WhatsappMessage::query()->where('appointment_id', $appointment->getKey())->pending()->onlyOnPhone()->exists();
    }

    /**
     * Crea la cita y devuelve la primera (la única, si no se repite).
     *
     * @param  array<string, mixed>  $data
     * @param  bool  $walkIn  Llegó sin cita: toma el primer hueco de hoy y queda en sala de una vez.
     */
    public static function book(array $data, bool $walkIn = false): Appointment
    {
        return self::bookAll($data, $walkIn)['booked'][0];
    }

    /**
     * Crea la cita con la misma lógica que la web: nunca se cruzan dos citas, salvo el sobrecupo que se marcó a
     * propósito. Si el paciente es nuevo, se registra en la misma transacción (si el horario ya se ocupó, no queda
     * un paciente suelto). Con "Repetir" se agendan también las fechas siguientes.
     *
     * @param  array<string, mixed>  $data
     * @param  bool  $walkIn  Llegó sin cita: toma el primer hueco de hoy y queda en sala de una vez.
     * @return array{booked: list<Appointment>, skipped: list<CarbonImmutable>}
     */
    public static function bookAll(array $data, bool $walkIn = false): array
    {
        return DB::transaction(function () use ($data, $walkIn) {
            $patient = ($data['new_patient'] ?? false)
                ? app(PatientRegistrar::class)->register(array_filter($data['patient'] ?? [], fn ($value) => $value !== null && $value !== ''))
                : Patient::findOrFail($data['patient_id']);

            $planItem = filled($data['treatment_plan_item_id'] ?? null)
                ? app(TreatmentPlanService::class)->workableItems($patient)->firstWhere('id', (int) $data['treatment_plan_item_id'])
                : null;

            $doctor = Doctor::findOrFail($data['doctor_id']);
            $treatment = Treatment::findOrFail($data['treatment_id']);
            $options = [
                'origin' => $data['origin'] ?? null,
                'fixed_date' => (bool) ($data['fixed_date'] ?? false),
                'internal_notes' => $data['internal_notes'] ?? null,
                'treatment_plan_item_id' => $planItem?->getKey(),
            ];
            $service = app(AppointmentService::class);

            if ($walkIn) {
                $result = ['booked' => [$service->walkIn(auth()->user(), $patient, $doctor, $treatment, $options)], 'skipped' => []];
            } else {
                $options += [
                    'duration_minutes' => filled($data['duration_minutes'] ?? null) ? (int) $data['duration_minutes'] : null,
                    'notify' => (bool) ($data['notify'] ?? true),
                    'overbook' => (bool) ($data['overbook'] ?? false),
                ];

                $result = ($data['repeat'] ?? false)
                    ? $service->bookSeries(auth()->user(), $patient, $doctor, $treatment, SlotPicker::startsAt($data), (int) $data['repeat_every'], (int) $data['repeat_times'], $options)
                    : ['booked' => [$service->bookFromStaff(auth()->user(), $patient, $doctor, $treatment, SlotPicker::startsAt($data), $options)], 'skipped' => []];
            }

            // Agendar una sesión no es aceptar el plan: solo si recepción marcó que el paciente lo aceptó.
            // Si no, al menos ya se le presentó.
            if ($planItem) {
                ($data['plan_accepted'] ?? false)
                    ? app(TreatmentPlanService::class)->accept($planItem->plan)
                    : app(TreatmentPlanService::class)->markProposed($planItem->plan);
            }

            return $result;
        });
    }

    /**
     * Lo que llega puesto: el doctor (el propio, el que se tocó o el único), el día y la hora. Desde "Buscar hueco"
     * llega también el tratamiento, y entonces la hora ya queda elegida. Desde otra pantalla llega el paciente (y la
     * sesión de su plan): sin un día pedido, se propone el próximo en que suele venir.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public static function defaults(array $arguments): array
    {
        /** @var User $user */
        $user = auth()->user();
        $date = (string) ($arguments['date'] ?? '');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && CarbonImmutable::parse($date)->gte(today()) ? $date : null;
        $time = (string) ($arguments['time'] ?? '');
        $activeDoctors = Doctor::query()->active()->pluck('id');

        $doctorId = $user->managesAllAgendas()
            ? ($activeDoctors->contains((int) ($arguments['doctor'] ?? 0)) ? (int) $arguments['doctor'] : null)
            : $user->doctorId();
        $treatmentId = Treatment::query()->active()->whereKey((int) ($arguments['treatment'] ?? 0))->value('id');
        $time = preg_match('/^\d{2}:\d{2}$/', $time) ? $time : null;
        $patient = filled($arguments['patient'] ?? null) ? Patient::find((int) $arguments['patient']) : null;
        $planItemId = (int) ($arguments['plan_item'] ?? 0);
        // Con varios doctores, el que atendió al paciente la última vez.
        $doctorId ??= $activeDoctors->count() === 1 ? $activeDoctors->first() : SlotPicker::lastDoctorId($patient, $treatmentId);
        // Un día pedido a propósito (otro que hoy) se respeta: no se cambia por el día en que suele venir el paciente.
        $dateFromAgenda = $date !== today()->toDateString() ? $date : null;
        $day = $date ?? (SlotPicker::nextUsualDay($patient) ?? today())->toDateString();

        // Si ese día ya no tiene lugar (a la noche, o está lleno), se abre en el siguiente que sí.
        if ($dateFromAgenda === null && $time === null && $treatmentId && $doctorId) {
            $day = SlotPicker::firstDayWithSlots(Doctor::findOrFail($doctorId), Treatment::findOrFail($treatmentId), CarbonImmutable::parse($day), $patient?->getKey())?->toDateString() ?? $day;
        }

        return [
            'new_patient' => false,
            'patient_id' => $patient?->getKey(),
            'patient' => ['relationship' => PatientRelationship::Self->value],
            'treatment_plan_item_id' => array_key_exists($planItemId, AppointmentForm::planItemOptions($patient?->getKey())) ? $planItemId : null,
            'treatment_id' => $treatmentId,
            'doctor_id' => $doctorId,
            'date' => $day,
            'date_from_agenda' => $dateFromAgenda,
            'preferred_time' => $time,
            'slot' => $treatmentId && $doctorId && $time ? $time : null,
            'manual' => false,
            'fixed_date' => false,
            'notify' => true,
            'repeat' => false,
            'repeat_every' => 4,
            'repeat_times' => 6,
        ];
    }
}
