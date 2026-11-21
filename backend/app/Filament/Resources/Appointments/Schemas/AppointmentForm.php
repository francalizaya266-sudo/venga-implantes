<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Enums\AppointmentOrigin;
use App\Enums\AppointmentStatus;
use App\Enums\DocumentType;
use App\Enums\PatientRelationship;
use App\Enums\PlanPaymentMode;
use App\Enums\RescheduleRequestStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Patients\Schemas\PatientForm;
use App\Filament\Resources\RescheduleRequests\RescheduleRequestResource;
use App\Filament\Support\PatientContext;
use App\Filament\Support\SlotPicker;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\Booking\AppointmentService;
use App\Services\Clinical\TreatmentPlanService;
use App\Support\Dates;
use App\Support\Money;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\StateCasts\BooleanStateCast;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;

/** Nueva cita desde el panel (recepción o doctor). */
class AppointmentForm
{
    /**
     * En dos columnas: a la izquierda se elige quién, qué y cuándo; a la derecha queda el resumen de lo que se va a
     * agendar (para leérselo al paciente), el aviso de WhatsApp y la nota para el doctor. En el celular, una debajo
     * de la otra.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Group::make([
                    self::patientSection(),
                    Section::make('Tratamiento y horario')
                        ->columns(2)
                        ->schema([
                            ...SlotPicker::fields(),
                            ...self::repeatFields(),
                        ]),
                    // Cerrado: casi nunca se usa.
                    Section::make('Más opciones')
                        ->description('Otra duración o fecha fija')
                        ->columns(2)
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            // Para el caso que necesita más (o menos) tiempo que el de siempre: los horarios libres se vuelven a calcular.
                            Select::make('duration_minutes')
                                ->label('Duración')
                                ->placeholder('La normal del tratamiento')
                                ->options(fn (Get $get) => SlotPicker::durationOptions(self::usualDuration($get)))
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set) => SlotPicker::keepSlotIfItFits($get, $set)),
                            Toggle::make('fixed_date')
                                ->label('Fecha fija (el paciente no la mueve solo)')
                                ->inline(false),
                        ]),
                ])->columnSpan(['lg' => 2]),
                self::summarySection()->columnSpan(['lg' => 1]),
            ]);
    }

    /**
     * "Resumen de la cita": lo que el paciente pregunta al agendar (qué día, de qué hora a qué hora, cuánto dura y
     * cuánto cuesta), y lo que recepción decide en cada cita: si se le prepara el aviso y qué tiene que saber el doctor.
     */
    private static function summarySection(): Section
    {
        return Section::make('Resumen de la cita')
            ->extraAttributes(['class' => 'appointment-summary'])
            ->schema([
                View::make('filament.forms.appointment-summary')
                    ->viewData(fn (Get $get): array => ['rows' => self::summaryRows($get)]),
                // Con el paciente al frente o al teléfono, el aviso sobra y ensucia Avisos.
                Toggle::make('notify')
                    ->label('Enviar aviso por WhatsApp')
                    ->helperText('Apágalo si ya se lo dijiste en persona o por teléfono: no queda nada por enviar en Avisos.')
                    ->default(true),
                Textarea::make('internal_notes')
                    ->label('Nota para el doctor')
                    ->placeholder('Viene con dolor, trae su radiografía…')
                    ->helperText('No la ve el paciente.')
                    ->rows(3),
            ]);
    }

    /**
     * Las filas del resumen, con el tratamiento y el doctor ya elegidos: el doctor, el día, de qué hora a qué hora,
     * cuánto dura, el precio y, si se repite, cada cuánto. Una sesión de su plan se cobra a lo acordado en el plan,
     * no al precio de lista.
     *
     * @return list<array{label: string, value: string, pending: bool}>
     */
    public static function summaryRows(Get $get): array
    {
        $treatment = Treatment::find($get('treatment_id'));
        $doctor = Doctor::find($get('doctor_id'));

        if (! $treatment || ! $doctor) {
            return [];
        }

        $minutes = SlotPicker::chosenDuration($get) ?? $doctor->durationFor($treatment);
        $time = $get('manual') ? $get('manual_time') : $get('slot');
        $day = filled($get('date')) ? CarbonImmutable::parse($get('date')) : null;
        $start = filled($time) && $day ? $day->setTimeFromTimeString($time) : null;

        $price = match (true) {
            filled($get('treatment_plan_item_id')) => 'El acordado en su plan',
            $treatment->price_from === null => null,
            (float) $treatment->price_from === 0.0 => 'Sin costo',
            default => 'Desde '.Money::short($treatment->price_from),
        };

        $rows = [
            ['label' => 'Doctor', 'value' => $doctor->name, 'pending' => false],
            ['label' => 'Día', 'value' => $day ? self::dayLabel($day) : 'Falta elegirlo', 'pending' => $day === null],
            ['label' => 'Hora', 'value' => $start ? $start->format('H:i').' a '.$start->addMinutes($minutes)->format('H:i') : 'Falta elegirla', 'pending' => $start === null],
            ['label' => 'Duración', 'value' => Dates::duration($minutes), 'pending' => false],
        ];

        if ($price !== null) {
            $rows[] = ['label' => 'Precio', 'value' => $price, 'pending' => false];
        }

        if ($get('repeat') && filled($get('repeat_every')) && filled($get('repeat_times'))) {
            $every = (int) $get('repeat_every');
            $rows[] = ['label' => 'Se repite', 'value' => 'Cada '.($every === 1 ? 'semana' : $every.' semanas').', '.(int) $get('repeat_times').' citas', 'pending' => false];
        }

        return $rows;
    }

