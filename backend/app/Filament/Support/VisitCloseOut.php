<?php

namespace App\Filament\Support;

use App\Enums\TreatmentKind;
use App\Enums\TreatmentPlanStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Consultations\ConsultationResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\Patients\RelationManagers\TreatmentPlansRelationManager;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\Clinical\TreatmentPlanService;
use App\Services\Patients\PatientInsights;
use App\Services\StaffAlerts;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Lo que sigue al terminar la atención, en una tarjeta arriba de la atención terminada: el presupuesto que
 * se armó (para que lo acepte y firme, o se lo lleve impreso o por WhatsApp), cobrar (o pasárselo a recepción)
 * y la próxima sesión con su fecha sugerida.
 *
 * El doctor no maneja la caja: al terminar, si el paciente debe algo, recepción se entera sola. La administradora
 * que también atiende elige: cobra ella misma o se lo pasa a recepción.
 */
final class VisitCloseOut
{
    /** Los plazos de siempre para la próxima cita, en días. */
    private const WITHIN_DAYS = [7 => 'En 1 semana', 15 => 'En 15 días', 30 => 'En 1 mes'];

    /**
     * @return array{plan: array{label: string, detail: string, button: string|null, action: string|null, done: bool, links: list<array{label: string, url?: string, action?: string}>, options: list<string>}|null, payment: array{label: string, detail: string, button: string|null, action: string|null, done: bool, secondary: array{label: string, action: string}|null}, next: array{label: string, detail: string, url: string|null, done: bool, within: list<array{label: string, url: string}>}}|null
     */
    public static function viewData(Consultation $consultation, User $user): ?array
    {
        if ($consultation->closed_at === null || ! $consultation->closed_at->isToday()) {
            return null;
        }

        return [
            'plan' => self::plan($consultation, $user),
            'payment' => self::payment($consultation, $user),
            'next' => self::next($consultation, $user),
        ];
    }

    /**
     * El presupuesto que el paciente tiene por aceptar: el que se armó en esta atención o, si no, el último con
     * algo sin aceptar (también lo que se agregó a un plan que ya aceptó). Null si no tiene nada por aceptar.
     */
    public static function planToAccept(Consultation $consultation): ?TreatmentPlan
    {
        return TreatmentPlan::query()
            ->open()
            ->where('patient_id', $consultation->patient_id)
            ->whereNull('included_in_plan_id')
            ->with(['items', 'patient.account'])
            ->get()
            ->filter(fn (TreatmentPlan $plan) => $plan->itemsToAccept()->isNotEmpty())
            ->sortByDesc(fn (TreatmentPlan $plan) => [(int) $plan->consultation_id === (int) $consultation->getKey(), $plan->getKey()])
            ->first();
    }

    /** Recepción ya sabe que tiene que cobrarle (se avisa una vez por atención). */
    public static function receptionNotified(Consultation $consultation): bool
    {
        return Cache::has(self::notifiedKey($consultation));
    }

    public static function markReceptionNotified(Consultation $consultation): void
    {
        Cache::put(self::notifiedKey($consultation), true, now()->endOfDay());
    }

    /**
     * Le pasa el cobro a recepción: le llega el aviso (en pantalla y en la campana) con el botón para cobrarle, y
     * la tarjeta queda en "Recepción ya sabe que tiene que cobrarle".
     */
    public static function handToReception(Consultation $consultation, User $by): void
    {
        $patient = $consultation->patient;

        app(StaffAlerts::class)->toReception(
            $patient->full_name.' terminó su atención: cobrarle',
            Money::balance($patient->balance()).' · atendió '.$consultation->doctor->name,
            $by,
            ['label' => 'Cobrar', 'url' => $consultation->appointment
                ? AppointmentResource::getUrl('view', ['record' => $consultation->appointment])
                : PatientResource::getUrl('view', ['record' => $patient])],
            // Al pagar, el aviso se da por leído.
            ['checkout' => $patient->getKey()],
        );

        self::markReceptionNotified($consultation);
    }

    /**
     * Al terminar la atención: si quien atendió no maneja la caja y el paciente debe algo, recepción se entera sin
     * que el doctor tenga que tocar nada.
     *
     * @return bool Si se le pasó el cobro a recepción.
     */
    public static function handToReceptionAfterClosing(Consultation $consultation, User $by): bool
    {
        if ($by->can('create', Payment::class) || $consultation->patient->balance() <= 0) {
            return false;
        }

        self::handToReception($consultation, $by);

        return true;
    }

