<?php

namespace App\Filament\Resources\WaitlistEntries;

use App\Enums\DayPeriod;
use App\Enums\PatientRelationship;
use App\Enums\WaitlistStatus;
use App\Filament\Actions\SendWhatsAppAction;
use App\Filament\Forms\ChoiceChips;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Support\SlotPicker;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\WaitlistEntry;
use App\Services\Agenda\Slot;
use App\Services\Booking\WaitlistService;
use App\Services\Patients\PatientRegistrar;
use App\Services\WhatsApp\WhatsAppOutbox;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/** Anotarlo en la lista, ofrecerle un horario por WhatsApp (queda apartado mientras responde), agendarlo cuando dice que sí, liberarlo o retirarlo. */
final class WaitlistEntryActions
{
    /** Segundos que se ve el aviso de un horario que quedó libre: no se queda tapando los botones de arriba. */
    public const FREED_NOTICE_SECONDS = 20;

    /** Cuántos días de su plazo se ven como botones en "Ofrecer horario". */
    private const DAYS_SHOWN = 7;

    /**
     * Anotar a alguien en la lista: la misma ventana en la lista de espera y en "Hoy", sin salir de donde se está. En
     * dos columnas, como "Nueva cita": la derecha dice qué va a pasar, porque quien anota no vuelve a mirar la lista
     * hasta que se libera un horario (y si ya hay uno que le sirve, no hace falta anotarlo). El paciente nuevo se
     * registra en la misma ventana.
     */
    public static function add(string $name = 'create'): CreateAction
    {
        return CreateAction::make($name)
            ->model(WaitlistEntry::class)
            ->label('Anotar paciente')
            ->visible(fn () => self::canAdd())
            ->modalHeading('Anotar en lista de espera')
            ->modalDescription('Para quien quiere un horario antes o un día que hoy está lleno.')
            ->modalWidth(Width::FiveExtraLarge)
            ->extraModalWindowAttributes(['class' => 'appointment-window'])
            ->stickyModalFooter()
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalSubmitActionLabel('Anotar')
            ->modalSubmitAction(fn (Action $action) => $action->size(Size::Large))
            ->schema(fn (Schema $schema) => WaitlistEntryResource::form($schema))
            ->fillForm(fn (array $arguments) => self::defaults($arguments))
            ->createAnother(false)
            ->mutateDataUsing(fn (array $data) => $data + [
                'status' => WaitlistStatus::Waiting,
                'created_by_user_id' => auth()->id(),
            ])
            ->using(fn (array $data) => self::create($data))
            ->successNotification(fn (Notification $notification, WaitlistEntry $record) => $notification
                ->title($record->patient->full_name.' quedó en la lista de espera')
                ->body('Cuando se libere un horario que le sirva, llegará la alerta para ofrecérselo.'));
    }

    /**
     * "Anotar en lista de espera" desde otra ventana que no encontró horario ("Nueva cita" con el día lleno, "Llegó
     * sin cita" sin hueco hoy): se abre encima, con el paciente (también el nuevo, con lo ya escrito), el tratamiento,
     * el doctor y el plazo puestos. Al anotarlo se cierran las dos; con la X se vuelve a la de antes, tal como estaba.
     *
     * @param  Closure(Get): array<string, mixed>  $with  Lo que se sabe en esa ventana, como los argumentos de defaults().
     */
    public static function addFrom(string $name, Closure $with): CreateAction
    {
        return self::add($name)
            ->label('Anotar en lista de espera')
            ->icon(Heroicon::OutlinedQueueList)
            ->fillForm(fn (Get $get) => self::defaults($with($get)))
            ->cancelParentActions();
    }

    public static function canAdd(): bool
    {
        return auth()->user()?->can('create', WaitlistEntry::class) ?? false;
    }