    /** "Hoy, sábado 21 de noviembre", "Mañana, domingo 22 de noviembre" o "Lunes 23 de noviembre". */
    private static function dayLabel(CarbonImmutable $day): string
    {
        $date = $day->translatedFormat('l j \d\e F');

        return match (true) {
            $day->isToday() => 'Hoy, '.$date,
            $day->isTomorrow() => 'Mañana, '.$date,
            default => ucfirst($date),
        };
    }

    /**
     * "Repetir": los controles que vuelven cada tantas semanas se agendan de una vez. Antes de guardar se dice qué
     * fechas quedan, cuáles cambian de hora porque la de siempre está ocupada y cuáles se saltan por no tener lugar.
     *
     * @return list<Field|Text>
     */
    private static function repeatFields(): array
    {
        return [
            Toggle::make('repeat')
                ->label('Repetir esta cita (por ejemplo, un control cada 4 semanas)')
                ->extraFieldWrapperAttributes(['class' => 'quiet-toggle'])
                ->live()
                ->columnSpanFull(),
            Select::make('repeat_every')
                ->label('Cada')
                ->options([1 => '1 semana', 2 => '2 semanas', 3 => '3 semanas', 4 => '4 semanas', 6 => '6 semanas', 8 => '8 semanas', 12 => '12 semanas'])
                ->default(4)
                ->selectablePlaceholder(false)
                ->required(fn (Get $get) => (bool) $get('repeat'))
                ->visible(fn (Get $get) => (bool) $get('repeat'))
                ->live(),
            Select::make('repeat_times')
                ->label('Cuántas citas en total')
                ->options(collect(range(2, AppointmentService::SERIES_MAX_TIMES))->mapWithKeys(fn (int $times) => [$times => $times.' citas'])->all())
                ->default(6)
                ->selectablePlaceholder(false)
                ->required(fn (Get $get) => (bool) $get('repeat'))
                ->visible(fn (Get $get) => (bool) $get('repeat'))
                ->live(),
            Text::make(fn (Get $get) => self::repeatSummary($get))
                ->visible(fn (Get $get) => (bool) $get('repeat') && self::repeatSummary($get) !== null)
                ->columnSpanFull(),
        ];
    }

    /**
     * "Además de esta, quedan: lun 30 nov 10:00 · lun 28 dic 10:30 (las 10:00 están ocupadas). Sin lugar, se saltan:
     * lun 25 ene." Con el tratamiento, el doctor, el día y la hora ya elegidos.
     */
    private static function repeatSummary(Get $get): ?string
    {
        $treatment = Treatment::find($get('treatment_id'));
        $doctor = Doctor::find($get('doctor_id'));
        $time = $get('manual') ? $get('manual_time') : $get('slot');

        if (! $treatment || ! $doctor || blank($time) || blank($get('date')) || blank($get('repeat_every')) || blank($get('repeat_times'))) {
            return null;
        }

        $first = CarbonImmutable::parse($get('date'))->setTimeFromTimeString($time);
        $plan = collect(app(AppointmentService::class)->seriesPlan(
            $doctor,
            $treatment,
            $first,
            (int) $get('repeat_every'),
            (int) $get('repeat_times'),
            filled($get('patient_id')) ? (int) $get('patient_id') : null,
            SlotPicker::chosenDuration($get),
        ));

        $booked = $plan->filter(fn (array $step) => $step['startsAt'] !== null)
            ->map(fn (array $step) => $step['startsAt']->translatedFormat('D j M H:i').($step['moved'] ? ' (las '.$first->format('H:i').' están ocupadas)' : ''));
        $skipped = $plan->filter(fn (array $step) => $step['startsAt'] === null)
            ->map(fn (array $step) => $step['date']->translatedFormat('D j M'));

        return collect([
            $booked->isNotEmpty() ? 'Además de esta, quedan: '.$booked->implode(' · ').'.' : 'Las demás fechas no tienen lugar: solo se agenda esta.',
            // "nov." ya trae su punto.
            $skipped->isNotEmpty() && $booked->isNotEmpty() ? 'Sin lugar ese día, se saltan: '.rtrim($skipped->implode(' · '), '.').'.' : null,
            'Al paciente se le prepara un solo aviso, el de la primera.',
        ])->filter()->implode(' ');
    }

