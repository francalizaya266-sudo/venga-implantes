<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Enums\AppointmentOrigin;
use App\Enums\AppointmentStatus;
use App\Enums\DocumentType;
use App\Enums\PatientRelationship;
use App\Enums\PlanPaymentMode;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Patients\Schemas\PatientForm;
use App\Filament\Support\SlotPicker;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\Booking\AppointmentService;
use App\Services\Clinical\TreatmentPlanService;
use App\Support\Dates;
use App\Support\Money;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\StateCasts\BooleanStateCast;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
     */
    public static function patientSection(bool $forToday = false): Section
    {
        return Section::make('Paciente')
            // Arriba a la derecha, como en la ficha: uno ya registrado o uno nuevo que se registra aquí mismo.
            ->afterHeader([
                ToggleButtons::make('new_patient')
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
                    }),
            ])
            ->schema([
                // El paciente que estaba elegido al marcar "paciente nuevo": si se desmarca, vuelve.
                Hidden::make('patient_before_new')->dehydrated(false),
                self::patientSelect()
                    ->hiddenLabel()
                    ->hidden(fn (Get $get) => (bool) $get('new_patient'))
                    ->required(fn (Get $get) => ! $get('new_patient'))
                    ->helperText(fn (Get $get) => self::upcomingAppointmentHint($get('patient_id'), afterToday: $forToday))
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
                Grid::make(2)
                    ->statePath('patient')
                    ->visible(fn (Get $get) => (bool) $get('new_patient'))
                    ->schema(self::newPatientFields()),
                self::planItemSelect(),
                self::planAcceptedCheckbox(),
                // Junto al paciente, donde recepción lo pregunta. Solo a quien viene por primera vez: a los
                // demás ya se les preguntó (los reportes usan el origen del paciente).
                Select::make('origin')
                    ->label('¿Cómo nos encontró?')
                    ->options(AppointmentOrigin::class)
                    ->visible(fn (Get $get) => self::isFirstTime($get)),
            ]);
    }

    /**
     * "Ya tiene cita mañana a las 10:00 · Limpieza dental." La próxima que le falta, entre las que quien mira
     * puede ver (el doctor, solo las de su agenda).
     *
     * @param  bool  $afterToday  Sin contar las de hoy.
     */
    private static function upcomingAppointmentHint(mixed $patientId, bool $afterToday): ?string
    {
        if (blank($patientId)) {
            return null;
        }

        $next = AppointmentResource::getEloquentQuery()
            ->where('patient_id', $patientId)
            ->where('starts_at', '>=', $afterToday ? today()->addDay() : today())
            ->whereIn('status', AppointmentStatus::changeable())
            ->orderBy('starts_at')
            ->first();

        if (! $next) {
            return null;
        }

        $day = match (true) {
            $next->starts_at->isToday() => 'hoy',
            $next->starts_at->isTomorrow() => 'mañana',
            default => 'el '.$next->starts_at->translatedFormat('D j M'),
        };

        return 'Ya tiene cita '.$day.' a las '.$next->starts_at->format('H:i').' · '.$next->treatment->name.'.';
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
     * Procedimientos pendientes de los planes vigentes del paciente. Recepción ve el tratamiento;
     * la pieza solo la ve quien puede ver lo clínico.
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

        return app(TreatmentPlanService::class)->workableItems($patient)
            ->mapWithKeys(fn (TreatmentPlanItem $item) => [$item->getKey() => $item->label(withTooth: $user->canSeeClinical())
                .($item->sessions_planned > 1 ? ' · sesión '.min($item->sessions_done + 1, $item->sessions_planned).' de '.$item->sessions_planned : '')
                .($item->in_payment_schedule ? ' · ya incluida en su forma de pago' : '')])
            ->all();
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
                    PatientForm::documentNumberInput()
                        ->label('Número')
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