    /**
     * Cómo se abre la ventana: con lo que ya se sabe del paciente y de lo que espera, y desde hoy. Lo que no sirve
     * (un día que ya pasó, un tratamiento o doctor inactivo) se descarta.
     *
     * @param  array<string, mixed>  $with  patient, new_patient y patient_data (lo escrito del paciente nuevo), treatment, doctor, from y until (Y-m-d).
     * @return array<string, mixed>
     */
    public static function defaults(array $with = []): array
    {
        $date = fn (mixed $value) => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && CarbonImmutable::parse($value)->gte(today()) ? $value : null;
        $newPatient = (bool) ($with['new_patient'] ?? false);
        $patientData = is_array($with['patient_data'] ?? null) ? $with['patient_data'] : [];
        $from = $date($with['from'] ?? null) ?? today()->toDateString();
        $until = $date($with['until'] ?? null);

        return [
            'new_patient' => $newPatient,
            'patient_id' => ! $newPatient && filled($with['patient'] ?? null) ? Patient::query()->whereKey((int) $with['patient'])->value('id') : null,
            'patient' => $patientData + ['relationship' => PatientRelationship::Self->value],
            'treatment_id' => Treatment::query()->active()->whereKey((int) ($with['treatment'] ?? 0))->value('id'),
            'doctor_id' => SlotPicker::onlyActiveDoctorId() === null ? Doctor::query()->active()->whereKey((int) ($with['doctor'] ?? 0))->value('id') : null,
            'earliest_date' => $from,
            'latest_date' => $until !== null && $until >= $from ? $until : null,
            'preferred_period' => DayPeriod::Any->value,
        ];
    }

    /**
     * Si es nuevo, se registra en la misma transacción: si algo falla, no queda un paciente suelto.
     *
     * @param  array<string, mixed>  $data
     */
    private static function create(array $data): WaitlistEntry
    {
        return DB::transaction(function () use ($data): WaitlistEntry {
            $patientId = ($data['new_patient'] ?? false)
                ? app(PatientRegistrar::class)->register(array_filter($data['patient'] ?? [], fn (mixed $value) => $value !== null && $value !== ''))->getKey()
                : $data['patient_id'];

            return WaitlistEntry::create(['patient_id' => $patientId] + Arr::except($data, ['new_patient', 'patient', 'patient_id', 'patient_before_new']));
        });
    }

    /**
     * Llega con el primer horario libre que le sirve (o con el que se tocó en la agenda). En dos columnas, como "Nueva
     * cita": a la izquierda los días de su plazo como botones, cada uno con cuántos horarios le sirven, y los horarios
     * a un toque; a la derecha el mensaje, que se vuelve a escribir al cambiar algo, y hasta cuándo queda apartado. Un
     * día sin lugar no deja sin salida: "Ir al" siguiente que lo tiene. Al abrir WhatsApp queda como avisado.
     */
    public static function offer(): Action
    {
        return Action::make('offer')
            ->label('Ofrecer horario')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('success')
            ->visible(fn (WaitlistEntry $record) => $record->isActive() && self::hasWhatsApp($record))
            // El paso que toca mientras no tenga un horario apartado (también si no tomó el que se le ofreció).
            ->extraAttributes(fn (?WaitlistEntry $record) => $record !== null && ! $record->isHolding() ? ['class' => 'appt-main-step'] : [], merge: true)
            ->authorize('update')
            ->modalHeading(fn (WaitlistEntry $record) => 'Ofrecer un horario a '.$record->patient->full_name)
            ->modalDescription('Elige el día y el horario. Se abrirá WhatsApp con el mensaje listo: solo presiona Enviar.')
            ->modalWidth(Width::SixExtraLarge)
            ->extraModalWindowAttributes(['class' => 'appointment-window'])
            ->stickyModalFooter()
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalSubmitActionLabel('Abrir WhatsApp')
            // Como "Enviar por WhatsApp": la pestaña se abre en el mismo toque, para que el navegador no la bloquee.
            ->modalSubmitAction(fn (Action $action) => $action->size(Size::Large)->extraAttributes(SendWhatsAppAction::OPENS_TAB_ON_CLICK))
            // Cerrarla no cancela nada: se vuelve a la lista.
            ->modalCancelActionLabel('Volver')
            ->fillForm(fn (WaitlistEntry $record, array $arguments) => self::offerDefaults($record, $arguments))
            ->schema(fn (Schema $schema, WaitlistEntry $record) => $schema
                ->columns(['default' => 1, 'lg' => 5])
                ->components([
                    Group::make(self::offerFields($record))->columnSpan(['lg' => 3]),
                    Section::make('Mensaje')
                        ->extraAttributes(['class' => 'appointment-summary'])
                        ->schema([
                            Textarea::make('body')
                                ->hiddenLabel()
                                ->placeholder('Elige un horario y aquí queda el mensaje listo.')
                                ->rows(9)
                                ->required(),
                            Text::make(fn (Get $get) => self::holdText($record, $get))
                                ->color('gray'),
                        ])
                        ->columnSpan(['lg' => 2]),
                ]))
            ->action(fn (array $data, WaitlistEntry $record, Action $action, Component $livewire) => self::sendOffer($data, $record, $action, $livewire));
    }