    /** Lo que ese doctor tarda en ese tratamiento; null si falta elegir alguno. */
    private static function usualDuration(Get $get): ?int
    {
        $treatment = Treatment::find($get('treatment_id'));
        $doctor = Doctor::find($get('doctor_id'));

        return $treatment && $doctor ? $doctor->durationFor($treatment) : null;
    }

    /**
     * Quién viene: uno registrado o uno nuevo que se registra ahí mismo, con su sesión del plan si la tiene.
     * Si ya tiene una cita por delante, se dice bajo su nombre: así no se le agenda dos veces lo mismo.
     *
     * @param  bool  $forToday  La cita es para hoy ("Llegó sin cita"): no se propone el día en que suele venir, y su cita de hoy la muestra esa ventana.
     * @param  list<Component>  $notices  Lo que esa ventana avisa del paciente elegido, justo bajo su nombre.
     * @param  Closure|null  $hidePlanWhen  Cuándo no se pregunta por la sesión de su plan (llegó a una cita que ya la dice).
     */
    public static function patientSection(bool $forToday = false, array $notices = [], ?Closure $hidePlanWhen = null): Section
    {
        return Section::make('Paciente')
            ->afterHeader([self::patientKindToggle()])
            ->schema([
                // El paciente que estaba elegido al marcar "paciente nuevo": si se desmarca, vuelve.
                Hidden::make('patient_before_new')->dehydrated(false),
                self::patientSelect()
                    ->hiddenLabel()
                    ->hidden(fn (Get $get) => (bool) $get('new_patient'))
                    ->required(fn (Get $get) => ! $get('new_patient'))
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get) use ($forToday) {
                        $set('treatment_plan_item_id', null);

                        // Con varios doctores, queda el que lo atendió la última vez.
                        $doctorChanged = SlotPicker::followLastDoctor($get, $set);

                        // Si suele venir un mismo día, se propone el próximo, con sus horarios de siempre primero.
                        if (! $forToday) {
                            SlotPicker::followUsualDay($get, $set);

                            if ($doctorChanged) {
                                SlotPicker::refreshAfterDoctorChange($get, $set);
                            }
                        }
                    }),
                // Bajo su nombre, lo que recepción le recuerda al agendar: lo que debe y si suele faltar.
                Text::make(fn (Get $get) => self::reminders($get))
                    ->color('gray')
                    ->visible(fn (Get $get) => ! $forToday && self::reminders($get) !== null),
                ...$notices,
                self::upcomingAppointmentCallout($forToday),
                ...($forToday ? [] : [self::waitlistCallout()]),
                Grid::make(2)
                    ->statePath('patient')
                    ->visible(fn (Get $get) => (bool) $get('new_patient'))
                    ->schema(self::newPatientFields()),
                self::patientContextCallout($forToday)->hidden($hidePlanWhen ?? false),
                self::planItemSelect()->hidden($hidePlanWhen ?? false),
                self::planAcceptedCheckbox()->hidden($hidePlanWhen ?? false),
                // Junto al paciente, donde recepción lo pregunta. Solo a quien viene por primera vez: a los
                // demás ya se les preguntó (los reportes usan el origen del paciente).
                Select::make('origin')
                    ->label('¿Cómo nos encontró?')
                    ->options(AppointmentOrigin::class)
                    ->visible(fn (Get $get) => self::isFirstTime($get)),
            ]);
    }

    /**
     * Lo que toca con el paciente elegido, con el botón que deja la ventana lista: que lo elegido es una sesión de su
     * plan, que ya tuvo su evaluación, lo que le toca de su plan, el presupuesto sin respuesta o la cita a la que faltó.
     * En gris azulado si es una sugerencia; en ámbar si conviene preguntarle antes de seguir.
     */
    private static function patientContextCallout(bool $forToday): Callout
    {
        return Callout::make(fn (Get $get) => self::patientContext($get, $forToday)['heading'] ?? null)
            ->key('patientContext')
            ->description(fn (Get $get) => self::patientContext($get, $forToday)['detail'] ?? null)
            ->icon(fn (Get $get) => match (self::patientContext($get, $forToday)['key'] ?? null) {
                'missedVisit' => Heroicon::OutlinedArrowPath,
                'evaluationAgain' => Heroicon::OutlinedExclamationTriangle,
                default => Heroicon::OutlinedClipboardDocumentList,
            })
            ->color(fn (Get $get) => (self::patientContext($get, $forToday)['warn'] ?? false) ? 'warning' : 'info')
            ->actions([
                Action::make('usePatientContext')
                    ->label(fn (Get $get) => self::patientContext($get, $forToday)['button'] ?? null)
                    ->url(fn (Get $get) => self::patientContext($get, $forToday)['url'] ?? null, shouldOpenInNewTab: true)
                    ->button()
                    ->size(Size::Small)
                    ->visible(fn (Get $get) => filled(self::patientContext($get, $forToday)['button'] ?? null))
                    ->action(fn (Get $get, Set $set) => self::usePatientContext($get, $set, $forToday)),
            ])
            ->visible(fn (Get $get) => self::patientContext($get, $forToday) !== null);
    }

    /**
     * Llena la ventana con lo que propone el aviso: la sesión, el tratamiento y el doctor. En "Nueva cita" se busca
     * de nuevo el día con lugar y el horario, como al elegirlos a mano.
     */
    private static function usePatientContext(Get $get, Set $set, bool $forToday): void
    {
        $fill = self::patientContext($get, $forToday)['fill'] ?? [];

        foreach ($fill as $field => $value) {
            $set($field, $value);
        }

        if (! $forToday) {
            SlotPicker::refreshAfterDoctorChange($get, $set);
        }
    }

    /**
     * El aviso de lo que toca con el paciente elegido, para lo que ya se eligió. Se pide varias veces al pintar la
     * ventana: se calcula una sola vez por pedido.
     *
     * @return array{key: string, heading: string, detail: string, warn: bool, button: string|null, fill: array<string, int|null>, url: string|null}|null
     */
    private static function patientContext(Get $get, bool $forToday): ?array
    {
        $patientId = $get('patient_id');

        if ($get('new_patient') || blank($patientId)) {
            return null;
        }

        $treatmentId = filled($get('treatment_id')) ? (int) $get('treatment_id') : null;
        $planItemId = filled($get('treatment_plan_item_id')) ? (int) $get('treatment_plan_item_id') : null;
        $memo = 'appointment-form.context.'.($forToday ? 'today' : 'new').'.'.$patientId.'.'.$treatmentId.'.'.$planItemId;

        if (! request()->attributes->has($memo)) {
            $patient = Patient::find($patientId);
            /** @var User $user */
            $user = auth()->user();

            request()->attributes->set($memo, $patient ? PatientContext::notice($patient, $user, $forToday, $treatmentId, $planItemId) : null);
        }

        return request()->attributes->get($memo);
    }

    /** Lo que debe (y si una cuota se le pasó) y si suele faltar, en una línea. Una sola vez por pedido. */
    private static function reminders(Get $get): ?string
    {
        $patientId = $get('patient_id');

        if ($get('new_patient') || blank($patientId)) {
            return null;
        }

        $memo = 'appointment-form.reminders.'.$patientId;

        if (! request()->attributes->has($memo)) {
            $patient = Patient::find($patientId);
            /** @var User $user */
            $user = auth()->user();

            request()->attributes->set($memo, $patient ? PatientContext::reminders($patient, $user) : null);
        }

        return request()->attributes->get($memo);
    }

    /**
     * "Existente | Nuevo", arriba a la derecha del paciente, como en la ficha: uno ya registrado o uno nuevo que se
     * registra en la misma ventana. Si se vuelve a "Existente", regresa el paciente que estaba elegido (va con un
     * Hidden "patient_before_new" en la misma sección).
     */
    public static function patientKindToggle(): ToggleButtons
    {
        return ToggleButtons::make('new_patient')
            ->label('¿Ya está registrado?')
            ->hiddenLabel()
            ->options([0 => 'Existente', 1 => 'Nuevo'])
            ->icons([1 => Heroicon::OutlinedPlus])
            ->grouped()
            ->stateCast(app(BooleanStateCast::class, ['isNullable' => false, 'isStoredAsInt' => true]))
            ->extraAttributes(['class' => 'patient-kind'])
            ->live()
            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                if ((bool) $state) {
                    $set('patient_before_new', $get('patient_id'));
                    $set('patient_id', null);
                } else {
                    $set('patient_id', $get('patient_before_new'));
                }
            });
    }

    /**
     * Si el paciente está en la lista de espera, se dice: al agendarle lo que espera, sale solo de la lista (y así
     * nadie le ofrece después un horario que ya no necesita). Si se le está agendando justo eso, se dice que esta cita
     * lo saca de la lista.
     */
    private static function waitlistCallout(): Callout
    {
        return Callout::make(fn (Get $get) => ($entry = self::waitingEntry($get)) ? 'Está en la lista de espera desde el '.self::entryDate($entry) : null)
            ->key('onTheWaitlist')
            ->description(fn (Get $get) => ($entry = self::waitingEntry($get)) ? self::waitlistDescription($entry, $get('treatment_id')) : null)
            ->icon(Heroicon::OutlinedQueueList)
            ->color('gray')
            ->visible(fn (Get $get) => self::waitingEntry($get) !== null);
    }

    private static function waitlistDescription(WaitlistEntry $entry, mixed $chosenTreatmentId): string
    {
        /** @var Treatment|null $treatment */
        $treatment = $entry->treatment;

        if ($treatment === null) {
            return 'Espera cualquier horario: al agendarle esta cita, sale solo de la lista.';
        }

        return (int) $chosenTreatmentId === $treatment->getKey()
            ? 'Espera un horario para '.$treatment->name.': al agendarle esta cita, sale solo de la lista.'
            : 'Espera un horario para '.$treatment->name.'. Si le agendas eso, sale solo de la lista.';
    }

    /** "10 de noviembre": el día en que lo anotaron. */
    private static function entryDate(WaitlistEntry $entry): string
    {
        /** @var CarbonInterface $createdAt */
        $createdAt = $entry->created_at;

        return $createdAt->translatedFormat('j \d\e F');
    }

    /**
     * Su anotación que sigue en espera (la del tratamiento que se elige, si tiene una; si no, la más antigua), si quien
     * mira ve la lista. Se busca una sola vez por pedido.
     */
    private static function waitingEntry(Get $get): ?WaitlistEntry
    {
        $patientId = $get('patient_id');

        if ($get('new_patient') || blank($patientId) || ! (auth()->user()?->can('viewAny', WaitlistEntry::class) ?? false)) {
            return null;
        }

        $memo = 'appointment-form.waiting.'.$patientId;

        if (! request()->attributes->has($memo)) {
            request()->attributes->set($memo, WaitlistEntry::query()->active()->with('treatment')->where('patient_id', $patientId)->oldest()->get());
        }

        /** @var Collection<int, WaitlistEntry> $entries */
        $entries = request()->attributes->get($memo);

        return $entries->first(fn (WaitlistEntry $entry) => filled($get('treatment_id')) && $entry->treatment_id === (int) $get('treatment_id')) ?? $entries->first();
    }

    /**
     * La otra cita del paciente, para no agendarle dos veces lo mismo. El aviso se adapta a lo que se está haciendo:
     * en ámbar cuando puede ser la misma (ese mismo día, el mismo tratamiento o sesión del plan, un cambio que pidió
     * y nadie resolvió, o una cita suelta en los próximos 7 días), con el enlace para verla o moverla, porque muchos
     * llaman para reprogramar y no para sacar otra. Si no, en gris: quien viene a controles o tiene un plan casi
     * siempre tiene una por delante, y un aviso que sale siempre en ámbar se deja de leer.
     *
     * @param  bool  $forToday  "Llegó sin cita": solo las de otro día (la de hoy la muestra esa ventana) y sin enlace, porque el paciente está en el mostrador.
     */
    private static function upcomingAppointmentCallout(bool $forToday): Callout
    {
        return Callout::make(fn (Get $get) => self::upcomingNotice($get, $forToday)['heading'] ?? null)
            ->key('upcomingAppointment')
            ->description(fn (Get $get) => ($notice = self::upcomingNotice($get, $forToday))
                ? new HtmlString(collect($notice['lines'])->map(fn (string $line) => e($line))->implode('<br>'))
                : null)
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color(fn (Get $get) => (self::upcomingNotice($get, $forToday)['warn'] ?? false) ? 'warning' : 'gray')
            ->actions([
                Action::make('openUpcomingAppointment')
                    ->label(fn (Get $get) => self::upcomingNotice($get, $forToday)['link']['label'] ?? null)
                    ->url(fn (Get $get) => self::upcomingNotice($get, $forToday)['link']['url'] ?? null)
                    ->visible(fn (Get $get) => filled(self::upcomingNotice($get, $forToday)['link'] ?? null)),
            ])
            ->visible(fn (Get $get) => self::upcomingNotice($get, $forToday) !== null);
    }

    /**
     * Qué se dice de la otra cita del paciente, según la ventana, el día y el tratamiento elegidos.
     *
     * @return array{heading: string, lines: list<string>, warn: bool, link: array{label: string, url: string}|null}|null
     */
    public static function upcomingNotice(Get $get, bool $forToday = false): ?array
    {
        $patientId = $get('patient_id');

        if ($get('new_patient') || blank($patientId)) {
            return null;
        }

        $chosenDate = $forToday ? null : $get('date');
        ['mentioned' => $appointment, 'count' => $count] = self::upcomingAppointments($patientId, $forToday, $chosenDate);

        if (! $appointment) {
            return null;
        }

        // El análisis estático no ve los tipos de la cita: se dicen aquí.
        /** @var CarbonInterface $startsAt */
        $startsAt = $appointment->starts_at;
        /** @var AppointmentStatus $status */
        $status = $appointment->status;
        /** @var Treatment $treatment */
        $treatment = $appointment->treatment;
        /** @var Doctor $doctor */
        $doctor = $appointment->doctor;

        $sameDay = filled($chosenDate) && $startsAt->toDateString() === $chosenDate;
        $changeRequested = (bool) $appointment->getAttribute('has_pending_change');
        $sameItem = filled($get('treatment_plan_item_id')) && (int) $get('treatment_plan_item_id') === $appointment->treatment_plan_item_id;
        // Con sesiones por agendar, la cita de ese procedimiento es la sesión anterior, no la misma.
        $sameVisit = filled($get('treatment_plan_item_id'))
            ? $sameItem && self::allSessionsBooked((int) $get('treatment_plan_item_id'))
            : filled($get('treatment_id')) && (int) $get('treatment_id') === $appointment->treatment_id;
        $withinAWeek = $startsAt->lt(today()->addDays(7));
        // Una de su serie de controles o de su plan es lo normal: solo una suelta y cercana hace dudar.
        $soonAndOnItsOwn = $withinAWeek && $appointment->series_id === null && $appointment->treatment_plan_item_id === null;
        $warn = $sameDay || $changeRequested || $sameVisit || $soonAndOnItsOwn || ($forToday && $withinAWeek);

        $lines = [
            $treatment->name
                .(SlotPicker::onlyActiveDoctorId() === null ? ' con '.$doctor->name : '')
                .' · '.$status->getLabel()
                .($count > 1 ? ' (y '.($count - 1).' '.($count === 2 ? 'cita' : 'citas').' más)' : ''),
        ];

        if ($warn) {
            $lines[] = match (true) {
                $changeRequested => 'Pidió cambiar esta cita: resuélvelo en "Cambios pedidos" en vez de crear otra.',
                $forToday => '¿Se confundió de día? Esa cita sigue en pie: muévela o cancélala después.',
                $sameVisit && filled($get('treatment_plan_item_id')) => 'Esta sesión de su plan ya está agendada. Continúa solo si necesita otra.',
                $sameItem => 'Esa es la sesión anterior de este procedimiento: esta cita sería la siguiente.',
                default => 'Continúa solo si necesita una cita adicional.',
            };
        }

        return [
            'heading' => $sameDay ? self::sameDayHeading($startsAt) : self::upcomingHeading($startsAt),
            'lines' => $lines,
            'warn' => $warn,
            'link' => $forToday ? null : self::upcomingLink($appointment, $changeRequested),
        ];
    }

    /** "Hoy ya tiene cita a las 11:00", "Mañana ya tiene cita a las 9:00" o "Ese día ya tiene cita a las 16:00". */
    private static function sameDayHeading(CarbonInterface $startsAt): string
    {
        $day = match (true) {
            $startsAt->isToday() => 'Hoy',
            $startsAt->isTomorrow() => 'Mañana',
            default => 'Ese día',
        };

        return $day.' ya tiene cita a las '.$startsAt->format('H:i');
    }

    /** ¿Ya tienen cita todas las sesiones que le faltan a ese procedimiento de su plan? */
    private static function allSessionsBooked(int $itemId): bool
    {
        $item = TreatmentPlanItem::find($itemId);

        if ($item === null) {
            return false;
        }

        $booked = app(TreatmentPlanService::class)->bookedSessions([$itemId])[$itemId] ?? 0;

        return TreatmentPlanService::nextSessionToBook($item, $booked) === null;
    }

    /** "Ya tiene cita mañana a las 10:00" o "Ya tiene cita el miércoles 4 de noviembre a las 16:00". */
    public static function upcomingHeading(CarbonInterface $startsAt): string
    {
        $day = match (true) {
            $startsAt->isToday() => 'hoy',
            $startsAt->isTomorrow() => 'mañana',
            default => 'el '.$startsAt->translatedFormat('l j \d\e F'),
        };

        return 'Ya tiene cita '.$day.' a las '.$startsAt->format('H:i');
    }

    /**
     * Si pidió un cambio, a ese pedido; si no, a la página de la cita, con "Mover cita" ya abierto si quien mira la
     * puede mover.
     *
     * @return array{label: string, url: string}
     */
    private static function upcomingLink(Appointment $appointment, bool $changeRequested): array
    {
        if ($changeRequested && RescheduleRequestResource::canViewAny()) {
            /** @var Patient $patient */
            $patient = $appointment->patient;

            return ['label' => 'Ver el cambio que pidió', 'url' => RescheduleRequestResource::forPatientUrl($patient)];
        }

        $canMove = auth()->user()?->can('update', $appointment) ?? false;

        return [
            'label' => $canMove ? 'Ver o mover esa cita' : 'Ver esa cita',
            'url' => AppointmentResource::getUrl('view', ['record' => $appointment, ...($canMove ? ['action' => 'move'] : [])]),
        ];
    }

    /**
     * Las citas del paciente que todavía se pueden mover, entre las que quien mira puede ver (el doctor, solo las de
     * su agenda): la que conviene mencionar (la del día elegido o, si no, la próxima) y cuántas son. Se pide varias
     * veces al pintar la ventana: se busca una sola vez por pedido.
     *
     * @param  bool  $forToday  Sin contar las de hoy.
     * @return array{mentioned: Appointment|null, count: int}
     */
    private static function upcomingAppointments(mixed $patientId, bool $forToday, mixed $chosenDate): array
    {
        $memo = 'appointment-form.upcoming.'.$patientId.'.'.($forToday ? 'after-today' : 'from-today').'.'.$chosenDate;

        if (! request()->attributes->has($memo)) {
            $upcoming = fn () => AppointmentResource::getEloquentQuery()
                ->where('patient_id', $patientId)
                ->where('starts_at', '>=', $forToday ? today()->addDay() : today())
                ->whereIn('status', AppointmentStatus::changeable());
            $withDetails = fn () => $upcoming()
                ->with(['treatment', 'doctor', 'patient'])
                ->withExists(['rescheduleRequests as has_pending_change' => fn (Builder $requests) => $requests->where('status', RescheduleRequestStatus::Pending)])
                ->orderBy('starts_at');

            $onChosenDay = filled($chosenDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $chosenDate)
                ? $withDetails()->whereDate('starts_at', (string) $chosenDate)->first()
                : null;

            request()->attributes->set($memo, [
                'mentioned' => $onChosenDay ?? $withDetails()->first(),
                'count' => $upcoming()->count(),
            ]);
        }

        return request()->attributes->get($memo);
    }

    /** Paciente nuevo, o uno registrado que nunca vino y del que no se sabe cómo llegó. */
    private static function isFirstTime(Get $get): bool
    {
        if ($get('new_patient')) {
            return true;
        }

        return filled($get('patient_id'))
            && Patient::query()->whereKey($get('patient_id'))->whereNull('first_visit_at')->whereNull('origin')->exists();
    }

    /** Si el paciente tiene un plan de tratamiento, la cita puede ser una de sus sesiones: al atenderla se marca sola. */
    public static function planItemSelect(): Select
    {
        return Select::make('treatment_plan_item_id')
            ->label('¿Es una sesión de su plan de tratamiento?')
            ->placeholder('No, es otra cita')
            ->options(fn (Get $get) => self::planItemOptions($get('patient_id')))
            ->visible(fn (Get $get) => self::planItemOptions($get('patient_id')) !== [])
            ->default(fn () => request()->integer('plan_item') ?: null)
            ->in(fn (Get $get) => array_keys(self::planItemOptions($get('patient_id'))))
            ->live()
            ->afterStateUpdated(function (?string $state, Set $set, Get $get) {
                $item = TreatmentPlanItem::with('plan')->find($state);

                // Se completa con el doctor del plan y el tratamiento del procedimiento.
                if ($item && blank($get('doctor_id'))) {
                    $set('doctor_id', $item->plan->doctor_id);
                }

                if ($item?->treatment_id) {
                    $set('treatment_id', $item->treatment_id);
                    SlotPicker::syncTreatmentShortcut($get, $set);
                }
            });
    }

    /**
     * Agendar una sesión no es aceptar el plan: si todavía no lo firmó, se pregunta. Sin marcarlo, el plan
     * sigue esperando su firma (o queda aceptado solo cuando se haga la primera sesión).
     */
    public static function planAcceptedCheckbox(): Checkbox
    {
        return Checkbox::make('plan_accepted')
            ->label('El paciente ya aceptó el plan')
            ->helperText('Sin firmar, paga por sesión y el precio queda fijo. Si no lo marcas, el plan sigue esperando su firma.')
            // Un paquete que se paga en un solo pago o en cuotas se acepta firmando, para elegir cómo.
            ->visible(fn (Get $get) => filled($get('treatment_plan_item_id'))
                && ($plan = TreatmentPlanItem::with('plan')->find($get('treatment_plan_item_id'))?->plan) !== null
                && $plan->accepted_at === null
                && in_array(PlanPaymentMode::PerSession, TreatmentPlanService::paymentModes($plan), true));
    }

    /**
     * Procedimientos pendientes de los planes vigentes del paciente, con la sesión que toca agendar: si la 1 ya tiene
     * cita, se ofrece la 2. Recepción ve el tratamiento; la pieza solo la ve quien puede ver lo clínico.
     *
     * @return array<int, string>
     */
    public static function planItemOptions(mixed $patientId): array
    {
        $patient = filled($patientId) ? Patient::find($patientId) : null;

        if (! $patient) {
            return [];
        }

        /** @var User $user */
        $user = auth()->user();
        $plans = app(TreatmentPlanService::class);
        $items = $plans->workableItems($patient);
        $booked = $plans->bookedSessions($items->modelKeys());

        return $items
            ->mapWithKeys(fn (TreatmentPlanItem $item) => [$item->getKey() => $item->label(withTooth: $user->canSeeClinical())
                .self::sessionLabel($item, $booked[$item->getKey()] ?? 0)
                .($item->in_payment_schedule ? ' · ya incluida en su forma de pago' : '')])
            ->all();
    }

    /** " · sesión 2 de 3", o " · ya agendada" si todas las que le faltan ya tienen cita. */
    private static function sessionLabel(TreatmentPlanItem $item, int $booked): string
    {
        $next = TreatmentPlanService::nextSessionToBook($item, $booked);

        return match (true) {
            $next === null => ' · ya agendada',
            $item->sessions_planned > 1 => ' · sesión '.$next.' de '.$item->sessions_planned,
            default => '',
        };
    }

    public static function patientSelect(): Select
    {
        return Select::make('patient_id')
            ->label('Paciente')
            ->placeholder('Busca por nombre, DNI o celular')
            ->searchable()
            ->required()
            ->default(fn () => request()->integer('patient') ?: null)
            ->getSearchResultsUsing(fn (string $search) => Patient::query()
                ->search($search)
                ->with('account')
                ->limit(20)
                ->get()
                ->mapWithKeys(fn (Patient $p) => [$p->id => $p->selectLabel()]))
            ->getOptionLabelUsing(fn ($value) => Patient::with('account')->find($value)?->selectLabel());
    }

    /**
     * Datos mínimos del paciente nuevo, en la misma ventana de la cita.
     *
     * @return list<Field>
     */
    public static function newPatientFields(): array
    {
        return [
            TextInput::make('first_name')->label('Nombres')->required()->live(onBlur: true),
            TextInput::make('last_name')->label('Apellidos')->required()->live(onBlur: true),
            TextInput::make('phone')
                ->label('Celular')
                ->tel()
                ->helperText('Si es un familiar a cargo, usa el celular del titular.')
                ->live(onBlur: true)
                ->rule(fn () => function (string $attribute, $value, $fail) {
                    if (filled($value) && ($problem = Phone::problem((string) $value)) !== null) {
                        $fail($problem);
                    }
                }),
            PatientForm::relationshipSelect()
                ->helperText(null)
                ->default(PatientRelationship::Self->value),
            PatientForm::birthDateInput(),
            Grid::make(['default' => 3])
                ->schema([
                    Select::make('document_type')
                        ->label('Documento')
                        ->options(DocumentType::class)
                        ->default(DocumentType::Dni->value)
                        ->selectablePlaceholder(false)
                        ->live(),
                    // Como en la ficha: con un documento que ya está registrado, se dice en vez de fallar al guardar.
                    PatientForm::documentNumberInput()
                        ->label('Número')
                        ->unique(table: Patient::class, column: 'document_number', modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('document_type', $get('document_type') instanceof DocumentType ? $get('document_type')->value : $get('document_type')))
                        ->validationMessages(['unique' => 'Ya hay un paciente con este documento: elígelo como existente.'])
                        ->columnSpan(2),
                ]),
            // Si ya está registrado, se agenda a su nombre en vez de crear otro paciente.
            PatientForm::duplicatesCheck(onPick: function (int $patientId, Set $set) {
                $set('../new_patient', false);
                $set('../patient_id', $patientId);
            }),
        ];
    }
}
