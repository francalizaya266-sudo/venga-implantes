<?php

namespace App\Filament\Actions;

use App\Enums\AppointmentStatus;
use App\Enums\PatientRelationship;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Support\ArrivalChecklist;
use App\Filament\Support\Notify;
use App\Filament\Support\SlotPicker;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Agenda\Slot;
use App\Services\Booking\AppointmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * "Llegó sin cita": el paciente está en el mostrador y no tiene cita. En una sola ventana se elige (o se
 * registra) al paciente y qué viene a hacerse; el sistema le da el primer hueco de hoy y lo deja en sala.
 * Si resulta que sí tenía cita hoy, se dice ahí mismo y lo normal es marcar que llegó a esa, en vez de abrirle otra.
 */
final class WalkInAction
{
    public static function make(): Action
    {
        return Action::make('walkIn')
            ->label('Llegó sin cita')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('gray')
            ->modalHeading('Llegó sin cita')
            ->modalDescription('Se le da el primer hueco de hoy y queda en sala de una vez.')
            // Como "Nueva cita": a la izquierda quién y qué; a la derecha lo que conviene ver antes de pasarlo.
            ->modalWidth(Width::FiveExtraLarge)
            ->extraModalWindowAttributes(['class' => 'appointment-window'])
            ->stickyModalFooter()
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalSubmitActionLabel('Pasar a sala')
            // Si ya está en sala o lo atienden, el botón dice que abre otra atención; sin hueco hoy, no se ofrece.
            ->modalSubmitAction(fn (Action $action, array $mountedActions) => $action
                ->size(Size::Large)
                ->label(fn () => self::opensAnotherVisit(self::windowState($mountedActions)) ? 'Abrir otra atención' : 'Pasar a sala')
                ->disabled(fn () => self::hasNoRoom(self::windowState($mountedActions))))
            ->schema([
                Grid::make(['default' => 1, 'lg' => 5])
                    ->schema([
                        Group::make([
                            AppointmentForm::patientSection(forToday: true, notices: [self::todaysAppointmentCallout()]),
                            Section::make('Atención')
                                ->columns(2)
                                ->hidden(fn (Get $get) => self::arrivesToAppointment($get))
                                ->schema([
                                    // Al elegir qué se hace, queda puesto quien se desocupa primero.
                                    SlotPicker::treatmentSelect()
                                        ->afterStateUpdated(fn (Get $get, Set $set) => $set('doctor_id', self::soonestDoctorId($get) ?? $get('doctor_id'))),
                                    SlotPicker::doctorSelect(),
                                    Callout::make(fn (Get $get) => self::roomToday($get))
                                        ->key('roomToday')
                                        ->description(fn (Get $get) => self::slot($get) ? null : self::otherwise())
                                        ->status(fn (Get $get) => self::slot($get) ? 'success' : 'danger')
                                        ->visible(fn (Get $get) => filled($get('treatment_id')) && filled($get('doctor_id')))
                                        ->columnSpanFull(),
                                ]),
                        ])->columnSpan(['lg' => 3]),
                        self::beforeGoingInSection()->columnSpan(['lg' => 2]),
                    ]),
            ])
            ->fillForm(fn () => [
                'new_patient' => false,
                'patient' => ['relationship' => PatientRelationship::Self->value],
                'use_appointment' => true,
                'doctor_id' => SlotPicker::defaultDoctorId(),
            ])
            ->action(function (array $data, Action $action) {
                $appointment = ($data['use_appointment'] ?? false) ? self::todaysAppointmentOf($data['patient_id'] ?? null) : null;

                if ($appointment?->status->isChangeable()) {
                    Notify::run(
                        function () use ($appointment, $data): void {
                            self::addNote($appointment, $data['internal_notes'] ?? null);
                            app(AppointmentService::class)->markArrived($appointment, auth()->user());
                        },
                        'Paciente en sala',
                        $action,
                        fn (Notification $notification) => ArrivalChecklist::addTo($notification, $appointment, auth()->user(), self::arrivalHeadline($appointment)),
                    );

                    return;
                }

                $appointment = null;

                Notify::run(
                    function () use ($data, &$appointment) {
                        $appointment = CreateAppointmentAction::book($data, walkIn: true);
                    },
                    'Paciente en sala',
                    $action,
                    function (Notification $notification) use (&$appointment) {
                        return ArrivalChecklist::addTo($notification, $appointment, auth()->user(), self::headline($appointment));
                    },
                );
            });
    }