    /**
     * Con qué se abre "Ofrecer horario": el primer horario libre que le sirve, o el que se tocó en la agenda.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function offerDefaults(WaitlistEntry $record, array $arguments): array
    {
        $service = app(WaitlistService::class);
        $first = $service->firstSlot($record);
        $state = [
            'treatment_id' => $record->treatment_id,
            'doctor_id' => $first['doctor']->id ?? $record->doctor_id ?? $service->doctorsFor($record)->first()?->id,
            'date' => ($first['slot']->startsAt ?? $record->firstDate())->toDateString(),
            'slot' => $first ? $first['slot']->label() : null,
        ];

        // Desde la agenda llega la hora que se vio libre ese día.
        if (isset($arguments['doctor'], $arguments['date'], $arguments['time'])) {
            $state = ['doctor_id' => (int) $arguments['doctor'], 'date' => (string) $arguments['date'], 'slot' => (string) $arguments['time']] + $state;
        }

        return $state + ['body' => self::body($record, $state)];
    }

    /**
     * Abre WhatsApp con el mensaje y deja el horario apartado. Si mientras tanto se ocupó (o no tiene celular), no se
     * envía nada y se dice por qué.
     *
     * @param  array<string, mixed>  $data
     */
    private static function sendOffer(array $data, WaitlistEntry $record, Action $action, Component $livewire): void
    {
        $service = app(WaitlistService::class);
        $doctor = Doctor::findOrFail($data['doctor_id']);
        $treatment = Treatment::findOrFail($data['treatment_id'] ?? $record->treatment_id);
        $startsAt = CarbonImmutable::parse($data['date'])->setTimeFromTimeString($data['slot']);

        // Mientras se escribía, el horario pudo ocuparse.
        $stillFree = $service->slotsFor($record, $doctor, $treatment, $startsAt)
            ->contains(fn (Slot $slot) => $slot->startsAt->eq($startsAt));

        if (! $stillFree) {
            SendWhatsAppAction::closeTab($livewire);
            Notification::make()->danger()->title('Ese horario ya no está libre. Elige otro.')->send();
            $action->halt();
        }

        $message = $service->offerDraft($record, $doctor, $treatment, $startsAt);

        if (! $message) {
            SendWhatsAppAction::closeTab($livewire);
            Notification::make()->danger()->title('El paciente no tiene celular registrado.')->send();
            $action->halt();
        }

        $message->save();
        $url = app(WhatsAppOutbox::class)->clickToChatUrl($message, auth()->user(), $data['body']);
        $service->markOffered($record, $doctor, $treatment, $startsAt);

        SendWhatsAppAction::goToTab($url, $livewire);
        self::offerOpenedNotification($record, $url)->send();
    }

