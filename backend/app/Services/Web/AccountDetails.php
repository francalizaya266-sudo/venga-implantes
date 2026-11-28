<?php

namespace App\Services\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PatientRelationship;
use App\Enums\PaymentMethod;
use App\Enums\PlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanInstallment;
use App\Models\TreatmentPlanItem;
use App\Services\Billing\BillingService;
use App\Services\Clinical\PrescriptionService;
use App\Services\Clinical\TreatmentPlanService;
use App\Support\Dates;
use App\Support\Initials;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Lo que Mi cuenta muestra a la familia después de confirmar que es ella (un enlace que llegó a su WhatsApp): sus
 * planes con el avance de cada tratamiento y de cada pago, lo que pagó, sus citas anteriores con lo que se llevó a
 * casa y los datos de cada persona. Todo con las palabras ya listas para la pantalla. La historia clínica (examen,
 * diagnóstico, evolución) no sale de aquí: solo lo que el doctor le entrega al paciente.
 */
final class AccountDetails
{
    /** Planes ya terminados y pagados que se siguen mostrando, plegados, como historial. */
    private const FINISHED_PLANS = 6;

    /** Citas anteriores y pagos que se muestran: lo más reciente primero. */
    private const PAST_APPOINTMENTS = 20;

    private const PAYMENTS = 30;

    public function __construct(
        private readonly BillingService $billing,
        private readonly TreatmentPlanService $plans,
        private readonly PrescriptionService $prescriptions,
    ) {}

    /**
     * Los planes que la familia aceptó: primero los que siguen (el más reciente arriba) y al final los últimos que
     * ya se terminaron y se pagaron.
     *
     * @param  EloquentCollection<int, Patient>  $patients
     * @param  array<int, string>  $names
     * @return Collection<int, array<string, mixed>>
     */
    public function plans(EloquentCollection $patients, array $names): Collection
    {
        $cards = TreatmentPlan::query()
            ->whereIn('patient_id', $patients->modelKeys())
            ->whereNotNull('accepted_at')
            ->whereNot('status', TreatmentPlanStatus::Cancelled)
            ->with(['patient', 'doctor', 'items', 'installments', 'includedIn.patient'])
            // Las sesiones del plan que ya tienen fecha: la más cercana es "tu próxima sesión".
            ->with(['items.appointments' => fn ($appointments) => $appointments
                ->where('starts_at', '>=', now())
                ->whereIn('status', AppointmentStatus::changeable())
                ->oldest('starts_at')])
            ->latest('accepted_at')
            ->latest('id')
            ->get()
            ->map(fn (TreatmentPlan $plan) => $this->planCard(
                $plan,
                // En un 2x1, la otra persona no paga: su plan va incluido en el de quien paga.
                $plan->included_in_plan_id ? null : $this->billing->planProgress($plan),
                $plan->included_in_plan_id ? null : $this->plans->paymentText($plan),
                $names,
            ));

        return $cards->where('finished', false)
            ->concat($cards->where('finished', true)->take(self::FINISHED_PLANS))
            ->values();
    }

    /**
     * El saldo de cada persona de la familia que debe o tiene algo a favor, y cuánto debe la familia entre todos.
     *
     * @param  EloquentCollection<int, Patient>  $patients
     * @return array{balances: Collection<int, array{patient_id: int, name: string, text: string, owed: bool}>, owed_total: string|null}
     */
    public function balances(EloquentCollection $patients): array
    {
        $rows = $patients
            ->map(fn (Patient $patient) => ['patient' => $patient, 'balance' => $patient->balance()])
            ->reject(fn (array $row) => $row['balance'] === 0.0)
            ->values();
        $owed = (float) $rows->sum(fn (array $row) => max(0, $row['balance']));

        return [
            'balances' => $rows->map(fn (array $row) => [
                'patient_id' => $row['patient']->id,
                'name' => $row['patient']->full_name,
                'text' => $row['balance'] > 0 ? Money::format($row['balance']).' por pagar' : Money::format(-$row['balance']).' a favor',
                'owed' => $row['balance'] > 0,
            ]),
            'owed_total' => $owed > 0 ? Money::format($owed) : null,
        ];
    }

    /**
     * Lo que la familia pagó, lo más reciente primero. Un pago anulado no cuenta.
     *
     * @param  EloquentCollection<int, Patient>  $patients
     * @param  array<int, string>  $names
     * @return Collection<int, array{id: int, patient_id: int, date: string, name: string, amount: string, method: string}>
     */
    public function payments(EloquentCollection $patients, array $names): Collection
    {
        return Payment::query()
            ->whereIn('patient_id', $patients->modelKeys())
            ->whereNull('voided_at')
            ->latest('paid_at')
            ->latest('id')
            ->limit(self::PAYMENTS)
            ->get()
            ->map(function (Payment $payment) use ($names): array {
                /** @var CarbonInterface $paidAt */
                $paidAt = $payment->paid_at;
                /** @var PaymentMethod $method */
                $method = $payment->method;

                return [
                    'id' => $payment->id,
                    'patient_id' => $payment->patient_id,
                    'date' => $paidAt->format('d/m/Y'),
                    'name' => $names[$payment->patient_id],
                    'amount' => Money::format($payment->amount),
                    'method' => $method->getLabel(),
                ];
            })
            ->values();
    }