    /** La nota de recepción, debajo de las que la cita ya tenía (no las reemplaza). */
    private static function addNote(Appointment $appointment, ?string $note): void
    {
        if (blank($note)) {
            return;
        }

        $appointment->update(['internal_notes' => collect([$appointment->internal_notes, trim($note)])->filter(fn (?string $line) => filled($line))->implode("\n")]);
    }

    /** "Rosa Quispe pasa con Dra. Elena Ríos a las 11:30." */
    private static function headline(Appointment $appointment): string
    {
        return $appointment->patient->full_name.' pasa con '.$appointment->doctor->name.' a las '.$appointment->starts_at->format('H:i').'.';
    }

    /** "Rosa Quispe llegó a su cita de las 11:30 con Dra. Elena Ríos." */
    private static function arrivalHeadline(Appointment $appointment): string
    {
        return $appointment->patient->full_name.' llegó a su cita de las '.$appointment->starts_at->format('H:i').' con '.$appointment->doctor->name.'.';
    }

    /** "Extracción simple con Dra. Elena Ríos" (el doctor, solo si hay más de uno). */
    private static function describe(Appointment $appointment): string
    {
        return $appointment->treatment->name.(SlotPicker::onlyActiveDoctorId() === null ? ' con '.$appointment->doctor->name : '');
    }

    /**
     * Según en qué va su cita de hoy: ya lo están atendiendo, ya espera en sala, ya pasó su hora o todavía no le toca.
     */
    private static function todaysAppointmentHeading(Get $get): ?string
    {
        $appointment = self::todaysAppointment($get);

        return match (true) {
            $appointment === null => null,
            $appointment->status === AppointmentStatus::InProgress => 'Lo están atendiendo desde las '.($appointment->started_at ?? $appointment->starts_at)->format('H:i'),
            $appointment->status === AppointmentStatus::CheckedIn => 'Ya está en sala desde las '.($appointment->checked_in_at ?? $appointment->starts_at)->format('H:i'),
            $appointment->starts_at->isPast() => 'Tenía cita hoy a las '.$appointment->starts_at->format('H:i'),
            default => 'Tiene cita hoy a las '.$appointment->starts_at->format('H:i'),
        };
    }

    /** ¿Se va a marcar que llegó a la cita que ya tenía? Entonces no se elige tratamiento ni doctor. */
    private static function arrivesToAppointment(Get $get): bool
    {
        return (bool) $get('use_appointment') && (self::todaysAppointment($get)?->status->isChangeable() ?? false);
    }

    /** La cita de hoy del paciente elegido que sigue sin cerrarse, si la hay. Un paciente nuevo no tiene. */
    private static function todaysAppointment(Get $get): ?Appointment
    {
        return $get('new_patient') ? null : self::todaysAppointmentOf($get('patient_id'));
    }

    /**
     * La primera cita de hoy del paciente que todavía espera un cierre, entre las que quien mira puede manejar
     * (el doctor, solo las suyas). Se pide varias veces al pintar la ventana: se busca una sola vez por pedido.
     */
    private static function todaysAppointmentOf(mixed $patientId): ?Appointment
    {
        if (blank($patientId)) {
            return null;
        }

        $memo = 'walk-in.todays-appointment.'.$patientId;

        if (! request()->attributes->has($memo)) {
            request()->attributes->set($memo, AppointmentResource::getEloquentQuery()
                ->where('patient_id', $patientId)
                ->whereDate('starts_at', today())
                ->whereIn('status', AppointmentStatus::open())
                ->orderBy('starts_at')
                ->first());
        }

        return request()->attributes->get($memo);
    }

    /** Lo que se le dice al paciente antes de pasarlo a sala. */
    private static function roomToday(Get $get): string
    {
        $slot = self::slot($get);

        return $slot
            ? 'Lo puede atender hoy a las '.$slot->label().'.'
            : 'Ya no tiene un hueco hoy para ese tratamiento.';
    }

    /** Sin hueco: con un solo doctor no hay a quién más pedírselo, queda agendarlo otro día. */
    private static function otherwise(): string
    {
        return SlotPicker::onlyActiveDoctorId() === null
            ? 'Elige otro doctor, o agéndalo con "Nueva cita" para otro día.'
            : 'Agéndalo con "Nueva cita" para otro día.';
    }