    /**
     * Lo que se elige en "Ofrecer horario": el tratamiento (si se anotó para cualquiera), el doctor, el día y el horario.
     *
     * @return list<Field>
     */
    private static function offerFields(WaitlistEntry $entry): array
    {
        return [
            Select::make('treatment_id')
                ->label('Tratamiento')
                ->options(fn () => Treatment::query()->active()->whereHas('doctors')->orderBy('sort_order')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->hidden($entry->treatment_id !== null)
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::refresh($entry, $get, $set, keepSlot: false, toDayWithSlots: true)),
            Select::make('doctor_id')
                ->label('Doctor')
                ->options(fn (Get $get) => self::doctorOptions($entry, $get))
                ->required()
                ->live()
                // Con otro doctor, si ese día no tiene lugar con él, pasa solo al siguiente que sí.
                ->afterStateUpdated(fn (Get $get, Set $set) => self::refresh($entry, $get, $set, keepSlot: false, toDayWithSlots: true)),
            // Los días de su plazo (hasta siete), cada uno con cuántos horarios le sirven. Uno más lejos, con "Otro día".
            ChoiceChips::make('date')
                ->label('Día')
                ->options(fn (Get $get) => self::dayOptions($entry, $get('date')))
                ->descriptions(fn (Get $get) => self::freeSlotsPerDay($entry, $get))
                ->extraAttributes(['class' => 'day-chips'])
                ->hintAction(self::otherDayAction($entry))
                ->required()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::refresh($entry, $get, $set, keepSlot: false)),
            ChoiceChips::make('slot')
                ->label('Horario libre')
                ->options(fn (Get $get) => self::offerSlots($entry, $get)->mapWithKeys(fn (Slot $slot) => [$slot->label() => $slot->label()])->all())
                ->groups(fn (Get $get) => self::offerSlots($entry, $get)->mapWithKeys(fn (Slot $slot) => [$slot->label() => DayPeriod::Morning->includes($slot->startsAt) ? 'Mañana' : 'Tarde'])->all())
                ->highlighted(fn (Get $get) => self::recommendedSlots($entry, $get))
                ->descriptions(fn (Get $get) => array_fill_keys(self::recommendedSlots($entry, $get), 'Recomendada'))
                ->extraAttributes(['class' => 'slot-chips'])
                ->helperText(fn (Get $get) => self::slotsHint($entry, $get))
                // Día sin lugar: un toque lleva al siguiente que lo tiene (antes quedaba "No hay horarios libres" sin salida).
                ->hintAction(Action::make('nextDayWithSlots')
                    ->label(fn (Get $get) => 'Ir al '.self::nextDayWithSlots($entry, $get)?->translatedFormat('l j'))
                    ->link()
                    ->visible(fn (Get $get) => self::offerSlots($entry, $get)->isEmpty() && self::nextDayWithSlots($entry, $get) !== null)
                    ->action(function (Get $get, Set $set) use ($entry): void {
                        if ($next = self::nextDayWithSlots($entry, $get)) {
                            $set('date', $next->toDateString());
                            self::refresh($entry, $get, $set, keepSlot: false);
                        }
                    }))
                ->required()
                // La hora elegida ya no está entre las libres: alguien la tomó mientras se llenaba la ventana.
                ->validationMessages(['in' => 'Ese horario ya no está libre: lo tomaron mientras tanto. Elige otro.'])
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::refresh($entry, $get, $set, keepSlot: true)),
        ];
    }

    /** "Otro día": uno de su plazo más allá de los que se ven, en un calendario. */
    private static function otherDayAction(WaitlistEntry $entry): Action
    {
        return Action::make('otherDay')
            ->label('Otro día')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->link()
            ->modalHeading('Otro día')
            ->modalWidth(Width::Small)
            // Encima de "Ofrecer horario", sin cerrarla mientras se elige.
            ->overlayParentActions()
            ->modalSubmitActionLabel('Elegir')
            ->fillForm(fn (Get $get) => ['day' => $get('date')])
            ->schema([
                DatePicker::make('day')
                    ->label('Día')
                    ->native(false)
                    ->displayFormat('D d/m/Y')
                    ->firstDayOfWeek(1)
                    ->closeOnDateSelection()
                    ->minDate($entry->firstDate())
                    ->maxDate($entry->latest_date)
                    ->required(),
            ])
            ->action(function (array $data, Get $get, Set $set) use ($entry): void {
                $set('date', CarbonImmutable::parse($data['day'])->toDateString());
                self::refresh($entry, $get, $set, keepSlot: false);
            });
    }

    /**
     * Si ya tiene una cita para lo mismo, se abre "Mover" en esa cita, con el horario que se le ofreció si sigue en pie;
     * si no, "Nueva cita" con el paciente, el tratamiento y ese horario. Al agendarla o moverla sale solo de la lista.
     */
    public static function book(): Action
    {
        return Action::make('book')
            ->label(fn (WaitlistEntry $record) => $record->upcoming_appointment_id !== null ? 'Adelantar cita' : 'Agendar')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->visible(fn (WaitlistEntry $record) => $record->isActive())
            // El paso que toca cuando ya tiene el horario apartado (dijo que sí), o cuando no hay WhatsApp para ofrecérselo.
            ->extraAttributes(fn (?WaitlistEntry $record) => $record !== null && ($record->isHolding() || ! self::hasWhatsApp($record)) ? ['class' => 'appt-main-step'] : [], merge: true)
            ->url(fn (WaitlistEntry $record) => $record->upcoming_appointment_id !== null
                ? AppointmentResource::getUrl('view', array_filter([
                    'record' => $record->upcoming_appointment_id,
                    'action' => 'move',
                    'actionArguments' => self::offeredSlotArguments($record),
                ]))
                : AppointmentResource::newUrl(array_filter([
                    'patient' => $record->patient_id,
                    'treatment' => $record->treatment_id,
                    'doctor' => $record->offered_doctor_id ?? $record->doctor_id,
                    'date' => ($record->offered_starts_at?->isFuture() ? $record->offered_starts_at : $record->firstDate())->toDateString(),
                    'time' => $record->offered_starts_at?->isFuture() ? $record->offered_starts_at->format('H:i') : null,
                ])));
    }

    /**
     * El horario que se le ofreció, para que "Mover" llegue con él elegido: solo si sigue apartado y no pasó.
     *
     * @return array{doctor: int|null, date: string, time: string}|null
     */
    private static function offeredSlotArguments(WaitlistEntry $record): ?array
    {
        /** @var CarbonInterface|null $offeredAt */
        $offeredAt = $record->offered_starts_at;

        if (! $record->isHolding() || $offeredAt === null || ! $offeredAt->isFuture()) {
            return null;
        }

        return ['doctor' => $record->offered_doctor_id, 'date' => $offeredAt->toDateString(), 'time' => $offeredAt->format('H:i')];
    }

    /** No quiere el horario ofrecido (o ya se sabe que no viene): queda libre para otro y él sigue en la lista. */
    public static function release(): Action
    {
        return Action::make('release')
            ->label('Liberar el horario')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('gray')
            ->visible(fn (WaitlistEntry $record) => $record->isHolding())
            ->authorize('update')
            ->requiresConfirmation()
            ->modalHeading(fn (WaitlistEntry $record) => '¿Liberar el horario apartado para '.$record->patient->first_name.'?')
            ->modalDescription('Queda libre para ofrecérselo a otro o para la web. Sigue en la lista de espera.')
            ->modalSubmitActionLabel('Liberar')
            ->action(function (WaitlistEntry $record) {
                app(WaitlistService::class)->release($record);

                self::freedHoldNotification('Horario liberado', $record)->send();
            });
    }

    public static function remove(): Action
    {
        return Action::make('remove')
            ->label('Retirar de la lista')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (WaitlistEntry $record) => $record->isActive())
            ->authorize('update')
            ->modalHeading('Retirar de la lista de espera')
            ->modalSubmitActionLabel('Retirar')
            ->schema([
                Textarea::make('reason')
                    ->label('Motivo')
                    ->placeholder('Ej.: ya no puede, consiguió otro horario.')
                    ->rows(2)
                    ->maxLength(200),
            ])
            ->action(function (array $data, WaitlistEntry $record) {
                $wasHolding = $record->isHolding();
                app(WaitlistService::class)->remove($record, $data['reason'] ?? null);

                ($wasHolding
                    ? self::freedHoldNotification('Retirado de la lista de espera', $record)
                    : Notification::make()->success()->title('Retirado de la lista de espera'))->send();
            });
    }

    /**
     * Se dejó libre a mano el horario que tenía apartado: cuál, y a quién de la lista ofrecérselo ahora con su botón,
     * como cuando el apartado vence solo.
     */
    private static function freedHoldNotification(string $title, WaitlistEntry $entry): Notification
    {
        $notification = Notification::make()->success()->title($title);
        $notice = app(WaitlistService::class)->releasedHoldNotice($entry);

        if ($notice === null) {
            return $notification;
        }

        $next = $notice['next'];

        if ($next === null) {
            return $notification->body($notice['when'].' quedó libre para la agenda y la web.');
        }

        return $notification
            ->body($notice['when'].' quedó libre. '.$next['text'])
            ->actions([
                Action::make('offerNext')
                    ->label($next['link']['label'])
                    ->url($next['link']['url'])
                    ->button()
                    ->close(),
            ])
            ->duration(self::FREED_NOTICE_SECONDS * 1000);
    }

    /**
     * Se abrió WhatsApp con la oferta y el horario quedó apartado. Si el navegador no dejó abrirlo, queda el botón; si
     * al final no se le envió, se dice cómo dejar el horario para otro (antes quedaba apartado sin que él lo supiera).
     */
    private static function offerOpenedNotification(WaitlistEntry $entry, string $url): Notification
    {
        /** @var Patient $patient */
        $patient = $entry->patient;
        /** @var CarbonImmutable $startsAt */
        $startsAt = $entry->offered_starts_at;
        /** @var CarbonImmutable $heldUntil */
        $heldUntil = $entry->held_until;

        return Notification::make()
            ->success()
            ->title('Oferta para '.$patient->full_name.' abierta en WhatsApp')
            ->body('El '.Dates::dayAndTime($startsAt).' queda apartado para '.$patient->first_name.' hasta el '.Dates::dayAndTime($heldUntil)
                .'. Si al final no se le envió, toca «Liberar el horario» en la lista de espera para ofrecérselo a otro.')
            ->actions([
                Action::make('reopen')->label('Abrir de nuevo')->url($url, shouldOpenInNewTab: true)->link(),
            ])
            ->persistent();
    }

    /**
     * Al cambiar algo, la hora pasa a la primera libre (si la elegida ya no sirve) y el mensaje se reescribe. Con otro
     * doctor o tratamiento, un día que quedó sin lugar pasa solo al siguiente que lo tiene (el día elegido a mano, no).
     */
    private static function refresh(WaitlistEntry $entry, Get $get, Set $set, bool $keepSlot, bool $toDayWithSlots = false): void
    {
        if ($toDayWithSlots && self::offerSlots($entry, $get)->isEmpty() && ($next = self::nextDayWithSlots($entry, $get))) {
            $set('date', $next->toDateString());
        }

        $options = self::offerSlots($entry, $get)->map(fn (Slot $slot) => $slot->label());

        if (! $keepSlot || ! $options->contains((string) $get('slot'))) {
            $set('slot', $options->first());
        }

        $set('body', self::body($entry, [
            'treatment_id' => $get('treatment_id') ?? $entry->treatment_id,
            'doctor_id' => $get('doctor_id'),
            'date' => $get('date'),
            'slot' => $get('slot'),
        ]));
    }

    /**
     * Los botones de los días: desde el primero en que puede venir, hasta siete y sin pasar de su plazo, más el
     * elegido si cae más lejos.
     *
     * @return array<string, string> "2026-11-19" => "Mañana"
     */
    private static function dayOptions(WaitlistEntry $entry, mixed $chosen): array
    {
        $first = CarbonImmutable::instance($entry->firstDate())->startOfDay();
        $days = collect(range(0, self::DAYS_SHOWN - 1))
            ->map(fn (int $offset) => $first->addDays($offset))
            ->filter(fn (CarbonImmutable $day) => $entry->latest_date === null || $day->lte($entry->latest_date))
            ->map(fn (CarbonImmutable $day) => $day->toDateString());

        if (self::isDate($chosen)) {
            $days->push($chosen);
        }

        return $days->unique()->sort()->mapWithKeys(fn (string $date) => [$date => SlotPicker::dayLabel(CarbonImmutable::parse($date))])->all();
    }

    /**
     * Bajo cada día, cuántos horarios le sirven con el doctor elegido.
     *
     * @return array<string, string> "2026-11-19" => "3 libres"
     */
    private static function freeSlotsPerDay(WaitlistEntry $entry, Get $get): array
    {
        if (! self::canSearch($entry, $get)) {
            return [];
        }

        return collect(self::dayOptions($entry, $get('date')))
            ->map(fn (string $label, string $date) => match ($count = self::slotsOn($entry, $get, $date)->count()) {
                0 => 'Sin lugar',
                1 => '1 libre',
                default => "{$count} libres",
            })
            ->all();
    }

    /** @return Collection<int, Slot> Los horarios que le sirven el día elegido. */
    private static function offerSlots(WaitlistEntry $entry, Get $get): Collection
    {
        return self::slotsOn($entry, $get, $get('date'));
    }

    /** @return list<string> Los horarios pegados a otra cita: no dejan huecos en la agenda. */
    private static function recommendedSlots(WaitlistEntry $entry, Get $get): array
    {
        return self::offerSlots($entry, $get)->filter(fn (Slot $slot) => $slot->recommended)->map(fn (Slot $slot) => $slot->label())->values()->all();
    }

    /**
     * Los horarios libres de un día que le sirven (su plazo, la parte del día que pidió y antes de la cita que ya
     * tiene). Lo piden los botones, sus grupos, los días y los textos al pintar la ventana: se busca una sola vez.
     *
     * @return Collection<int, Slot>
     */
    private static function slotsOn(WaitlistEntry $entry, Get $get, mixed $date): Collection
    {
        $doctorId = $get('doctor_id');
        $treatmentId = $get('treatment_id') ?? $entry->treatment_id;

        if (blank($doctorId) || blank($treatmentId) || ! self::isDate($date)) {
            return collect();
        }

        $memo = 'waitlist-offer.slots.'.implode('|', [$entry->getKey(), $doctorId, $treatmentId, $date]);

        if (! request()->attributes->has($memo)) {
            $doctor = SlotPicker::chosenDoctor($doctorId);
            $treatment = SlotPicker::chosenTreatment($treatmentId);

            request()->attributes->set($memo, $doctor && $treatment
                ? app(WaitlistService::class)->slotsFor($entry, $doctor, $treatment, CarbonImmutable::parse($date))
                : collect());
        }

        return request()->attributes->get($memo);
    }

    /** El siguiente día con un horario que le sirva con el doctor elegido, dentro de su plazo. Null si no hay. */
    private static function nextDayWithSlots(WaitlistEntry $entry, Get $get): ?CarbonImmutable
    {
        if (! self::canSearch($entry, $get)) {
            return null;
        }

        $from = CarbonImmutable::parse($get('date'))->startOfDay();
        $last = $from->addDays(WaitlistService::SEARCH_DAYS);
        /** @var CarbonInterface|null $until */
        $until = $entry->latest_date;

        if ($until !== null && $until->lt($last)) {
            $last = CarbonImmutable::instance($until)->startOfDay();
        }

        for ($day = $from->addDay(); $day->lte($last); $day = $day->addDay()) {
            if (self::slotsOn($entry, $get, $day->toDateString())->isNotEmpty()) {
                return $day;
            }
        }

        return null;
    }

    /** ¿Ya hay tratamiento, doctor y día? (entonces, si no hay horarios, es que ese día no tiene lugar para él). */
    private static function canSearch(WaitlistEntry $entry, Get $get): bool
    {
        return filled($get('doctor_id')) && filled($get('treatment_id') ?? $entry->treatment_id) && self::isDate($get('date'));
    }

    /** @phpstan-assert-if-true string $value */
    private static function isDate(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    /** Bajo los horarios: qué se muestra y, si no hay ninguno, por qué y qué hacer. */
    private static function slotsHint(WaitlistEntry $entry, Get $get): string
    {
        if (! self::canSearch($entry, $get)) {
            return 'Elige el tratamiento y el doctor para ver los horarios libres.';
        }

        if (self::offerSlots($entry, $get)->isNotEmpty()) {
            /** @var DayPeriod $period */
            $period = $entry->preferred_period;

            return collect([
                $period !== DayPeriod::Any ? 'Solo los de la parte del día que pidió ('.mb_strtolower($period->getLabel()).').' : null,
                self::recommendedSlots($entry, $get) !== [] ? 'Las recomendadas van pegadas a otra cita: así no quedan huecos en la agenda.' : null,
            ])->filter()->implode(' ');
        }

        $next = self::nextDayWithSlots($entry, $get);

        return match (true) {
            $next !== null => 'Ese día no hay un horario que le sirva. El siguiente con lugar es el '.$next->translatedFormat('l j \d\e F').'.',
            count(self::doctorOptions($entry, $get)) > 1 => 'Con este doctor no hay un horario que le sirva en su plazo. Prueba con otro doctor.',
            default => 'No hay un horario que le sirva en su plazo. Sigue en la lista: cuando se libere uno, llegará la alerta.',
        };
    }

    /** @return array<int, string> Los doctores que pueden atenderlo en lo que espera. */
    private static function doctorOptions(WaitlistEntry $entry, Get $get): array
    {
        return app(WaitlistService::class)->doctorsFor($entry, SlotPicker::chosenTreatment($get('treatment_id') ?? $entry->treatment_id))->pluck('name', 'id')->all();
    }

    /** ¿Tiene celular para ofrecerle el horario por WhatsApp? */
    private static function hasWhatsApp(WaitlistEntry $entry): bool
    {
        /** @var Patient $patient */
        $patient = $entry->patient;

        return $patient->account !== null;
    }

    /** Bajo el mensaje: hasta cuándo queda apartado el horario elegido si se le envía ahora. */
    private static function holdText(WaitlistEntry $entry, Get $get): string
    {
        if (! self::isDate($get('date')) || blank($get('slot'))) {
            return 'Al abrir WhatsApp, el horario queda apartado mientras responde.';
        }

        $heldUntil = app(WaitlistService::class)->holdEndsAt(CarbonImmutable::parse($get('date'))->setTimeFromTimeString((string) $get('slot')));
        /** @var Patient $patient */
        $patient = $entry->patient;

        return 'Al abrir WhatsApp queda apartado para '.$patient->first_name.' hasta '
            .($heldUntil->isToday() ? 'las '.$heldUntil->format('H:i') : 'el '.Dates::dayAndTime($heldUntil))
            .' mientras responde: no se le ofrece a nadie más ni se puede reservar en la web.';
    }

    /**
     * El mensaje con el horario elegido, o vacío si falta elegirlo.
     *
     * @param  array{treatment_id: mixed, doctor_id: mixed, date: mixed, slot: mixed}  $state
     */
    private static function body(WaitlistEntry $entry, array $state): string
    {
        $doctor = Doctor::find($state['doctor_id']);
        $treatment = Treatment::find($state['treatment_id']);

        if (! $doctor || ! $treatment || blank($state['date']) || blank($state['slot'])) {
            return '';
        }

        $startsAt = CarbonImmutable::parse($state['date'])->setTimeFromTimeString($state['slot']);
        $message = app(WaitlistService::class)->offerDraft($entry, $doctor, $treatment, $startsAt);

        return $message ? app(WhatsAppOutbox::class)->render($message, auth()->user()) : '';
    }
}