    /**
     * Las citas que ya pasaron. La que se atendió trae lo que el paciente se llevó a casa: lo que se le hizo de su
     * plan, las indicaciones del doctor, la receta y los cuidados del tratamiento.
     *
     * @param  EloquentCollection<int, Patient>  $patients
     * @param  array<int, string>  $names
     * @return Collection<int, array<string, mixed>>
     */
    public function past(EloquentCollection $patients, array $names): Collection
    {
        return Appointment::query()
            ->whereIn('patient_id', $patients->modelKeys())
            ->where('starts_at', '<', now())
            // La de hoy que todavía se espera o se está atendiendo sigue entre las próximas: no sale dos veces.
            ->where(fn (Builder $query) => $query->where('starts_at', '<', today())->orWhereNotIn('status', AppointmentStatus::open()))
            ->with(['doctor', 'treatment', 'consultation.planItems', 'consultation.prescription.items'])
            ->latest('starts_at')
            ->limit(self::PAST_APPOINTMENTS)
            ->get()
            ->map(fn (Appointment $appointment) => $this->pastCard($appointment, $names))
            ->values();
    }

    /**
     * Cada persona de la familia con sus datos, y si todavía falta que nos cuente su salud.
     *
     * @param  EloquentCollection<int, Patient>  $family
     * @param  array<int, string>  $names
     * @return Collection<int, array<string, mixed>>
     */
    public function family(EloquentCollection $family, array $names): Collection
    {
        $family->loadMissing('medicalBackground');

        return $family->map(fn (Patient $patient) => self::member($patient, $names))->values();
    }

    /**
     * Una cita que ya pasó: cuándo fue, de quién, con quién y cómo terminó.
     *
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    private function pastCard(Appointment $appointment, array $names): array
    {
        /** @var CarbonInterface $startsAt */
        $startsAt = $appointment->starts_at;
        /** @var AppointmentStatus $status */
        $status = $appointment->status;
        /** @var Treatment $treatment */
        $treatment = $appointment->treatment;
        /** @var Doctor $doctor */
        $doctor = $appointment->doctor;

