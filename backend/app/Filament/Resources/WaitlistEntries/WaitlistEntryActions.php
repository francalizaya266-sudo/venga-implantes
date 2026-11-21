<?php

namespace App\Filament\Resources\WaitlistEntries;

use App\Enums\DayPeriod;
use App\Enums\PatientRelationship;
use App\Enums\WaitlistStatus;
use App\Filament\Actions\SendWhatsAppAction;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/** Anotarlo en la lista, ofrecerle un horario por WhatsApp (queda apartado mientras responde), agendarlo cuando dice que sí, liberarlo o retirarlo. */
final class WaitlistEntryActions
{
    /** Segundos que se ve el aviso de un horario que quedó libre: no se queda tapando los botones de arriba. */
    public const FREED_NOTICE_SECONDS = 20;

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
     * Llega con el primer horario libre que le sirve (o con el que se tocó en la agenda). Al cambiar el doctor, el día o la hora, el
     * mensaje se vuelve a escribir; al abrir WhatsApp queda como avisado con ese horario.
     */
    public static function offer(): Action
    {
        return Action::make('offer')
            ->label('Ofrecer horario')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('success')
            ->visible(fn (WaitlistEntry $record) => $record->isActive() && $record->patient->account !== null)
            ->authorize('update')
            ->modalHeading(fn (WaitlistEntry $record) => 'Ofrecer un horario a '.$record->patient->full_name)
            ->modalDescription('Elige el horario. Se abrirá WhatsApp con el mensaje listo: solo presiona Enviar. El horario queda apartado mientras responde: no se le ofrece a nadie más ni se puede reservar en la web.')
            ->modalSubmitActionLabel('Abrir WhatsApp')
            // Como "Enviar por WhatsApp": la pestaña se abre en el mismo toque, para que el navegador no la bloquee.
            ->modalSubmitAction(fn (Action $action) => $action->extraAttributes(SendWhatsAppAction::OPENS_TAB_ON_CLICK))
            ->modalWidth(Width::TwoExtraLarge)
            ->fillForm(function (WaitlistEntry $record, array $arguments) {
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
            })
            ->schema(fn (WaitlistEntry $record) => [
                Select::make('treatment_id')
                    ->label('Tratamiento')
                    ->options(fn () => Treatment::query()->active()->whereHas('doctors')->orderBy('sort_order')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->hidden($record->treatment_id !== null)
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::refresh($record, $get, $set, keepSlot: false)),
                Select::make('doctor_id')
                    ->label('Doctor')
                    ->options(fn (Get $get) => app(WaitlistService::class)->doctorsFor($record, Treatment::find($get('treatment_id')))->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::refresh($record, $get, $set, keepSlot: false)),
                DatePicker::make('date')
                    ->label('Día')
                    ->native(false)
                    ->displayFormat('D d/m/Y')
                    ->firstDayOfWeek(1)
                    ->closeOnDateSelection()
                    ->minDate($record->firstDate())
                    ->maxDate($record->latest_date)
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::refresh($record, $get, $set, keepSlot: false)),
                Select::make('slot')
                    ->label('Horario libre')
                    ->options(fn (Get $get) => self::slotOptions($record, $get))
                    ->placeholder(fn (Get $get) => self::slotOptions($record, $get) === [] ? 'No hay horarios libres que le sirvan ese día' : 'Elige un horario')
                    ->helperText('Solo los que caen en la parte del día que pidió. ★ = pegado a otra cita.')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::refresh($record, $get, $set, keepSlot: true)),
                Textarea::make('body')
                    ->label('Mensaje')
                    ->rows(6)
                    ->required(),
            ])
            ->action(function (array $data, WaitlistEntry $record, Action $action, Component $livewire) {
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

    /** Al cambiar algo, la hora pasa a la primera libre (si la elegida ya no sirve) y el mensaje se reescribe. */
    private static function refresh(WaitlistEntry $entry, Get $get, Set $set, bool $keepSlot): void
    {
        $options = self::slotOptions($entry, $get);

        if (! $keepSlot || ! array_key_exists((string) $get('slot'), $options)) {
            $set('slot', array_key_first($options));
        }

        $set('body', self::body($entry, [
            'treatment_id' => $get('treatment_id') ?? $entry->treatment_id,
            'doctor_id' => $get('doctor_id'),
            'date' => $get('date'),
            'slot' => $get('slot'),
        ]));
    }

    /** @return array<string, string> */
    private static function slotOptions(WaitlistEntry $entry, Get $get): array
    {
        $doctor = Doctor::find($get('doctor_id'));
        $treatment = Treatment::find($get('treatment_id') ?? $entry->treatment_id);
        $date = $get('date');

        if (! $doctor || ! $treatment || blank($date)) {
            return [];
        }

        return app(WaitlistService::class)
            ->slotsFor($entry, $doctor, $treatment, CarbonImmutable::parse($date))
            ->mapWithKeys(fn (Slot $slot) => [$slot->label() => $slot->label().($slot->recommended ? ' ★' : '')])
            ->all();
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