    /** @return array{label: string, detail: string, button: string|null, action: string|null, done: bool, links: list<array{label: string, url?: string, action?: string}>, options: list<string>}|null */
    private static function plan(Consultation $consultation, User $user): ?array
    {
        $plan = self::planToAccept($consultation);

        if (! $plan) {
            $signed = self::signedToday($consultation);

            return $signed ? [
                'label' => 'Aceptó su plan: '.Money::format($signed->total()),
                'detail' => app(TreatmentPlanService::class)->paymentText($signed),
                'button' => null,
                'action' => null,
                'done' => true,
                'links' => [],
                'options' => [],
            ] : null;
        }

        $first = $plan->accepted_at === null;
        $items = $plan->itemsToAccept();
        $canSign = $user->can('update', $plan);
        $lines = TreatmentPlan::lines($items);
        $names = $lines->take(3)->pluck('label')->implode(' · ')
            .($lines->count() > 3 ? ' y '.($lines->count() - 3).' más' : '');
        $until = $plan->validUntil();

        return [
            'label' => match (true) {
                // Se empezó el paquete sin firmar: lo hecho se paga con el paquete, así que tiene que firmar.
                $first && $plan->status === TreatmentPlanStatus::InProgress && TreatmentPlanService::awaitsPackageSignature($plan) => 'Ya empezó su paquete, falta que firme: ',
                $first => 'Presupuesto por aceptar: ',
                default => 'Lo agregado a su plan, por aceptar: ',
            }.Money::format($items->sum(fn (TreatmentPlanItem $item) => $item->subtotal())),
            'detail' => $names.($until ? ' · vale hasta el '.$until->format('d/m/Y') : ''),
            'options' => $first ? app(TreatmentPlanService::class)->paymentOptions($plan) : [],
            'button' => $canSign ? ($first ? 'Acepta y firma' : 'Firma lo agregado') : null,
            'action' => $canSign ? 'acceptPlan' : null,
            'done' => false,
            'links' => array_values(array_filter([
                // En la pantalla, para girarla hacia el paciente y explicarle el plan con su odontograma y sus fotos.
                $user->can('viewClinical', $consultation->patient) ? ['label' => 'Mostrar al paciente', 'url' => ConsultationResource::presentUrl($consultation)] : null,
                ['label' => 'Imprimir', 'url' => TreatmentPlansRelationManager::printUrl($plan)],
                $canSign && $plan->patient->account !== null ? ['label' => 'WhatsApp', 'action' => 'sendPlan'] : null,
            ])),
        ];
    }

    /** El plan que aceptó o firmó hoy, para dejarlo a la vista ya resuelto. */
    private static function signedToday(Consultation $consultation): ?TreatmentPlan
    {
        return TreatmentPlan::query()
            ->open()
            ->where('patient_id', $consultation->patient_id)
            ->whereNull('included_in_plan_id')
            ->where(fn ($query) => $query
                ->where('accepted_at', '>=', today())
                ->orWhereHas('signatures', fn ($signatures) => $signatures->where('signed_at', '>=', today())))
            ->with(['items', 'installments'])
            ->latest('id')
            ->first();
    }

    /** @return array{label: string, detail: string, button: string|null, action: string|null, done: bool, secondary: array{label: string, action: string}|null} */
    private static function payment(Consultation $consultation, User $user): array
    {
        $charged = Charge::query()->valid()->where('consultation_id', $consultation->getKey())->get();
        $balance = $consultation->patient->balance();
        $detail = $charged->isEmpty()
            ? 'Hoy no se cargó nada'
            : 'Se cargó '.Money::format($charged->sum(fn (Charge $charge) => $charge->net())).': '.$charged->pluck('description')->implode(' · ');

        if ($balance <= 0) {
            return ['label' => 'No tiene nada por pagar', 'detail' => $detail, 'button' => null, 'action' => null, 'done' => true, 'secondary' => null];
        }

        $label = 'Debe '.Money::format($balance);
        $notified = self::receptionNotified($consultation);

        // Quien maneja la caja cobra ahí mismo. La administradora, además, puede pasárselo a recepción.
        if ($user->can('create', Payment::class)) {
            return [
                'label' => $label,
                'detail' => $notified ? 'Recepción ya sabe que tiene que cobrarle.' : $detail,
                'button' => 'Cobrar ahora',
                'action' => 'collect',
                'done' => $notified,
                'secondary' => ! $notified && $user->isAdmin() && self::hasReception($user) ? ['label' => 'Pasar a recepción', 'action' => 'notifyReception'] : null,
            ];
        }

        // El doctor no cobra. Recepción ya se enteró al terminar; el botón queda por si no fue así (por ejemplo,
        // si el cargo se agregó después).
        return $notified
            ? ['label' => $label, 'detail' => 'Recepción ya sabe que tiene que cobrarle.', 'button' => null, 'action' => null, 'done' => true, 'secondary' => null]
            : ['label' => $label, 'detail' => $detail, 'button' => 'Avisar a recepción', 'action' => 'notifyReception', 'done' => false, 'secondary' => null];
    }

