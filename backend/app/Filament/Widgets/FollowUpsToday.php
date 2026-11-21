<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\RescheduleRequestStatus;
use App\Filament\Actions\SendWhatsAppAction;
use App\Filament\Concerns\RefreshesWithToday;
use App\Filament\Pages\TomorrowTray;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Filament\Resources\Consultations\ConsultationResource;
use App\Filament\Resources\Installments\InstallmentResource;
use App\Filament\Resources\LabOrders\LabOrderResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\RescheduleRequests\RescheduleRequestResource;
use App\Filament\Resources\Supplies\SupplyResource;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\Appointment;
use App\Models\Consent;
use App\Models\ConsentTemplate;
use App\Models\Consultation;
use App\Models\DeskNote;
use App\Models\Doctor;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Supply;
use App\Models\SupplyBatch;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\Clinical\ConsentService;
use App\Services\Dashboard\FollowUps;
use App\Services\Inventory\SupplyForecast;
use App\Services\Inventory\Tray;
use App\Support\Dates;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * "Por hacer hoy" de recepción, al costado de la agenda, en dos bloques. "Resolver hoy": lo que espera a
 * alguien hoy (quien lleva mucho en sala, citas sin cerrar, consentimientos por firmar, avisos, solicitudes,
 * cuotas, laboratorio, inventario); arriba, cuántos de esos son urgentes. "Seguimiento": a quién escribirle o
 * llamar, con el WhatsApp ya redactado y "Nueva cita" a un toque; si es largo y hay algo que resolver, va
 * plegado. Cada grupo se abre y se cierra, con su número a la vista, y lleva a su lista completa; lo que la
 * persona abrió o cerró se queda así aunque la tarjeta se actualice. Quien ya recibió el aviso hace poco queda
 * marcado, para no escribirle dos veces. Arriba de todo van los pendientes que recepción anota a mano: salen el
 * día que tocan y se quedan hasta marcarlos como hechos, para que el turno que sigue sepa qué quedó.
 */