        return [
            'id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'date' => $startsAt->format('d/m/Y'),
            'name' => $names[$appointment->patient_id],
            'treatment' => $treatment->name,
            'doctor' => $doctor->name,
            'status' => $status->getLabel(),
            'tone' => match (true) {
                $status === AppointmentStatus::Completed => 'ok',
                in_array($status, AppointmentStatus::missed(), true) => 'bad',
                default => 'quiet',
            },
            'visit' => $status === AppointmentStatus::Completed ? $this->takenHome($appointment, $treatment) : null,
        ];
    }

    /**
     * Lo que el paciente se llevó a casa de una cita atendida, o null si el doctor no cerró la atención o no dejó
     * nada. De la atención solo salen las indicaciones: el motivo, el examen y lo que anotó el doctor se quedan.
     *
     * @return array{done: array<int, string>, indications: string|null, medicines: array<int, array{name: string, how: string|null}>, aftercare: string|null}|null
     */
    private function takenHome(Appointment $appointment, Treatment $treatment): ?array
    {
        /** @var Consultation|null $consultation */
        $consultation = $appointment->consultation;

        if ($consultation?->closed_at === null) {
            return null;
        }

        /** @var EloquentCollection<int, TreatmentPlanItem> $worked */
        $worked = $consultation->planItems;
        /** @var Prescription|null $prescription */
        $prescription = $consultation->prescription;
        /** @var EloquentCollection<int, PrescriptionItem> $medicines */
        $medicines = $prescription->items ?? new EloquentCollection;

        $visit = [
            'done' => $worked
                ->map(fn (TreatmentPlanItem $item) => $item->toothDescription() ? $item->label(withTooth: false).' · '.$item->toothDescription() : $item->label())
                ->unique()
                ->values()
                ->all(),
            'indications' => trim((string) $consultation->indications) ?: null,
            'medicines' => $medicines
                ->sortBy(['sort_order', 'id'])
                ->map(fn (PrescriptionItem $item) => ['name' => $item->medication, 'how' => $this->prescriptions->instructionsOf($item)])
                ->values()
                ->all(),
            'aftercare' => trim((string) $treatment->aftercare) ?: null,
        ];

        return array_filter($visit) === [] ? null : $visit;
    }

    /**
     * Una persona de la familia: sus datos y, si todavía no nos contó su salud, la invitación a hacerlo.
     *
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    private static function member(Patient $patient, array $names): array
    {
        /** @var PatientRelationship $relationship */
        $relationship = $patient->relationship;
        /** @var CarbonInterface|null $birthDate */
        $birthDate = $patient->birth_date;
        $self = $relationship === PatientRelationship::Self;

        return [
            'id' => $patient->id,
            'name' => $patient->full_name,
            'short_name' => $names[$patient->id],
            'initials' => Initials::of($patient->full_name),
            'is_self' => $self,
            'relationship' => $relationship->getLabel(),
            'document' => $patient->document_number ?: null,
            'birth_date' => $birthDate?->format('d/m/Y'),
            'email' => $patient->email ?: null,
            // Lo ya registrado no se muestra ni se cambia desde la web: solo se ofrece contarla a quien falta.
            'ask_health' => $patient->medicalBackground === null
                ? ($self ? 'Cuéntanos de tu salud' : 'Cuéntanos de la salud de '.$patient->first_name).' en dos minutos.'
                : null,
        ];
    }

    /**
     * Un plan como lo lee el paciente: cuántos tratamientos lleva hechos, cuándo es su próxima sesión, cómo va el
     * pago y cada cuota con su fecha.
     *
     * @param  array{total: float, paid: float, owed: float, remaining: float, late: bool, next: array{installment: TreatmentPlanInstallment, due: float}|null}|null  $progress
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    private function planCard(TreatmentPlan $plan, ?array $progress, ?string $payment, array $names): array
    {
        /** @var EloquentCollection<int, TreatmentPlanItem> $all */
        $all = $plan->items;
        // Lo que la familia aceptó y sigue en pie: lo cancelado y lo que todavía no firmó no se cuenta ni se muestra.
        $items = $all->whereNotNull('accepted_at')->where('status', '!=', PlanItemStatus::Cancelled)->values();
        /** @var TreatmentPlanStatus $status */
        $status = $plan->status;
        $finished = $status === TreatmentPlanStatus::Completed && ($progress['remaining'] ?? 0) <= 0;
        [$state, $tone] = self::planState($finished, $progress);
        /** @var Doctor|null $doctor */
        $doctor = $plan->doctor;

        return [
            'id' => $plan->id,
            'patient_id' => $plan->patient_id,
            'title' => $plan->title ?: 'Plan de tratamiento',
            'state' => $state,
            'tone' => $tone,
            'who' => $names[$plan->patient_id].' · '.$doctor?->name,
            'finished' => $finished,
            'included_in' => $progress === null ? self::payerName($plan) : null,
            'steps' => [
                'done' => $items->where('status', PlanItemStatus::Done)->count(),
                'started' => $items->where('status', PlanItemStatus::InProgress)->count(),
                'total' => $items->count(),
                // Las mismas palabras que lee el doctor en el panel (RN-28), contando solo lo que el paciente aceptó.
                'text' => $plan->progressText($items),
            ],
            'next_session' => self::nextSession($items),
            'progress' => $progress === null ? null : self::paymentProgress($progress),
            'payment' => $payment,
            'installments' => $progress !== null && $plan->hasSchedule() ? $this->installments($plan) : [],
            'items' => $items->map(fn (TreatmentPlanItem $item) => self::planItem($item))->values(),
        ];
    }

    /** En un 2x1, el nombre de quien paga el plan en el que va incluido este. */
    private static function payerName(TreatmentPlan $plan): ?string
    {
        /** @var TreatmentPlan|null $paid */
        $paid = $plan->includedIn;
        /** @var Patient|null $payer */
        $payer = $paid?->patient;

        return $payer?->first_name;
    }

    /**
     * La sesión del plan que ya tiene fecha y está más cerca: "Miércoles 4 de noviembre, 10:00 a. m.".
     *
     * @param  EloquentCollection<int, TreatmentPlanItem>  $items
     */
    private static function nextSession(EloquentCollection $items): ?string
    {
        /** @var Appointment|null $next */
        $next = $items->flatMap(fn (TreatmentPlanItem $item) => $item->appointments)->sortBy('starts_at')->first();

        if ($next === null) {
            return null;
        }

        /** @var CarbonInterface $startsAt */
        $startsAt = $next->starts_at;

        return Dates::day($startsAt).', '.Dates::time($startsAt);
    }

    /**
     * El estado del plan en una palabra, con su color: lo atrasado en rojo, lo que hay que pagar ya sin color.
     *
     * @param  array{total: float, paid: float, owed: float, remaining: float, late: bool, next: array{installment: TreatmentPlanInstallment, due: float}|null}|null  $progress
     * @return array{0: string, 1: string|null}
     */
    private static function planState(bool $finished, ?array $progress): array
    {
        return match (true) {
            $finished => ['Terminado', 'ok'],
            $progress === null => ['Incluido en el 2x1', 'ok'],
            $progress['late'] => ['Atrasado', 'bad'],
            $progress['owed'] > 0 => ['Por pagar', null],
            $progress['remaining'] <= 0 => ['Pagado', 'ok'],
            default => ['Al día', 'ok'],
        };
    }

    /**
     * Cómo va el pago del plan: cuánto se pagó del total, cuánto hay que pagar ya y cuál es el pago que sigue.
     *
     * @param  array{total: float, paid: float, owed: float, remaining: float, late: bool, next: array{installment: TreatmentPlanInstallment, due: float}|null}  $progress
     * @return array{percent: int, total: string, paid: string, remaining: string, owed: array{label: string, amount: string, late: bool}|null, next: array{amount: string, day: string, date: string, label: string}|null}
     */
    private static function paymentProgress(array $progress): array
    {
        return [
            'percent' => $progress['total'] > 0 ? (int) round(min(1, $progress['paid'] / $progress['total']) * 100) : 100,
            'total' => Money::format($progress['total']),
            'paid' => Money::format($progress['paid']),
            'remaining' => Money::format($progress['remaining']),
            'owed' => $progress['owed'] > 0 ? [
                'label' => $progress['late'] ? 'Vencido' : 'Para pagar ya',
                'amount' => Money::format($progress['owed']),
                'late' => $progress['late'],
            ] : null,
            'next' => $progress['next'] ? self::upcomingPayment($progress['next']['installment'], $progress['next']['due']) : null,
        ];
    }

    /**
     * El pago que sigue: cuánto, qué día y cuál es ("Cuota 3 de 6").
     *
     * @return array{amount: string, day: string, date: string, label: string}
     */
    private static function upcomingPayment(TreatmentPlanInstallment $installment, float $due): array
    {
        /** @var CarbonInterface $dueOn */
        $dueOn = $installment->due_on;

        return [
            'amount' => Money::format($due),
            'day' => self::dueDay($dueOn),
            // La fecha sola, para ordenar los próximos pagos de varios planes sin leer el texto del día.
            'date' => $dueOn->toDateString(),
            'label' => $installment->label(),
        ];
    }

    /**
     * Un tratamiento del plan: qué es, en qué pieza (con su nombre, no con su número) y cómo va.
     *
     * @return array{id: int, label: string, detail: string|null, state: string, status: string, sessions: array{done: int, planned: int}|null}
     */
    private static function planItem(TreatmentPlanItem $item): array
    {
        /** @var PlanItemStatus $status */
        $status = $item->status;
        $tooth = $item->toothDescription();
        $planned = (int) $item->sessions_planned;
        $done = $status === PlanItemStatus::Done;

        return [
            'id' => $item->id,
            'label' => $item->label(withTooth: $tooth === null),
            'detail' => $tooth,
            'state' => $planned > 1 && ! $done ? $item->sessions_done.' de '.$planned.' sesiones' : $status->getLabel(),
            'status' => $status->value,
            'sessions' => $planned > 1 ? ['done' => $done ? $planned : min((int) $item->sessions_done, $planned), 'planned' => $planned] : null,
        ];
    }

    /**
     * El cronograma del plan: cada pago con su fecha, su monto y cómo va.
     *
     * @return list<array{id: int, label: string, day: string, amount: string, state: string, state_label: string, left: string|null}>
     */
    private function installments(TreatmentPlan $plan): array
    {
        return array_map(function (array $row): array {
            $installment = $row['installment'];
            /** @var CarbonInterface $dueOn */
            $dueOn = $installment->due_on;
            $partlyPaid = in_array($row['state'], ['due', 'late'], true) && $row['due'] < $installment->net();

            return [
                'id' => $installment->id,
                'label' => $installment->label(),
                'day' => self::dueDay($dueOn),
                'amount' => Money::format($installment->net()),
                'state' => $row['state'],
                'state_label' => match ($row['state']) {
                    'paid' => 'Pagado',
                    'late' => 'Vencido',
                    'due' => 'Vence hoy',
                    default => 'Por vencer',
                },
                // Lo que ya abonó de este pago no se le vuelve a pedir: se dice cuánto falta.
                'left' => $partlyPaid ? Money::format($row['due']) : null,
            ];
        }, $this->billing->installmentStates($plan));
    }

    /** "Miércoles 2 de diciembre"; con el año cuando no es el de hoy, que un plan largo cruza de un año al otro. */
    private static function dueDay(CarbonInterface $date): string
    {
        return Dates::day($date).($date->isCurrentYear() ? '' : ' de '.$date->year);
    }
}