    /** Alguien de recepción (activo, y que no sea quien mira) para pasarle el cobro. */
    private static function hasReception(User $except): bool
    {
        return User::query()
            ->where('is_active', true)
            ->where('role', UserRole::Reception)
            ->whereKeyNot($except->getKey())
            ->exists();
    }

    /**
     * La próxima sesión de su plan. Si todavía no acepta el presupuesto, se ofrece su primera sesión: al
     * agendarla se pregunta si ya lo aceptó. Con los plazos de siempre a un toque (en 1 semana, en 15 días),
     * para agendarla en otra fecha sin buscarla en el calendario.
     *
     * @return array{label: string, detail: string, url: string|null, done: bool, within: list<array{label: string, url: string}>}
     */
    private static function next(Consultation $consultation, User $user): array
    {
        $insights = app(PatientInsights::class);
        $patient = $consultation->patient;

        if ($booked = $insights->upcomingVisit($patient)) {
            return [
                'label' => 'Ya tiene su próxima cita',
                'detail' => ucfirst($booked->starts_at->translatedFormat('l j \d\e F, g:i a')).' · '.$booked->treatment->name,
                'url' => null,
                'done' => true,
                'within' => [],
            ];
        }

        $canBook = $user->can('create', Appointment::class);
        $suggested = $insights->suggestedNextVisit($patient, evenIfNotAccepted: true);

        if (! $suggested) {
            return [
                'label' => 'No tiene otra sesión pendiente',
                'detail' => 'Si necesita volver, prográmale una cita.',
                'url' => $canBook ? AppointmentResource::nextVisitUrl($patient, $consultation->doctor_id) : null,
                'done' => false,
                'within' => $canBook ? self::within($consultation, checkupMonths: $consultation->mainTreatment()?->checkupMonths() ?? TreatmentKind::Other->checkupMonths()) : [],
            ];
        }

        $waitsForAcceptance = $suggested['item']->plan->accepted_at === null;

        return [
            'label' => ($waitsForAcceptance ? 'Primera sesión del presupuesto: ' : 'Próxima: ').$suggested['label'],
            'detail' => 'Sugerida el '.$suggested['on']->translatedFormat('l j \d\e F').' · '.$suggested['why'],
            'url' => $canBook ? AppointmentResource::nextVisitUrl($patient, $consultation->doctor_id, $suggested['on'], $suggested['item']) : null,
            'done' => false,
            'within' => $canBook ? self::within($consultation, $suggested['item']) : [],
        ];
    }

    /**
     * "En 1 semana", "En 15 días", "En 1 mes": la cita nueva se abre con ese día ya elegido (y con la sesión del
     * plan, si la hay). Sin sesión pendiente, además el control: "Control en 6 meses", o al año si fue un implante.
     *
     * @return list<array{label: string, url: string}>
     */
    private static function within(Consultation $consultation, ?TreatmentPlanItem $item = null, ?int $checkupMonths = null): array
    {
        $patient = Patient::query()->findOrFail($consultation->patient_id);
        $today = CarbonImmutable::today();
        $links = collect(self::WITHIN_DAYS)->map(fn (string $label, int $days) => [
            'label' => $label,
            'url' => AppointmentResource::nextVisitUrl($patient, $consultation->doctor_id, $today->addDays($days), $item),
        ]);

        // La ortodoncia vuelve al mes: ese plazo ya está.
        if ($checkupMonths !== null && $checkupMonths > 1) {
            $links->push([
                'label' => $checkupMonths === 12 ? 'Control al año' : 'Control en '.$checkupMonths.' meses',
                'url' => AppointmentResource::nextVisitUrl($patient, $consultation->doctor_id, $today->addMonths($checkupMonths)),
            ]);
        }

        return $links->values()->all();
    }

    private static function notifiedKey(Consultation $consultation): string
    {
        return 'close-out:reception-notified:'.$consultation->getKey();
    }
}