class FollowUpsToday extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use RefreshesWithToday;

    /** Lo que se resuelve hoy, en este orden; lo demás es seguimiento. */
    private const RESOLVE = ['waits', 'left-open', 'consents', 'earlier-open', 'messages', 'requests', 'installments', 'lab', 'supplies', 'supplies-soon', 'tray', 'batches'];

    /** De lo que se resuelve hoy, lo que no puede esperar: su número se marca y cuenta en "N urgentes". */
    private const URGENT = ['waits', 'left-open', 'consents', 'earlier-open', 'messages', 'requests', 'installments', 'lab', 'supplies', 'batches'];

    /** Desde cuántos grupos el seguimiento va plegado, si también hay algo que resolver. */
    private const FOLD_FOLLOW_UP_FROM = 3;

    /** Mientras carga, un recuadro del mismo alto: la página no salta. */
    protected ?string $placeholderHeight = '24rem';

    protected string $view = 'filament.widgets.follow-ups-today';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->managesAllAgendas();
    }

    /** Abre WhatsApp con el aviso del grupo para ese paciente, listo para retocar y enviar. */
    public function whatsappAction(): Action
    {
        return SendWhatsAppAction::compose('whatsapp', function (array $arguments) {
            $key = MessageTemplateKey::tryFrom((string) ($arguments['template'] ?? ''));
            $patient = Patient::query()->find($arguments['patient'] ?? null);

            return $key && $patient ? app(FollowUps::class)->draft($key, $patient) : null;
        })
            ->label('WhatsApp')
            ->link()
            ->size(Size::Small);
    }

    /** Anota un pendiente para hoy u otro día, con el paciente del que trata si hace falta. */
    public function addNoteAction(): Action
    {
        return Action::make('addNote')
            ->label('Anotar pendiente')
            ->icon(Heroicon::OutlinedPlus)
            ->link()
            ->size(Size::Small)
            ->authorize(fn () => auth()->user()->can('create', DeskNote::class))
            ->modalHeading('Anotar pendiente')
            ->modalDescription('Sale aquí el día que toca y se queda hasta que alguien lo marque como hecho.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Anotar')
            ->schema([
                Textarea::make('body')
                    ->label('¿Qué hay que hacer?')
                    ->placeholder('Por ejemplo: llamar al laboratorio por la corona de Rosa')
                    ->rows(2)
                    ->maxLength(300)
                    ->required()
                    ->autofocus(),
                DatePicker::make('due_on')
                    ->label('¿Para cuándo?')
                    ->native(false)
                    ->displayFormat('D d/m/Y')
                    ->firstDayOfWeek(1)
                    ->closeOnDateSelection()
                    ->default(fn () => today())
                    ->minDate(today())
                    ->required(),
                AppointmentForm::patientSelect()
                    ->label('¿De qué paciente? (opcional)')
                    ->default(null)
                    ->required(false),
            ])
            ->action(function (array $data) {
                DeskNote::create([...$data, 'created_by_user_id' => auth()->id()]);

                Notification::make()->success()->title('Pendiente anotado')->send();
            });
    }

    /** Marca un pendiente como hecho: queda tachado el resto del día, con quién lo hizo y a qué hora. */
    public function noteDoneAction(): Action
    {
        return Action::make('noteDone')
            ->label('Hecho')
            ->icon(Heroicon::Check)
            ->link()
            ->size(Size::Small)
            ->action(fn (array $arguments) => $this->settleNote($arguments, done: true));
    }

    /** Por si se marcó sin querer. */
    public function noteUndoAction(): Action
    {
        return Action::make('noteUndo')
            ->label('Deshacer')
            ->color('gray')
            ->link()
            ->size(Size::Small)
            ->action(fn (array $arguments) => $this->settleNote($arguments, done: false));
    }

    /** @param  array<string, mixed>  $arguments */
    private function settleNote(array $arguments, bool $done): void
    {
        $note = DeskNote::query()->find($arguments['note'] ?? null);

        if ($note && auth()->user()->can('update', $note)) {
            $note->update(['done_at' => $done ? now() : null, 'done_by_user_id' => $done ? auth()->id() : null]);
        }
    }

    /**
     * Los pendientes de hoy: primero lo que falta (lo más viejo arriba) y al final lo que ya se hizo hoy.
     *
     * @return list<array{id: int, body: string, status: string, done: bool, late: bool, patient: string|null, patient_url: string|null}>
     */
    private function notes(): array
    {
        return DeskNote::query()
            ->forToday()
            ->with(['patient', 'doneBy'])
            ->orderByRaw('done_at is not null')
            ->orderBy('due_on')
            ->orderBy('id')
            ->get()
            ->map(fn (DeskNote $note) => [
                'id' => $note->getKey(),
                'body' => $note->body,
                'status' => $note->status(),
                'done' => $note->isDone(),
                'late' => ! $note->isDone() && $note->due_on->lt(today()),
                'patient' => $note->patient?->full_name,
                'patient_url' => $note->patient ? PatientResource::getUrl('view', ['record' => $note->patient]) : null,
            ])
            ->all();
    }

    /**
     * @return array{notes: list<array{id: int, body: string, status: string, done: bool, late: bool, patient: string|null, patient_url: string|null}>, urgent: int, resolve: list<array<string, mixed>>, followUp: list<array<string, mixed>>, foldFollowUp: bool, openKey: string|null}
     */
    protected function getViewData(): array
    {
        $followUps = app(FollowUps::class);

        /** @var Collection<int, array<string, mixed>> $sections */
        $sections = collect([
            $this->longWaits(),
            $this->leftOpenAppointments(),
            $this->consentsToSign(),
            $this->earlierOpenConsultations(),
            $this->dueMessages(),
            $this->pendingRequests(),
            $this->awaitingAnswer($followUps),
            $this->unscheduled($followUps),
            $this->missedVisits($followUps),
            $this->installments($followUps),
            $this->labOrders(),
            $this->suppliesToReorder(),
            $this->suppliesRunningLow(),
            $this->tomorrowTray(),
            $this->batchesExpiring(),
            $this->checkups($followUps),
            $this->birthdays($followUps),
            $this->reviews($followUps),
        ])
            ->filter(fn (?array $section) => $section !== null && $section['count'] > 0)
            ->map(fn (array $section) => [...$section, 'urgent' => in_array($section['key'], self::URGENT, true)])
            ->values();
        [$resolve, $followUp] = $sections->partition(fn (array $section) => in_array($section['key'], self::RESOLVE, true));
        $resolve = $resolve->sortBy(fn (array $section) => array_search($section['key'], self::RESOLVE, true))->values();

        return [
            'notes' => $this->notes(),
            'urgent' => $resolve->where('urgent', true)->count(),
            'resolve' => $resolve->all(),
            'followUp' => $followUp->values()->all(),
            'foldFollowUp' => $resolve->isNotEmpty() && $followUp->count() >= self::FOLD_FOLLOW_UP_FROM,
            // El primer grupo con nombres va abierto: es lo primero que hay que hacer con personas.
            'openKey' => $resolve->concat($followUp)->first(fn (array $section) => $section['rows'] !== [])['key'] ?? null,
        ];
    }

    /**
     * Quien lleva 20 minutos o más de demora (desde la hora de su cita o, si llegó tarde, desde que llegó): alguien
     * tiene que ver qué pasa (avisar a su doctor o explicarle la demora). Quien llegó temprano no cuenta mientras no
     * sea su hora. Son los mismos que la sala de espera marca en rojo, y lo que se dice de cada uno es su demora.
     *
     * @return array<string, mixed>
     */
    private function longWaits(): array
    {
        $waiting = Appointment::query()
            ->waitingNow()
            ->with(['patient', 'doctor'])
            ->orderBy('checked_in_at')
            ->get()
            ->filter(fn (Appointment $appointment) => $appointment->waitsTooLong());

        return [
            'key' => 'waits',
            'title' => 'Con '.Appointment::LONG_WAIT_MINUTES.' min o más de demora',
            'hint' => 'Avisa a su doctor o explícales la demora',
            'count' => $waiting->count(),
            'url' => '#'.WaitingRoom::ANCHOR,
            'summary' => self::firstNames($waiting->map(function (Appointment $appointment): string {
                /** @var Patient $patient */
                $patient = $appointment->patient;
                /** @var Doctor|null $doctor */
                $doctor = $appointment->doctor;

                return $patient->full_name.' ('.collect([Dates::duration((int) $appointment->delayMinutes()), $doctor?->name])->filter()->implode(', ').')';
            })).'.',
            'template' => null,
            'rows' => [],
        ];
    }

    /**
     * Las citas de hoy cuyo tratamiento pide consentimiento y todavía no lo firmó: se firma desde la cita, antes de
     * empezar. Solo para quien puede tomarlo (quien atiende).
     *
     * @return array<string, mixed>|null
     */
    private function consentsToSign(): ?array
    {
        if (! auth()->user()->can('create', Consent::class)) {
            return null;
        }

        $consents = app(ConsentService::class);
        $visits = Appointment::query()
            ->whereDate('starts_at', today())
            ->whereIn('status', AppointmentStatus::open())
            ->whereIn('treatment_id', ConsentTemplate::query()->where('is_active', true)->whereNotNull('treatment_id')->select('treatment_id'))
            ->with(['patient', 'treatment'])
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Appointment $appointment) => $consents->signedForVisit($appointment) === false);

        return [
            'key' => 'consents',
            'title' => 'Consentimientos por firmar hoy',
            'hint' => 'Su tratamiento lo pide: se firma desde la cita, antes de empezar',
            'count' => $visits->count(),
            'url' => null,
            'summary' => null,
            'template' => null,
            'rows' => $visits->map(function (Appointment $appointment): array {
                /** @var Patient $patient */
                $patient = $appointment->patient;
                /** @var Treatment $treatment */
                $treatment = $appointment->treatment;

                return [
                    'patient_id' => $patient->getKey(),
                    'name' => $patient->full_name,
                    'detail' => $treatment->name,
                    'contacted' => null,
                    'can_message' => false,
                    'url' => AppointmentResource::getUrl('view', ['record' => $appointment]),
                    'new_appointment_url' => null,
                ];
            })->values()->all(),
        ];
    }

    /**
     * Atenciones de días anteriores que su doctor no terminó, de todos los doctores: mientras sigan abiertas no se
     * cobra lo atendido ni avanza el plan. Solo para el administrador, que ve las de todos. Si además atiende, las
     * suyas ya están en su tarjeta (OpenConsultations): aquí van las de los demás, para no contarlas dos veces.
     *
     * @return array<string, mixed>|null
     */
    private function earlierOpenConsultations(): ?array
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->isAdmin() || ! ConsultationResource::canViewAny()) {
            return null;
        }

        $own = $user->canSeeClinical() ? $user->doctorId() : null;
        $open = Consultation::query()
            ->whereNull('closed_at')
            ->when($own, fn ($query, int $doctorId) => $query->where('doctor_id', '!=', $doctorId))
            ->where(fn ($query) => $query
                ->whereDate('attended_at', '<', today())
                ->orWhere(fn ($query) => $query->whereNull('attended_at')->whereDate('created_at', '<', today())))
            ->with(['patient', 'doctor'])
            ->oldest('attended_at')
            ->get();

        return [
            'key' => 'earlier-open',
            'title' => $own === null ? 'Atenciones abiertas de días anteriores' : 'Atenciones abiertas de otros doctores',
            'hint' => 'Mientras sigan abiertas no se cobra ni avanza el plan',
            'count' => $open->count(),
            'url' => ConsultationResource::getUrl('index', ['tab' => 'open']),
            'summary' => self::firstNames($open->map(function (Consultation $consultation): string {
                /** @var Patient $patient */
                $patient = $consultation->patient;
                /** @var Doctor|null $doctor */
                $doctor = $consultation->doctor;

                return $patient->full_name.' ('.collect([$doctor?->name, ($consultation->attended_at ?? $consultation->created_at)->diffForHumans()])->filter()->implode(', ').')';
            })).'.',
            'template' => null,
            'rows' => [],
        ];
    }

    /**
     * Citas que quedaron sin cerrar (ya pasó su hora y nadie marcó nada). Sin cerrarlas, no se cobra lo atendido,
     * no se descuentan los materiales y las inasistencias del reporte salen mal.
     *
     * @return array<string, mixed>
     */
    private function leftOpenAppointments(): array
    {
        return [
            'key' => 'left-open',
            'title' => 'Citas sin cerrar',
            'hint' => 'Ya pasó su hora: marca si vino o si no asistió',
            'count' => AppointmentResource::getEloquentQuery()->unclosed()->count(),
            'url' => AppointmentResource::getUrl('index', ['tab' => 'unclosed']),
            'summary' => null,
            'template' => null,
            'rows' => [],
        ];
    }

    /**
     * Los avisos que esperan su clic en "Avisos por enviar" (recordatorios, confirmaciones).
     *
     * @return array<string, mixed>
     */
    private function dueMessages(): array
    {
        return [
            'key' => 'messages',
            'title' => 'Avisos por enviar',
            'hint' => 'Recordatorios y confirmaciones listos para WhatsApp',
            'count' => WhatsappMessageResource::getEloquentQuery()->due()->count(),
            'url' => WhatsappMessageResource::getUrl('index'),
            'summary' => null,
            'template' => null,
            'rows' => [],
        ];
    }

    /**
     * Pacientes que pidieron otro horario desde su enlace y esperan respuesta.
     *
     * @return array<string, mixed>
     */
    private function pendingRequests(): array
    {
        return [
            'key' => 'requests',
            'title' => 'Cambios pedidos',
            'hint' => 'Elige uno de los horarios que propuso el paciente',
            'count' => RescheduleRequestResource::getEloquentQuery()->where('status', RescheduleRequestStatus::Pending)->count(),
            'url' => RescheduleRequestResource::getUrl('index'),
            'summary' => null,
            'template' => null,
            'rows' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function awaitingAnswer(FollowUps $followUps): array
    {
        ['count' => $count, 'total' => $total, 'plans' => $plans] = $followUps->plansAwaitingAnswer();
        $key = MessageTemplateKey::PlanFollowUp;
        $contacted = $followUps->recentlyContacted($plans->pluck('patient_id'), $key);
        $since = 'presentados hace más de '.TreatmentPlan::ANSWER_WAIT_DAYS.' días';

        return [
            'key' => 'proposed',
            'title' => 'Presupuestos sin respuesta',
            // Lo que suman va primero y sin cortarse ("S/ 9,500.00"): es lo que dice cuánto hay en juego.
            'hint' => $count > 0 ? 'Suman '.str_replace(' ', "\u{00A0}", Money::format($total)).' · '.$since : ucfirst($since),
            'count' => $count,
            'url' => TreatmentPlanResource::getUrl('index', ['tab' => 'awaiting']),
            'summary' => null,
            'template' => $key->value,
            'rows' => $plans->map(fn (TreatmentPlan $plan) => $this->row(
                $plan->patient,
                collect([Money::format($plan->total()), $plan->doctor?->name, 'presentado '.$plan->proposed_at->diffForHumans()])->filter()->implode(' · '),
                $contacted,
            ))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function unscheduled(FollowUps $followUps): array
    {
        ['count' => $count, 'patients' => $patients] = $followUps->unscheduledPlans();
        $key = MessageTemplateKey::PlanNextSession;
        $contacted = $followUps->recentlyContacted($patients->modelKeys(), $key);

        return [
            'key' => 'unscheduled',
            'title' => 'Planes sin próxima cita',
            'hint' => 'Aceptaron el plan y les falta terminarlo',
            'count' => $count,
            'url' => TreatmentPlanResource::getUrl('index', ['tab' => 'unscheduled']),
            'summary' => null,
            'template' => $key->value,
            'rows' => $patients->map(function (Patient $patient) use ($contacted) {
                /** @var Collection<int, TreatmentPlanItem> $items */
                $items = $patient->treatmentPlans->flatMap->items;
                // Recepción ve el procedimiento, no la pieza (Ley 29733).
                $labels = $items->map(fn (TreatmentPlanItem $item) => $item->label(withTooth: false))->unique()->values();

                return $this->row(
                    $patient,
                    'Falta: '.$labels->take(2)->implode(', ').($labels->count() > 2 ? ' y '.($labels->count() - 2).' más' : ''),
                    $contacted,
                    AppointmentResource::newUrl(array_filter([
                        'patient' => $patient->getKey(),
                        'doctor' => $patient->treatmentPlans->first()?->doctor_id,
                        'treatment' => $items->first()?->treatment_id,
                        'plan_item' => $items->first()?->getKey(),
                    ])),
                );
            })->all(),
        ];
    }

    /**
     * Faltaron o cancelaron y nadie les dio otro horario: se les escribe o se les agenda de nuevo lo mismo.
     *
     * @return array<string, mixed>
     */
    private function missedVisits(FollowUps $followUps): array
    {
        ['count' => $count, 'patients' => $patients] = $followUps->missedVisits();
        $key = MessageTemplateKey::MissedVisit;
        $contacted = $followUps->recentlyContacted($patients->modelKeys(), $key);

        return [
            'key' => 'missed',
            'title' => 'Faltaron o cancelaron',
            'hint' => 'En los últimos '.intdiv(Patient::MISSED_VISIT_DAYS, 30).' meses, sin otra cita ni lista de espera',
            'count' => $count,
            'url' => PatientResource::getUrl('index', ['tab' => 'missed']),
            'summary' => null,
            'template' => $key->value,
            'rows' => $patients->map(function (Patient $patient) use ($contacted) {
                /** @var Appointment $missed */
                $missed = $patient->appointments->first();

                return $this->row(
                    $patient,
                    ($missed->status === AppointmentStatus::NoShow ? 'No asistió el ' : 'Se canceló su cita del ').$missed->starts_at->translatedFormat('j \d\e F').' · '.$missed->treatment->name,
                    $contacted,
                    AppointmentResource::newUrl(['patient' => $patient->getKey(), 'treatment' => $missed->treatment_id, 'doctor' => $missed->doctor_id]),
                );
            })->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function installments(FollowUps $followUps): array
    {
        ['count' => $count, 'amount' => $amount] = $followUps->lateInstallments();

        return [
            'key' => 'installments',
            'title' => 'Cuotas atrasadas',
            'hint' => 'Vencieron y no se pagaron',
            'count' => $count,
            'url' => InstallmentResource::getUrl('index', ['tab' => 'late']),
            'summary' => Money::format($amount).' por cobrar. En la lista, "Escribir" prepara el aviso de cada una.',
            'template' => null,
            'rows' => [],
        ];
    }

    /**
     * Trabajos de laboratorio que piden algo hoy: se atrasaron, llegan hoy, el paciente tiene cita antes de la
     * entrega, o ya llegaron y nadie lo citó para colocarlos.
     *
     * @return array<string, mixed>
     */
    private function labOrders(): array
    {
        $orders = LabOrderResource::getEloquentQuery()->needingAttention()->orderBy('due_on')->get();

        return [
            'key' => 'lab',
            'title' => 'Trabajos de laboratorio',
            'hint' => 'Atrasados, llegan hoy, con la cita antes de la entrega o falta citar',
            'count' => $orders->count(),
            'url' => LabOrderResource::getUrl('index', ['tab' => 'attention']),
            'summary' => self::firstNames($orders->map(fn (LabOrder $order) => $order->description.' de '.$order->patient->full_name.' ('.mb_strtolower(mb_substr((string) $order->alert(), 0, 1)).mb_substr((string) $order->alert(), 1).')')).'.',
            'template' => null,
            'rows' => [],
        ];
    }

    /**
     * Materiales que bajaron del mínimo y nadie pidió todavía: el pedido sale por WhatsApp desde Inventario.
     *
     * @return array<string, mixed>|null
     */
    private function suppliesToReorder(): ?array
    {
        if (! auth()->user()->can('viewAny', Supply::class)) {
            return null;
        }

        // Lo que ya se pidió espera en Compras: aquí queda solo lo que falta pedir.
        $names = Supply::query()->toReorder()->orderBy('name')->pluck('name');

        return [
            'key' => 'supplies',
            'title' => 'Materiales por pedir',
            'hint' => 'Bajaron del mínimo y no se pidieron',
            'count' => $names->count(),
            'url' => SupplyResource::getUrl('index', ['tab' => 'low']),
            'summary' => self::firstNames($names).'. Pídelos por WhatsApp desde Inventario.',
            'template' => null,
            'rows' => [],
        ];
    }

    /**
     * Materiales que todavía alcanzan, pero que con las citas de las próximas dos semanas se acaban o bajan del
     * mínimo: se piden antes de que falten.
     *
     * @return array<string, mixed>|null
     */
    private function suppliesRunningLow(): ?array
    {
        if (! auth()->user()->can('viewAny', Supply::class)) {
            return null;
        }

        $lines = app(SupplyForecast::class)->runningLow();

        return [
            'key' => 'supplies-soon',
            'title' => 'Materiales que se acaban pronto',
            'hint' => 'Con las citas de las próximas '.intdiv(SupplyForecast::HORIZON_DAYS, 7).' semanas',
            'count' => $lines->count(),
            'url' => SupplyResource::getUrl('index', ['tab' => 'soon']),
            'summary' => self::firstNames($lines->map(fn (array $line) => $line['supply']->name.' ('.($line['runs_out_on'] ? 'se acaba ~'.$line['runs_out_on']->format('d/m') : 'baja del mínimo el '.$line['below_min_on']->format('d/m')).')')).'. Pídelos antes de que falten.',
            'template' => null,
            'rows' => [],
        ];
    }

    /**
     * La bandeja de mañana: cuántas citas llevan materiales y si a alguna no le alcanza. Se prepara la víspera.
     *
     * @return array<string, mixed>|null
     */
    private function tomorrowTray(): ?array
    {
        if (! TomorrowTray::canAccess()) {
            return null;
        }

        $visits = collect(app(Tray::class)->for(today()->addDay()))->filter(fn (array $visit) => $visit['items'] !== []);
        $short = $visits->flatMap(fn (array $visit) => array_filter($visit['items'], fn (array $item) => $item['short'] !== null))->pluck('name')->unique();

        return [
            'key' => 'tray',
            'title' => 'Bandeja de mañana',
            'hint' => 'Lo que se usa en cada cita, del lote que vence primero',
            'count' => $visits->count(),
            'url' => TomorrowTray::getUrl(),
            'summary' => $short->isNotEmpty() ? 'No alcanza: '.self::firstNames($short).'.' : null,
            'template' => null,
            'rows' => [],
        ];
    }

    /**
     * Lotes que vencen en 30 días o ya vencieron: se usan primero o se dan de baja.
     *
     * @return array<string, mixed>|null
     */
    private function batchesExpiring(): ?array
    {
        if (! auth()->user()->can('viewAny', Supply::class)) {
            return null;
        }

        $batches = SupplyBatch::query()
            ->available()
            ->expiringSoon()
            ->whereHas('supply', fn ($supply) => $supply->active())
            ->with('supply')
            ->orderBy('expires_on')
            ->get();
        $expired = $batches->filter->isExpired()->count();

        return [
            'key' => 'batches',
            'title' => 'Lotes por vencer',
            'hint' => 'Vencen en '.Supply::EXPIRY_WARNING_DAYS.' días o menos',
            'count' => $batches->count(),
            'url' => SupplyResource::getUrl('index', ['tab' => 'expiring']),
            'summary' => self::firstNames($batches->map(fn (SupplyBatch $batch) => $batch->supply->name.' ('.($batch->isExpired() ? 'venció' : 'vence').' el '.$batch->expires_on->format('d/m').')')->unique())
                .($expired > 0 ? '. Lo vencido se da de baja.' : '. Úsalos primero.'),
            'template' => null,
            'rows' => [],
        ];
    }

    /** @param  Collection<int, covariant string>  $names  "Guantes, Lidocaína y 3 más". */
    private static function firstNames(Collection $names): string
    {
        $names = $names->values();

        return $names->take(2)->implode(', ').($names->count() > 2 ? ' y '.($names->count() - 2).' más' : '');
    }

    /** @return array<string, mixed> */
    private function checkups(FollowUps $followUps): array
    {
        ['count' => $count, 'patients' => $patients] = $followUps->checkups();
        $key = MessageTemplateKey::CheckupRecall;
        $contacted = $followUps->recentlyContacted($patients->modelKeys(), $key);

        return [
            'key' => 'checkup',
            'title' => 'Para su control',
            'hint' => 'Sin cita y ya les toca: ortodoncia cada mes, implante al año, lo demás a los 6 meses',
            'count' => $count,
            'url' => PatientResource::getUrl('index', ['tab' => 'checkup']),
            'summary' => null,
            'template' => $key->value,
            'rows' => $patients->map(fn (Patient $patient) => $this->row(
                $patient,
                'Última visita: '.$patient->last_visit_at->translatedFormat('j M Y'),
                $contacted,
                AppointmentResource::newUrl(['patient' => $patient->getKey()]),
            ))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function birthdays(FollowUps $followUps): array
    {
        $patients = $followUps->birthdays();
        $key = MessageTemplateKey::Birthday;
        $contacted = $followUps->recentlyContacted($patients->modelKeys(), $key);

        return [
            'key' => 'birthdays',
            'title' => 'Cumpleaños de hoy',
            'hint' => 'Un saludo cuesta poco y se recuerda',
            'count' => $patients->count(),
            'url' => null,
            'summary' => null,
            'template' => $key->value,
            'rows' => $patients->map(fn (Patient $patient) => $this->row(
                $patient,
                'Cumple '.$patient->age.($patient->age === 1 ? ' año' : ' años'),
                $contacted,
            ))->all(),
        ];
    }

    /**
     * Al terminar la atención, a quien vino contento: viene seguido, está al día y no faltó. Es el mejor día para
     * pedirle su opinión en Google (y a nadie más de una vez cada seis meses).
     *
     * @return array<string, mixed>
     */
    private function reviews(FollowUps $followUps): array
    {
        $patients = $followUps->reviewCandidates();
        $key = MessageTemplateKey::ReviewRequest;
        $contacted = $followUps->recentlyContacted($patients->modelKeys(), $key);

        return [
            'key' => 'reviews',
            'title' => 'Pedir su opinión en Google',
            'hint' => 'Atendidos hoy que vienen seguido, están al día y no faltaron',
            'count' => $patients->count(),
            'url' => null,
            'summary' => null,
            'template' => $key->value,
            'rows' => $patients->map(fn (Patient $patient) => $this->row(
                $patient,
                collect(['Hoy: '.$patient->appointments->first()?->treatment->name, $patient->visits_count.' atenciones'])->implode(' · '),
                $contacted,
            ))->all(),
        ];
    }

    /**
     * @param  array<int, CarbonImmutable>  $contacted
     * @return array{patient_id: int, name: string, detail: string, contacted: string|null, can_message: bool, url: string, new_appointment_url: string|null}
     */
    private function row(Patient $patient, string $detail, array $contacted, ?string $newAppointmentUrl = null): array
    {
        $sentAt = $contacted[$patient->getKey()] ?? null;

        return [
            'patient_id' => $patient->getKey(),
            'name' => $patient->full_name,
            'detail' => $detail,
            'contacted' => $sentAt ? 'Le escribiste '.($sentAt->isToday() ? 'hoy' : $sentAt->diffForHumans()) : null,
            'can_message' => $patient->account !== null,
            'url' => PatientResource::getUrl('view', ['record' => $patient]),
            'new_appointment_url' => $newAppointmentUrl,
        ];
    }
}