    private static function slot(Get $get): ?Slot
    {
        return self::slotFor($get('doctor_id'), $get('treatment_id'), $get('patient_id'));
    }

    /**
     * ¿Va a abrirle una atención y no hay hueco hoy?
     *
     * @param  array<string, mixed>  $state
     */
    private static function hasNoRoom(array $state): bool
    {
        $patientId = ($state['new_patient'] ?? false) ? null : ($state['patient_id'] ?? null);

        if (($state['use_appointment'] ?? false) && (self::todaysAppointmentOf($patientId)?->status->isChangeable() ?? false)) {
            return false;
        }

        return filled($state['doctor_id'] ?? null)
            && filled($state['treatment_id'] ?? null)
            && self::slotFor($state['doctor_id'], $state['treatment_id'], $patientId) === null;
    }

    /**
     * Lo escrito en la ventana, para el botón de abajo, que no ve los campos (aunque haya otra ventana abierta encima).
     *
     * @param  array<Action>  $mountedActions
     * @return array<string, mixed>
     */
    private static function windowState(array $mountedActions): array
    {
        return collect($mountedActions)->first(fn (Action $mounted) => $mounted->getName() === 'walkIn')?->getRawData() ?? [];
    }

    /**
     * ¿El paciente ya está en sala o lo están atendiendo? Entonces lo que se crea es una atención más.
     *
     * @param  array<string, mixed>  $state
     */
    private static function opensAnotherVisit(array $state): bool
    {
        $appointment = ($state['new_patient'] ?? false) ? null : self::todaysAppointmentOf($state['patient_id'] ?? null);

        return $appointment !== null && ! $appointment->status->isChangeable();
    }

    /** El primer hueco de hoy. Se pide varias veces al pintar la ventana: se calcula una sola vez por pedido. */
    private static function slotFor(mixed $doctorId, mixed $treatmentId, mixed $patientId): ?Slot
    {
        if (blank($doctorId) || blank($treatmentId)) {
            return null;
        }

        $memo = 'walk-in.slot.'.$doctorId.'.'.$treatmentId.'.'.$patientId;

        if (! request()->attributes->has($memo)) {
            $doctor = Doctor::find($doctorId);
            $treatment = Treatment::find($treatmentId);

            request()->attributes->set($memo, $doctor && $treatment
                ? app(AppointmentService::class)->firstRoomToday($doctor, $treatment, filled($patientId) ? (int) $patientId : null)
                : null);
        }

        return request()->attributes->get($memo);
    }

    /**
     * "Ya tiene cita hoy": en ámbar, para no abrirle otra sin querer. Si todavía no llega a esa cita, lo normal es
     * marcar que llegó (viene marcado); si ya está en sala o lo están atendiendo, solo se avisa que sería otra más.
     */
    private static function todaysAppointmentCallout(): Callout
    {
        return Callout::make(fn (Get $get) => self::todaysAppointmentHeading($get))
            ->key('todaysAppointment')
            ->description(fn (Get $get) => self::todaysAppointmentDescription($get))
            ->warning()
            ->footer([
                Toggle::make('use_appointment')
                    ->label('Marcar que llegó a esa cita')
                    ->helperText('Así esa cita no queda como falta ni se le abre otra. Desmárcalo solo si viene por otra atención aparte.')
                    ->visible(fn (Get $get) => self::todaysAppointment($get)?->status->isChangeable() ?? false)
                    ->live(),
            ])
            ->visible(fn (Get $get) => self::todaysAppointment($get) !== null);
    }

    private static function todaysAppointmentDescription(Get $get): ?HtmlString
    {
        $appointment = self::todaysAppointment($get);

        if (! $appointment) {
            return null;
        }

        $advice = match (true) {
            ! $appointment->status->isChangeable() => 'Sigue solo si viene por otra atención aparte: se le abrirá una cita más.',
            $appointment->starts_at->isFuture() => 'Llegó antes de su hora.',
            default => null,
        };

        return new HtmlString(e(self::describe($appointment).' · '.$appointment->status->getLabel()).($advice ? '<br>'.e($advice) : ''));
    }

