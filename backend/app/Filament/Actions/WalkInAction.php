<?php

namespace App\Filament\Actions;

use App\Enums\AppointmentStatus;
use App\Enums\PatientRelationship;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\WaitlistEntries\WaitlistEntryActions;
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
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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
                            // Si llegó a su cita, esa ya dice qué sesión de su plan es: no se pregunta.
                            AppointmentForm::patientSection(
                                forToday: true,
                                notices: [self::todaysAppointmentCallout()],
                                hidePlanWhen: fn (Get $get) => self::arrivesToAppointment($get),
                                // Lo de la cita de hoy era del que estaba elegido: con otro paciente vuelve a lo normal.
                                onPatientChange: function (Set $set): void {
                                    $set('use_appointment', true);
                                    $set('advance', false);
                                    $set('advance_to', null);
                                },
                            ),
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
                                        ->description(fn (Get $get) => self::slot($get) ? null : self::otherwise($get))
                                        ->status(fn (Get $get) => self::slot($get) ? 'success' : 'danger')
                                        // Sin hueco hoy, lo que se hace con él a un toque, con lo ya escrito.
                                        ->actions([self::bookAnotherDayAction(), self::waitForTodayAction()])
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
                // Recién leída: la de la lista de hoy viene con las demás y no carga lo que el aviso al doctor necesita.
                $appointment = ($data['use_appointment'] ?? false) ? self::todaysAppointmentOf($data['patient_id'] ?? null)?->fresh() : null;

                if ($appointment !== null && self::canMarkArrival($appointment)) {
                    $bookedAt = self::startsAt($appointment);
                    $earlier = ($data['advance'] ?? false) && filled($data['advance_to'] ?? null) ? today()->setTimeFromTimeString($data['advance_to']) : null;

                    Notify::run(
                        function () use (&$appointment, $data, $earlier): void {
                            self::addNote($appointment, $data['internal_notes'] ?? null);
                            self::saveOrigin($appointment, $data['origin'] ?? null);
                            $appointment = $earlier
                                ? app(AppointmentService::class)->arriveEarlier($appointment, $earlier, auth()->user())
                                : tap($appointment, fn (Appointment $arriving) => app(AppointmentService::class)->markArrived($arriving, auth()->user()));
                        },
                        'Paciente en sala',
                        $action,
                        function (Notification $notification) use (&$appointment, $bookedAt) {
                            return ArrivalChecklist::addTo($notification, $appointment, auth()->user(), self::arrivalHeadline($appointment, $bookedAt));
                        },
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

    /**
     * "¿Cómo nos encontró?", si se preguntó al marcar que llegó a su cita: queda en la cita y en su ficha, como al
     * agendar. Lo que ya tenían no se cambia.
     */
    private static function saveOrigin(Appointment $appointment, mixed $origin): void
    {
        if (blank($origin)) {
            return;
        }

        if ($appointment->origin === null) {
            $appointment->update(['origin' => $origin]);
        }

        $patient = Patient::query()->whereKey($appointment->patient_id)->whereNull('origin')->first();
        $patient?->update(['origin' => $origin]);
    }

    /** "Rosa Quispe pasa con Dra. Elena Ríos a las 11:30." */
    private static function headline(Appointment $appointment): string
    {
        return $appointment->patient->full_name.' pasa con '.$appointment->doctor->name.' a las '.self::startsAt($appointment)->format('H:i').'.';
    }

    /**
     * "Rosa Quispe llegó a su cita de las 11:30 con Dra. Elena Ríos.", o si pasó antes: "Rosa Quispe llegó antes: su
     * cita pasó de las 11:30 a las 10:45 con Dra. Elena Ríos."
     *
     * @param  CarbonInterface  $bookedAt  La hora que tenía antes de llegar.
     */
    private static function arrivalHeadline(Appointment $appointment, CarbonInterface $bookedAt): string
    {
        if (! self::startsAt($appointment)->eq($bookedAt)) {
            return ArrivalChecklist::advancedHeadline($appointment, $bookedAt);
        }

        return $appointment->patient->full_name.' llegó a su cita de las '.self::startsAt($appointment)->format('H:i').' con '.$appointment->doctor->name.'.';
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

        if (! $appointment) {
            return null;
        }

        $startsAt = self::startsAt($appointment);
        /** @var CarbonInterface|null $checkedInAt */
        $checkedInAt = $appointment->checked_in_at;
        /** @var CarbonInterface|null $startedAt */
        $startedAt = $appointment->started_at;

        return match (true) {
            self::statusOf($appointment) === AppointmentStatus::InProgress => 'Lo están atendiendo desde las '.($startedAt ?? $startsAt)->format('H:i'),
            self::statusOf($appointment) === AppointmentStatus::CheckedIn => 'Ya está en sala desde las '.($checkedInAt ?? $startsAt)->format('H:i'),
            $startsAt->isPast() => 'Tenía cita hoy a las '.$startsAt->format('H:i'),
            default => 'Tiene cita hoy a las '.$startsAt->format('H:i'),
        };
    }

    /** ¿Todavía se puede marcar que llegó a esa cita? No, si ya está en sala o lo están atendiendo. */
    private static function canMarkArrival(?Appointment $appointment): bool
    {
        return $appointment !== null && self::statusOf($appointment)->isChangeable();
    }

    /** El estado de la cita, con su tipo (el análisis estático no lo ve). */
    private static function statusOf(Appointment $appointment): AppointmentStatus
    {
        /** @var AppointmentStatus $status */
        $status = $appointment->status;

        return $status;
    }

    /** La hora de la cita, con su tipo (el análisis estático no lo ve). */
    private static function startsAt(Appointment $appointment): CarbonInterface
    {
        /** @var CarbonInterface $startsAt */
        $startsAt = $appointment->starts_at;

        return $startsAt;
    }

    /** ¿Se va a marcar que llegó a la cita que ya tenía? Entonces no se elige tratamiento ni doctor. */
    private static function arrivesToAppointment(Get $get): bool
    {
        return (bool) $get('use_appointment') && self::canMarkArrival(self::todaysAppointment($get));
    }

    /** La cita de hoy del paciente elegido que sigue sin cerrarse, si la hay. Un paciente nuevo no tiene. */
    private static function todaysAppointment(Get $get): ?Appointment
    {
        return $get('new_patient') ? null : self::todaysAppointmentOf($get('patient_id'));
    }

    /**
     * Su cita de hoy a la que puede estar llegando: la primera que todavía espera que llegue o, si ya está en sala o lo
     * atienden en todas, la primera de ellas.
     */
    private static function todaysAppointmentOf(mixed $patientId): ?Appointment
    {
        $today = self::todaysAppointmentsOf($patientId);

        return $today->first(fn (Appointment $appointment) => self::canMarkArrival($appointment)) ?? $today->first();
    }

    /**
     * Sus citas de hoy que todavía esperan un cierre, entre las que quien mira puede manejar (el doctor, solo las
     * suyas). Se pide varias veces al pintar la ventana: se busca una sola vez por pedido.
     *
     * @return Collection<int, Appointment>
     */
    private static function todaysAppointmentsOf(mixed $patientId): Collection
    {
        if (blank($patientId)) {
            return new Collection;
        }

        $memo = 'walk-in.todays-appointments.'.$patientId;

        if (! request()->attributes->has($memo)) {
            /** @var Builder<Appointment> $appointments */
            $appointments = AppointmentResource::getEloquentQuery();

            request()->attributes->set($memo, $appointments
                ->with(['treatment', 'doctor'])
                ->where('patient_id', $patientId)
                ->startsOn(today())
                ->whereIn('status', AppointmentStatus::open())
                ->orderBy('starts_at')
                ->get());
        }

        return request()->attributes->get($memo);
    }

    /** Lo que se le dice al paciente antes de pasarlo a sala. */
    private static function roomToday(Get $get): string
    {
        $slot = self::slot($get);

        return match (true) {
            $slot !== null => 'Lo puede atender hoy a las '.$slot->label().'.',
            SlotPicker::onlyActiveDoctorId() === null && self::otherDoctorWithRoom($get) === null => 'Hoy ya no hay hueco para ese tratamiento con ningún doctor.',
            default => 'Ya no tiene un hueco hoy para ese tratamiento.',
        };
    }

    /**
     * Sin hueco: si otro doctor sí puede hoy, se dice quién y a qué hora; si no, queda agendarlo otro día o esperar a
     * que hoy se libere uno.
     */
    private static function otherwise(Get $get): string
    {
        $then = WaitlistEntryActions::canAdd()
            ? 'agéndalo otro día o anótalo en la lista de espera: si hoy se libera un hueco, te llega la alerta.'
            : 'agéndalo otro día.';
        $other = self::otherDoctorWithRoom($get);

        return $other !== null
            ? $other['doctor']->name.' sí puede hoy a las '.$other['slot']->label().'. Si no, '.$then
            : ucfirst($then);
    }

    /**
     * El otro doctor que hace el tratamiento y se desocupa primero hoy, si hay alguno.
     *
     * @return array{doctor: Doctor, slot: Slot}|null
     */
    private static function otherDoctorWithRoom(Get $get): ?array
    {
        return self::doctorsWithRoomToday($get)
            ->first(fn (array $option) => $option['doctor']->getKey() !== (int) $get('doctor_id'));
    }

    /**
     * Los doctores que hacen el tratamiento y hoy tienen un hueco, del que se desocupa primero al último.
     *
     * @return Collection<int, array{doctor: Doctor, slot: Slot}>
     */
    private static function doctorsWithRoomToday(Get $get): Collection
    {
        $treatmentId = $get('treatment_id');

        if (blank($treatmentId)) {
            return collect();
        }

        return Doctor::query()
            ->active()
            ->whereHas('treatments', fn ($query) => $query->whereKey($treatmentId))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Doctor $doctor) => ['doctor' => $doctor, 'slot' => self::slotFor($doctor->getKey(), $treatmentId, $get('new_patient') ? null : $get('patient_id'))])
            ->filter(fn (array $option) => $option['slot'] !== null)
            ->sortBy(fn (array $option) => $option['slot']->startsAt->getTimestamp())
            ->values();
    }

    /**
     * "Nueva cita" encima, con el paciente (también el nuevo, con lo ya escrito), el tratamiento y el doctor, en el
     * primer día con lugar. Al agendar se cierran las dos; con la X se vuelve a esta.
     */
    private static function bookAnotherDayAction(): Action
    {
        return CreateAppointmentAction::make('bookAnotherDay')
            ->label('Agendarlo otro día')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            // Sin hueco hoy, "Pasar a sala" no se ofrece: lo que queda hacer va como botón, no como enlace.
            ->button()
            ->size(Size::Small)
            ->fillForm(fn (Get $get) => array_replace(
                CreateAppointmentAction::defaults([
                    'patient' => $get('new_patient') ? null : $get('patient_id'),
                    'treatment' => $get('treatment_id'),
                    'doctor' => $get('doctor_id'),
                ]),
                $get('new_patient') ? ['new_patient' => true, 'patient' => self::newPatientData($get)] : [],
                // La nota para el doctor ("viene con dolor…") va con la cita de otro día.
                filled($get('internal_notes')) ? ['internal_notes' => $get('internal_notes')] : [],
            ))
            ->cancelParentActions()
            ->visible(fn (Get $get) => self::slot($get) === null);
    }

    /** Si hoy se libera un hueco (alguien cancela o no viene), llega la alerta para ofrecérselo. */
    private static function waitForTodayAction(): Action
    {
        return WaitlistEntryActions::addFrom('waitForToday', fn (Get $get) => [
            'new_patient' => $get('new_patient'),
            'patient' => $get('patient_id'),
            'patient_data' => self::newPatientData($get),
            'treatment' => $get('treatment_id'),
            // Hoy le sirve cualquiera que haga el tratamiento.
            'doctor' => null,
            'from' => today()->toDateString(),
            'until' => today()->toDateString(),
        ])
            ->label('Anotar en lista de espera para hoy')
            ->color('gray')
            ->button()
            ->size(Size::Small)
            ->visible(fn (Get $get) => self::slot($get) === null && WaitlistEntryActions::canAdd());
    }

    /**
     * Lo que ya se escribió del paciente nuevo.
     *
     * @return array<string, mixed>
     */
    private static function newPatientData(Get $get): array
    {
        $data = $get('patient');

        return is_array($data) ? $data : [];
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

        if (($state['use_appointment'] ?? false) && self::canMarkArrival(self::todaysAppointmentOf($patientId))) {
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

        return $appointment !== null && ! self::canMarkArrival($appointment);
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
                    ->visible(fn (Get $get) => self::canMarkArrival(self::todaysAppointment($get)))
                    ->live(),
                // Llegó antes y su doctor está libre: pasa antes, y su hora queda para otro.
                Toggle::make('advance')
                    // Marcado, dice la hora que se va a usar: la que se vio al marcarlo.
                    ->label(fn (Get $get) => 'Adelantar su cita a las '.(($get('advance') ? $get('advance_to') : null) ?? self::earlierRoom($get)?->label()))
                    ->helperText(fn (Get $get) => self::advanceHint($get))
                    ->visible(fn (Get $get) => self::arrivesToAppointment($get) && self::earlierRoom($get) !== null)
                    ->live()
                    ->afterStateUpdated(fn (?bool $state, Get $get, Set $set) => $set('advance_to', $state ? self::earlierRoom($get)?->label() : null)),
                // La hora que se vio al marcarlo: si al pasarlo ya no está libre, se dice en vez de darle otra.
                Hidden::make('advance_to')
                    ->visible(fn (Get $get) => (bool) $get('advance') && self::arrivesToAppointment($get) && self::earlierRoom($get) !== null),
            ])
            ->visible(fn (Get $get) => self::todaysAppointment($get) !== null);
    }

    /**
     * Llegó antes de su hora a la cita que se va a marcar: el primer hueco que su doctor tiene desde ahora y antes de
     * esa hora. Se pide varias veces al pintar la ventana: se busca una sola vez por pedido.
     */
    private static function earlierRoom(Get $get): ?Slot
    {
        $appointment = self::arrivesToAppointment($get) ? self::todaysAppointment($get) : null;

        if ($appointment === null) {
            return null;
        }

        $memo = 'walk-in.earlier-room.'.$appointment->getKey();

        if (! request()->attributes->has($memo)) {
            request()->attributes->set($memo, app(AppointmentService::class)->earlierRoomToday($appointment));
        }

        return request()->attributes->get($memo);
    }

    /** "La agenda de Dra. Ana Torres está libre desde esa hora. Su hora de las 11:00 queda para otro paciente." */
    private static function advanceHint(Get $get): ?string
    {
        $appointment = self::todaysAppointment($get);

        if ($appointment === null) {
            return null;
        }

        /** @var Doctor $doctor */
        $doctor = $appointment->doctor;

        return 'La agenda de '.$doctor->name.' está libre desde esa hora. Su hora de las '.self::startsAt($appointment)->format('H:i').' queda para otro paciente.';
    }

    private static function todaysAppointmentDescription(Get $get): ?HtmlString
    {
        $appointment = self::todaysAppointment($get);

        if (! $appointment) {
            return null;
        }

        $advice = match (true) {
            ! self::canMarkArrival($appointment) => 'Sigue solo si viene por otra atención aparte: se le abrirá una cita más.',
            self::startsAt($appointment)->isFuture() => 'Llegó antes de su hora.',
            default => null,
        };

        $others = self::todaysAppointmentsOf($get('patient_id'))
            ->reject(fn (Appointment $other) => $other->is($appointment))
            ->map(fn (Appointment $other) => 'También tiene hoy '.self::describe($other).' a las '.self::startsAt($other)->format('H:i').' · '.self::statusOf($other)->getLabel().'.');

        return new HtmlString(collect([self::describe($appointment).' · '.self::statusOf($appointment)->getLabel(), $advice, ...$others])
            ->filter()
            ->map(fn (string $line) => e($line))
            ->implode('<br>'));
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
                            ->openUrlInNewTab()
                            // Un doctor solo abre la ficha de sus pacientes: a quien nunca atendió, el enlace le daría "sin acceso".
                            ->visible(fn (Get $get) => self::canViewPatient($get)),
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

    private static function canViewPatient(Get $get): bool
    {
        $patient = self::chosenPatient($get);

        return $patient !== null && (auth()->user()?->can('view', $patient) ?? false);
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

    /** De quienes hacen ese tratamiento, quien tiene el hueco más cercano hoy (o el primero, si hoy nadie tiene). Null si el doctor ya está elegido. */
    private static function soonestDoctorId(Get $get): ?int
    {
        if (blank($get('treatment_id')) || filled($get('doctor_id'))) {
            return null;
        }

        $soonest = self::doctorsWithRoomToday($get)->first();

        if ($soonest !== null) {
            return (int) $soonest['doctor']->getKey();
        }

        // Si hoy nadie tiene hueco, queda el primero que lo hace: así la ventana lo dice y ofrece qué hacer.
        $first = Doctor::query()->active()->whereHas('treatments', fn ($query) => $query->whereKey($get('treatment_id')))->orderBy('sort_order')->value('id');

        return $first !== null ? (int) $first : null;
    }
}
