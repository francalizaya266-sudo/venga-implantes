<?php

namespace App\Filament\Resources\WaitlistEntries\Schemas;

use App\Enums\AppointmentStatus;
use App\Enums\DayPeriod;
use App\Filament\Actions\CreateAppointmentAction;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\WaitlistEntries\WaitlistEntryResource;
use App\Filament\Support\SlotPicker;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\WaitlistEntry;
use App\Services\Agenda\Slot;
use App\Services\Booking\WaitlistService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Anotar en lista de espera", en dos columnas como "Nueva cita": a la izquierda quién y qué espera; a la derecha
 * qué va a pasar (si ya hay un horario que le sirve, no hace falta anotarlo) y la nota. Bajo el paciente se avisa,
 * antes de guardar, si ya tiene cita para lo mismo, si ya está anotado o si no tiene celular para ofrecerle el horario.
 */
final class WaitlistEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 5])
            ->components([
                Group::make([
                    self::patientSection(),
                    self::waitSection(),
                ])->columnSpan(['lg' => 3]),
                self::outcomeSection()->columnSpan(['lg' => 2]),
            ]);
    }

    /** Quien llama por primera vez y encuentra todo lleno también se anota: se registra aquí mismo. */
    private static function patientSection(): Section
    {
        return Section::make('Paciente')
            // Al editar, el paciente ya no cambia. (Se mira si hay registro: la ventana de anotar se abre con varios nombres.)
            ->afterHeader([AppointmentForm::patientKindToggle()->visible(fn (?WaitlistEntry $record) => $record === null)])
            ->schema([
                // El paciente que estaba elegido al marcar "Nuevo": si se vuelve a "Existente", regresa.
                Hidden::make('patient_before_new')->dehydrated(false),
                AppointmentForm::patientSelect()
                    ->hiddenLabel()
                    ->hidden(fn (Get $get) => (bool) $get('new_patient'))
                    ->required(fn (Get $get) => ! $get('new_patient'))
                    ->disabled(fn (?WaitlistEntry $record) => $record !== null)
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set, ?WaitlistEntry $record) => self::untilTheDayBefore($get, $set, $record)),
                Grid::make(2)
                    ->statePath('patient')
                    ->visible(fn (Get $get) => (bool) $get('new_patient'))
                    ->schema(self::newPatientFields()),
                self::alreadyWaitingCallout(),
                self::upcomingAppointmentCallout(),
                self::noPhoneCallout(),
            ]);
    }

    private static function waitSection(): Section
    {
        return Section::make('Qué espera')
            ->columns(2)
            ->schema([
                self::treatmentSelect(),
                Select::make('doctor_id')
                    ->label('Doctor')
                    ->placeholder('Cualquier doctor')
                    ->options(fn (Get $get) => self::doctorOptions($get('treatment_id')))
                    ->hidden(fn () => SlotPicker::onlyActiveDoctorId() !== null)
                    ->live(),
                DatePicker::make('earliest_date')
                    ->label('Puede desde')
                    ->native(false)
                    ->displayFormat('D d/m/Y')
                    ->firstDayOfWeek(1)
                    ->closeOnDateSelection()
                    ->default(today())
                    ->minDate(fn (?WaitlistEntry $record) => $record !== null ? null : today())
                    ->required()
                    ->live(),
                DatePicker::make('latest_date')
                    ->label('Hasta')
                    ->native(false)
                    ->displayFormat('D d/m/Y')
                    ->firstDayOfWeek(1)
                    ->closeOnDateSelection()
                    ->afterOrEqual('earliest_date')
                    ->helperText('Déjalo vacío si le sirve cualquier fecha.')
                    ->live(),
                ToggleButtons::make('preferred_period')
                    ->label('Le acomoda')
                    ->options(DayPeriod::class)
                    ->default(DayPeriod::Any->value)
                    // Una sola opción: botones juntos, como "Existente | Nuevo".
                    ->grouped()
                    ->required()
                    ->live()
                    ->columnSpanFull(),
            ]);
    }

    /** Lo que espera. Al cambiarlo, el doctor que no lo hace queda en "cualquiera" y, si ya tiene cita para eso, "Hasta" se ajusta. */
    private static function treatmentSelect(): Select
    {
        return Select::make('treatment_id')
            ->label('Tratamiento')
            ->options(fn () => Treatment::query()->active()->whereHas('doctors')->orderBy('sort_order')->pluck('name', 'id'))
            ->searchable()
            ->required()
            // Con un solo doctor no hay a quién elegir: el tratamiento ocupa la fila.
            ->columnSpan(fn () => SlotPicker::onlyActiveDoctorId() !== null ? 'full' : 1)
            // Anotarlo dos veces para lo mismo haría ofrecerle dos horarios: se corrige la anotación que ya tiene.
            ->rule(fn (Get $get, ?WaitlistEntry $record) => function (string $attribute, mixed $value, Closure $fail) use ($get, $record) {
                if (self::existingEntry($get('patient_id'), $value, $record) !== null) {
                    $fail('Ya está en la lista de espera para ese tratamiento. Si cambió lo que puede, edita esa anotación.');
                }
            })
            ->live()
            ->afterStateUpdated(function (Get $get, Set $set, ?WaitlistEntry $record) {
                // Si el doctor elegido no hace el tratamiento nuevo, queda "cualquier doctor".
                if ($get('doctor_id') && ! array_key_exists((int) $get('doctor_id'), self::doctorOptions($get('treatment_id')))) {
                    $set('doctor_id', null);
                }

                self::untilTheDayBefore($get, $set, $record);
            });
    }

    /**
     * "Qué va a pasar": si ya hay un horario libre que le sirve, se agenda ahí y no hace falta anotarlo; si no, se
     * dice que la alerta llegará y cuántos esperan lo mismo. Y la nota, para lo que el sistema no sabe.
     */
    private static function outcomeSection(): Section
    {
        return Section::make('Qué va a pasar')
            ->extraAttributes(['class' => 'appointment-summary'])
            ->schema([
                Callout::make('Ya hay un horario libre que le sirve')
                    ->key('freeSlot')
                    ->description(fn (Get $get, ?WaitlistEntry $record) => self::freeSlotDescription($get, $record))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('success')
                    ->actions([
                        self::bookFreeSlotAction(),
                        // Si ya tiene cita para lo mismo, se adelanta esa (se abre lista para moverla) en vez de darle otra.
                        Action::make('bringAppointmentForward')
                            ->label('Adelantar su cita')
                            ->url(fn (Get $get) => ($appointment = self::upcomingAppointment($get)) ? AppointmentResource::getUrl('view', ['record' => $appointment, 'action' => 'move']) : null)
                            ->button()
                            ->size(Size::Small)
                            ->visible(fn (Get $get) => ($appointment = self::upcomingAppointment($get)) !== null && (auth()->user()?->can('update', $appointment) ?? false)),
                    ])
                    ->visible(fn (Get $get) => self::freeSlot($get) !== null),
                Text::make(fn (Get $get) => self::noFreeSlotText($get))
                    ->color('gray')
                    ->visible(fn (Get $get) => self::freeSlot($get) === null),
                Text::make(fn (Get $get, ?WaitlistEntry $record) => self::queueText($get, $record))
                    ->color('gray')
                    ->visible(fn (Get $get, ?WaitlistEntry $record) => self::queueText($get, $record) !== null),
                Textarea::make('notes')
                    ->label('Nota')
                    ->placeholder('Ej.: solo puede sábados; prefiere que lo llamen.')
                    ->helperText('No la ve el paciente.')
                    ->rows(3)
                    ->maxLength(500),
            ]);
    }

    /**
     * Los datos del paciente nuevo, como en "Nueva cita", con el celular obligatorio: sin él no se le puede ofrecer
     * por WhatsApp el horario que se libere.
     *
     * @return array<Component>
     */
    private static function newPatientFields(): array
    {
        return array_map(
            fn (Component $field) => $field instanceof TextInput && $field->getName() === 'phone' ? $field->required() : $field,
            AppointmentForm::newPatientFields(),
        );
    }

    /** Ya está anotado para lo mismo: se dice antes de guardar (antes salía como error al final), con su anotación. */
    private static function alreadyWaitingCallout(): Callout
    {
        return Callout::make(fn (Get $get, ?WaitlistEntry $record) => ($entry = self::existingEntry($get('patient_id'), $get('treatment_id'), $record))
            ? 'Ya está en la lista de espera para '.(self::treatmentOf($entry)->name ?? 'ese tratamiento')
            : null)
            ->key('alreadyWaiting')
            ->description(fn (Get $get, ?WaitlistEntry $record) => ($entry = self::existingEntry($get('patient_id'), $get('treatment_id'), $record))
                ? self::alreadyWaitingDescription($entry)
                : null)
            ->warning()
            ->actions([
                Action::make('editExistingEntry')
                    ->label('Editar esa anotación')
                    ->url(fn (Get $get, ?WaitlistEntry $record) => ($entry = self::existingEntry($get('patient_id'), $get('treatment_id'), $record))
                        ? WaitlistEntryResource::getUrl('index', ['tableAction' => 'edit', 'tableActionRecord' => $entry->getKey()])
                        : null),
            ])
            ->visible(fn (Get $get, ?WaitlistEntry $record) => self::existingEntry($get('patient_id'), $get('treatment_id'), $record) !== null);
    }

    private static function alreadyWaitingDescription(WaitlistEntry $entry): string
    {
        /** @var CarbonInterface $createdAt */
        $createdAt = $entry->created_at;
        /** @var CarbonInterface|null $offeredStartsAt */
        $offeredStartsAt = $entry->offered_starts_at;
        /** @var CarbonInterface|null $heldUntil */
        $heldUntil = $entry->held_until;

        $held = $entry->isHolding() && $offeredStartsAt && $heldUntil
            ? ' Tiene apartado el '.$offeredStartsAt->translatedFormat('l j').' a las '.$offeredStartsAt->format('H:i').' hasta las '.$heldUntil->format('H:i').', esperando su respuesta.'
            : '';

        return 'Lo anotaron el '.$createdAt->translatedFormat('j \d\e F').'.'.$held.' Si cambió lo que puede, edita esa anotación en vez de anotarlo otra vez.';
    }

    /** Ya tiene cita para lo mismo: se anota para adelantarla (al salir un horario antes, se mueve esa cita). */
    private static function upcomingAppointmentCallout(): Callout
    {
        return Callout::make(fn (Get $get) => ($appointment = self::upcomingAppointment($get))
            ? 'Ya tiene cita el '.self::startsAt($appointment)->translatedFormat('l j \d\e F').' a las '.self::startsAt($appointment)->format('H:i')
            : null)
            ->key('upcomingAppointment')
            ->description('Se anota para adelantarla: si se libera un horario antes, se mueve esa cita en vez de darle otra. Por eso "Hasta" queda un día antes.')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->visible(fn (Get $get) => self::upcomingAppointment($get) !== null);
    }

    /** Sin celular no hay a dónde mandarle el horario que se libere. */
    private static function noPhoneCallout(): Callout
    {
        return Callout::make('No tiene celular registrado')
            ->key('noPhone')
            ->description('Sin celular no se le puede ofrecer por WhatsApp el horario que se libere. Agrégalo en su ficha.')
            ->warning()
            ->actions([
                Action::make('openPatientFile')
                    ->label('Abrir su ficha')
                    ->url(fn (Get $get) => filled($get('patient_id')) ? PatientResource::getUrl('edit', ['record' => $get('patient_id')]) : null)
                    ->openUrlInNewTab(),
            ])
            ->visible(fn (Get $get) => ($patient = self::chosenPatient($get)) !== null && $patient->account === null);
    }

    /**
     * Si ya tiene cita para ese tratamiento y no se dijo hasta cuándo puede, "Hasta" queda el día antes de esa cita:
     * un horario después no le sirve. Solo al anotarlo; al editar se respeta lo que ya tiene.
     */
    private static function untilTheDayBefore(Get $get, Set $set, ?WaitlistEntry $editing): void
    {
        $appointment = self::upcomingAppointment($get);

        if ($editing !== null || ! $appointment || filled($get('latest_date'))) {
            return;
        }

        $dayBefore = CarbonImmutable::instance(self::startsAt($appointment))->subDay()->startOfDay();

        if ($dayBefore->gte(CarbonImmutable::parse((string) ($get('earliest_date') ?: today()->toDateString()))->startOfDay())) {
            $set('latest_date', $dayBefore->toDateString());
        }
    }

    /** "Martes 24 de noviembre a las 10:00 con Dr. Vega" y qué conviene hacer. */
    private static function freeSlotDescription(Get $get, ?WaitlistEntry $record): ?string
    {
        $free = self::freeSlot($get);

        if (! $free) {
            return null;
        }

        $when = ucfirst($free['slot']->startsAt->translatedFormat('l j \d\e F')).' a las '.$free['slot']->label()
            .(SlotPicker::onlyActiveDoctorId() === null ? ' con '.$free['doctor']->name : '').'.';

        return $when.(self::upcomingAppointment($get) !== null
            ? ' Puedes adelantar su cita ahí de una vez.'
            : ($record ? ' Ofréceselo o agéndalo de una vez.' : ' Mejor agéndalo de una vez: no hace falta anotarlo.'));
    }

    /**
     * "Agendar ahí": "Nueva cita" encima, con el paciente (también el nuevo, con lo ya escrito), el tratamiento, el doctor
     * y ese horario puestos. Al agendar se cierran las dos (y si ya estaba anotado, sale solo de la lista); con la X se
     * vuelve a esta ventana.
     */
    private static function bookFreeSlotAction(): Action
    {
        return CreateAppointmentAction::make('bookFreeSlot')
            ->label('Agendar ahí')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('primary')
            ->button()
            ->size(Size::Small)
            ->fillForm(function (Get $get): array {
                $free = self::freeSlot($get);
                $newPatient = (bool) $get('new_patient');

                return array_replace(
                    CreateAppointmentAction::defaults([
                        'patient' => $newPatient ? null : $get('patient_id'),
                        'treatment' => $get('treatment_id'),
                        'doctor' => $free !== null ? $free['doctor']->getKey() : null,
                        'date' => $free !== null ? $free['slot']->startsAt->toDateString() : null,
                        'time' => $free !== null ? $free['slot']->label() : null,
                    ]),
                    $newPatient ? ['new_patient' => true, 'patient' => (array) $get('patient')] : [],
                );
            })
            ->cancelParentActions()
            ->visible(fn (Get $get) => self::freeSlot($get) !== null && self::upcomingAppointment($get) === null && AppointmentResource::canCreate());
    }

    /** Sin horario libre: anotarlo sí sirve. Con un plazo muy corto, se dice que es difícil. */
    private static function noFreeSlotText(Get $get): string
    {
        if (blank($get('treatment_id'))) {
            return 'Elige el tratamiento: aquí verás si ya hay un horario libre que le sirva, para no anotarlo en vano.';
        }

        $appointment = self::upcomingAppointment($get);

        // Con una cita para lo mismo, lo que sirve es adelantarla: si ya es en pocos días, se dice que es difícil.
        if ($appointment !== null && self::startsAt($appointment)->lte(today()->addDays(WaitlistService::SEARCH_DAYS))) {
            return self::startsAt($appointment)->lte(today()->addDays(3)->endOfDay())
                ? 'No hay nada libre antes de su cita, y ya es en pocos días: es difícil adelantarla.'
                : 'No hay nada libre antes de su cita: anotarlo sí sirve. Si se libera un horario antes, te llega la alerta para adelantársela.';
        }

        $until = filled($get('latest_date')) ? CarbonImmutable::parse((string) $get('latest_date')) : null;

        return match (true) {
            // Solo hoy (llegó sin cita, o el día que pidió está lleno): queda que alguien cancele o no llegue.
            $until !== null && $until->isSameDay(today()) => 'Hoy no queda nada libre. Si alguien cancela o no llega, te llega la alerta para ofrecérselo.',
            $until !== null && $until->lte(today()->addDays(3)) => 'No hay nada libre en ese plazo, y es corto: es difícil que se libere algo. Si puede esperar más, amplía "Hasta".',
            default => 'No hay nada libre que le sirva en las próximas '.intdiv(WaitlistService::SEARCH_DAYS, 7).' semanas: anotarlo sí sirve. Cuando se libere un horario, te llega la alerta para ofrecérselo.',
        };
    }

    /** Cuántos esperan lo mismo: lo que se le puede decir al paciente de verdad. */
    private static function queueText(Get $get, ?WaitlistEntry $record): ?string
    {
        $treatment = filled($get('treatment_id')) ? Treatment::find($get('treatment_id')) : null;

        if (! $treatment instanceof Treatment || self::freeSlot($get) !== null) {
            return null;
        }

        $memo = 'waitlist-form.queue.'.$treatment->getKey().'.'.$record?->getKey().'.'.$get('patient_id');

        if (! request()->attributes->has($memo)) {
            request()->attributes->set($memo, WaitlistEntry::query()
                ->active()
                ->where('treatment_id', $treatment->getKey())
                ->when(filled($get('patient_id')), fn (Builder $query) => $query->where('patient_id', '!=', $get('patient_id')))
                ->when($record, fn (Builder $query, WaitlistEntry $editing) => $query->whereKeyNot($editing->getKey()))
                ->count());
        }

        $waiting = (int) request()->attributes->get($memo);

        return match ($waiting) {
            0 => 'Nadie más está en espera para '.$treatment->name.': cuando se libere un horario, sale primero en la alerta.',
            1 => 'Hay 1 persona más en espera para '.$treatment->name.'. En la alerta sale primero quien espera hace más tiempo.',
            default => 'Hay '.$waiting.' personas más en espera para '.$treatment->name.'. En la alerta sale primero quien espera hace más tiempo.',
        };
    }

    /**
     * El primer horario libre que le sirve con lo escrito en la ventana (la misma búsqueda que "Ofrecer horario").
     * Se pide varias veces al pintar la ventana: se busca una sola vez por pedido.
     *
     * @return array{doctor: Doctor, slot: Slot}|null
     */
    private static function freeSlot(Get $get): ?array
    {
        if (blank($get('treatment_id'))) {
            return null;
        }

        $period = $get('preferred_period');
        $entry = new WaitlistEntry([
            'patient_id' => ! $get('new_patient') && filled($get('patient_id')) ? (int) $get('patient_id') : null,
            'treatment_id' => (int) $get('treatment_id'),
            'doctor_id' => filled($get('doctor_id')) ? (int) $get('doctor_id') : null,
            'earliest_date' => filled($get('earliest_date')) ? (string) $get('earliest_date') : today()->toDateString(),
            'latest_date' => filled($get('latest_date')) ? (string) $get('latest_date') : null,
            'preferred_period' => $period instanceof DayPeriod ? $period : (DayPeriod::tryFrom((string) $period) ?? DayPeriod::Any),
        ]);

        $memo = 'waitlist-form.free-slot.'.md5(serialize($entry->getAttributes()));

        if (! request()->attributes->has($memo)) {
            $free = app(WaitlistService::class)->firstSlot($entry);
            $appointment = self::upcomingAppointment($get);

            // Con una cita para lo mismo, solo sirve un horario antes de esa cita (aunque se haya vaciado "Hasta").
            request()->attributes->set($memo, $free !== null && $appointment !== null && $free['slot']->startsAt->gte(self::startsAt($appointment)) ? null : $free);
        }

        return request()->attributes->get($memo);
    }

    /** Su próxima cita para ese tratamiento (la que se adelantaría), como la busca la lista de espera. */
    private static function upcomingAppointment(Get $get): ?Appointment
    {
        $patientId = $get('patient_id');
        $treatmentId = $get('treatment_id');

        if ($get('new_patient') || blank($patientId) || blank($treatmentId)) {
            return null;
        }

        $memo = 'waitlist-form.upcoming.'.$patientId.'.'.$treatmentId;

        if (! request()->attributes->has($memo)) {
            request()->attributes->set($memo, AppointmentResource::getEloquentQuery()
                ->where('patient_id', $patientId)
                ->where('treatment_id', $treatmentId)
                ->where('starts_at', '>=', now())
                ->whereIn('status', AppointmentStatus::occupying())
                ->orderBy('starts_at')
                ->first());
        }

        return request()->attributes->get($memo);
    }

    /** Su anotación que sigue en espera para ese tratamiento, sin contar la que se está editando. */
    private static function existingEntry(mixed $patientId, mixed $treatmentId, ?WaitlistEntry $editing): ?WaitlistEntry
    {
        if (blank($patientId) || blank($treatmentId)) {
            return null;
        }

        return WaitlistEntry::query()
            ->active()
            ->with('treatment')
            ->where('patient_id', $patientId)
            ->where('treatment_id', $treatmentId)
            ->when($editing, fn (Builder $query) => $query->whereKeyNot($editing->getKey()))
            ->oldest()
            ->first();
    }

    /** El paciente ya registrado que se eligió, con su cuenta (su celular). Una sola vez por pedido. */
    private static function chosenPatient(Get $get): ?Patient
    {
        $patientId = $get('patient_id');

        if ($get('new_patient') || blank($patientId)) {
            return null;
        }

        $memo = 'waitlist-form.patient.'.$patientId;

        if (! request()->attributes->has($memo)) {
            request()->attributes->set($memo, Patient::query()->with('account')->find($patientId));
        }

        return request()->attributes->get($memo);
    }

    private static function treatmentOf(WaitlistEntry $entry): ?Treatment
    {
        /** @var Treatment|null $treatment */
        $treatment = $entry->treatment;

        return $treatment;
    }

    /** La hora de la cita, con su tipo (el análisis estático no lo ve). */
    private static function startsAt(Appointment $appointment): CarbonInterface
    {
        /** @var CarbonInterface $startsAt */
        $startsAt = $appointment->starts_at;

        return $startsAt;
    }

    /** @return array<int, string> Doctores activos que hacen el tratamiento (todos, si aún no se elige). */
    private static function doctorOptions(mixed $treatmentId): array
    {
        return Doctor::query()
            ->active()
            ->when(filled($treatmentId), fn (Builder $query) => $query->whereHas('treatments', fn (Builder $treatments) => $treatments->whereKey($treatmentId)))
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }
}