    /**
     * "Antes de pasar": lo que le falta al paciente (datos, antecedentes, lo que debe, su trabajo de laboratorio),
     * ahora que todavía está en el mostrador y no recién en el aviso de después. Y la nota para el doctor.
     */
    private static function beforeGoingInSection(): Section
    {
        return Section::make('Antes de pasar')
            ->extraAttributes(['class' => 'appointment-summary'])
            ->schema([
                Text::make(fn (Get $get) => self::nothingPendingText($get))
                    ->color('gray')
                    ->visible(fn (Get $get) => self::pending($get) === []),
                Callout::make()
                    ->key('pendingBeforeGoingIn')
                    ->description(fn (Get $get) => new HtmlString('<ul class="consult-risks">'.collect(self::pending($get))->map(fn (string $item) => '<li>'.e($item).'</li>')->implode('').'</ul>'))
                    ->warning()
                    ->actions([
                        Action::make('openPatientFile')
                            ->label(fn (Get $get) => self::canUpdatePatient($get) ? 'Completar su ficha' : 'Ver su ficha')
                            ->url(fn (Get $get) => filled($get('patient_id')) ? PatientResource::getUrl(self::canUpdatePatient($get) ? 'edit' : 'view', ['record' => $get('patient_id')]) : null)
                            ->openUrlInNewTab(),
                    ])
                    ->visible(fn (Get $get) => self::pending($get) !== []),
                // Quien llega sin cita suele venir con algo urgente: el doctor lo ve en "Tu próximo paciente".
                Textarea::make('internal_notes')
                    ->label('Nota para el doctor')
                    ->placeholder('Viene con dolor en una muela…')
                    ->helperText('No la ve el paciente. Si llegó a una cita que ya tenía, se agrega a sus notas.')
                    ->rows(3),
            ]);
    }

    /** Cuando no hay nada que resolver, se dice por qué. */
    private static function nothingPendingText(Get $get): string
    {
        return match (true) {
            (bool) $get('new_patient') => 'Es paciente nuevo: al pasarlo a sala verás qué datos le faltan para completar su ficha.',
            blank($get('patient_id')) => 'Elige al paciente: aquí verás si le falta algo (datos, antecedentes, lo que debe) antes de pasar.',
            default => 'No le falta nada: puede pasar.',
        };
    }

    /**
     * Lo pendiente del paciente elegido; vacío si es nuevo o todavía no se eligió.
     *
     * @return list<string>
     */
    private static function pending(Get $get): array
    {
        $patient = self::chosenPatient($get);

        /** @var User $user */
        $user = auth()->user();

        // A quien nunca vino, la ventana ya le pregunta cómo nos encontró.
        return $patient ? ArrivalChecklist::for($patient, $user, originAskedAlready: $patient->first_visit_at === null) : [];
    }

    private static function canUpdatePatient(Get $get): bool
    {
        $patient = self::chosenPatient($get);

        return $patient !== null && (auth()->user()?->can('update', $patient) ?? false);
    }

    /** El paciente ya registrado que se eligió, con lo que pide la lista de pendientes. Una sola vez por pedido. */
    private static function chosenPatient(Get $get): ?Patient
    {
        $patientId = $get('patient_id');

        if ($get('new_patient') || blank($patientId)) {
            return null;
        }

        $memo = 'walk-in.patient.'.$patientId;

        if (! request()->attributes->has($memo)) {
            request()->attributes->set($memo, Patient::query()->with(['account', 'medicalBackground'])->find($patientId));
        }

        return request()->attributes->get($memo);
    }

    /** De quienes hacen ese tratamiento, quien tiene el hueco más cercano hoy. Null si el doctor ya está elegido o nadie tiene. */
    private static function soonestDoctorId(Get $get): ?int
    {
        $treatment = Treatment::find($get('treatment_id'));

        if (! $treatment || filled($get('doctor_id'))) {
            return null;
        }

        return Doctor::query()
            ->active()
            ->whereHas('treatments', fn ($query) => $query->whereKey($treatment->getKey()))
            ->get()
            ->map(fn (Doctor $doctor) => ['id' => $doctor->getKey(), 'slot' => app(AppointmentService::class)->firstRoomToday($doctor, $treatment)])
            ->filter(fn (array $option) => $option['slot'] !== null)
            ->sortBy(fn (array $option) => $option['slot']->startsAt->getTimestamp())
            ->first()['id'] ?? null;
    }
}
