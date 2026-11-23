<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Enums\AppointmentStatus;
use App\Enums\PatientRelationship;
use App\Enums\PlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Exceptions\BookingException;
use App\Filament\Resources\Patients\PatientResource;
use App\Http\Controllers\Api\V1\Account\Concerns\ChangesAppointments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangeAppointmentRequest;
use App\Http\Resources\V1\AppointmentCardResource;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\RememberedDevice;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Services\Access\DeviceService;
use App\Services\Access\PatientAccess;
use App\Services\Billing\BillingService;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\PatientChangePolicy;
use App\Services\Clinical\TreatmentPlanService;
use App\Services\StaffAlerts;
use App\Services\Web\PageMeta;
use App\Services\Web\ReturningPatient;
use App\Support\Dates;
use App\Support\FamilyNames;
use App\Support\Initials;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Mi cuenta: desde el celular recordado se ven y manejan las próximas citas de toda la
 * familia. Planes y pagos, citas pasadas y datos personales piden un enlace nuevo (verificación reforzada).
 */
class AccountController extends Controller
{
    use ChangesAppointments;

    public function __construct(
        private readonly PatientAccess $access,
        private readonly DeviceService $devices,
        private readonly AppointmentService $appointments,
        private readonly PatientChangePolicy $policy,
        private readonly BillingService $billing,
        private readonly TreatmentPlanService $plans,
        private readonly PageMeta $meta,
    ) {}

    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function show(Request $request): array
    {
        $meta = $this->meta->for(page: 'cuenta', noindex: true);
        $device = $this->access->device($request);

        if ($device === null) {
            return ['data' => ['guest' => true], 'meta' => $meta];
        }

        $account = $device->account;
        // Un celular que tocó un enlace del número ve todo; el que solo reservó, sus próximas citas.
        $elevated = $device->isVerified();
        $patients = $account->patients()->orderBy('first_name')->get();
        // Quien reservó con este número desde otro celular: su cita se ve, pero no cuenta como familia todavía.
        $guests = $account->guestIds();
        // La familia es quien ya está reconocido: a quien está por confirmar no se le saluda ni se le cuenta.
        $family = $patients->reject(fn (Patient $patient) => in_array($patient->getKey(), $guests, true))->values();
        $holder = $family->firstWhere('relationship', PatientRelationship::Self);
        // Dos personas de la familia con el mismo nombre se muestran con su apellido, para saber de quién es cada cita.
        $names = FamilyNames::for($patients);

        $appointments = fn () => Appointment::query()
            ->whereHas('patient', fn ($query) => $query->whereBelongsTo($account))
            ->with(['patient', 'doctor', 'treatment']);
        $upcoming = $appointments()
            ->with(['patient.medicalBackground', 'pendingRescheduleRequest.options'])
            ->where('starts_at', '>=', now()->startOfDay())
            ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn, AppointmentStatus::InProgress])
            ->oldest('starts_at')
            ->get();
        // "Antes de tu cita": en la primera cita de quien todavía no tiene antecedentes.
        $askHealth = $upcoming
            ->filter(fn (Appointment $appointment) => $appointment->patient->medicalBackground === null
                && in_array($appointment->status, AppointmentStatus::changeable(), true))
            ->unique('patient_id')
            ->pluck('id')
            ->all();

        return [
            'data' => [
                'guest' => false,
                'holder' => $holder ? ['first_name' => $holder->first_name, 'initials' => Initials::of($holder->full_name)] : null,
                'several' => $family->count() > 1,
                'phone' => Phone::display($account->phone),
                'elevated' => $elevated,
                'upcoming' => $upcoming->map(fn (Appointment $appointment) => AppointmentCardResource::make($appointment)->shownAs(
                    name: $appointment->patient->is($holder) ? null : $names[$appointment->patient_id],
                    guest: in_array((int) $appointment->patient_id, $guests, true),
                    askHealth: in_array($appointment->id, $askHealth, true),
                    // Pidió cambiarla y espera respuesta: se le recuerda y no se le ofrece pedirlo otra vez.
                    pendingRequest: $appointment->pendingRescheduleRequest,
                ))->values(),
                'plans' => $elevated ? $this->plansOf($account, $names) : null,
                'balances' => $elevated ? $this->balancesOf($patients) : null,
                'past' => $elevated ? $appointments()->where('starts_at', '<', now())->latest('starts_at')->limit(20)->get()->map(fn (Appointment $appointment) => [
                    'id' => $appointment->id,
                    'date' => $appointment->starts_at->format('d/m/Y'),
                    'name' => $names[$appointment->patient_id],
                    'treatment' => $appointment->treatment->name,
                    'status' => $appointment->status->getLabel(),
                ])->values() : null,
                'family' => $elevated ? $family->map(fn (Patient $patient) => [
                    'id' => $patient->id,
                    'name' => $patient->full_name,
                    'relationship' => $patient->relationship->getLabel(),
                    'document' => $patient->document_number ?: null,
                    'birth_date' => $patient->birth_date?->format('d/m/Y'),
                    'email' => $patient->email ?: null,
                ])->values() : null,
            ],
            'meta' => $meta,
        ];
    }

    /** "Pedir acceso" desde un celular nuevo: recepción le envía su enlace por WhatsApp. */
    public function requestAccess(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']], attributes: ['phone' => 'Celular']);

        if (($problem = Phone::problem($data['phone'])) !== null) {
            return response()->json(['message' => $problem, 'errors' => ['phone' => [$problem]]], 422);
        }

        $phone = Phone::normalize($data['phone']);

        if (! $this->throttle('acceso:'.$request->ip(), 5) || ! $this->throttle('acceso:'.$phone, 2)) {
            return response()->json(['message' => 'Ya recibimos tu pedido. Te escribiremos pronto por WhatsApp.'], 429);
        }

        $this->access->requestAccess($data['phone']);

        // No dice si el número existe: a todos se les responde lo mismo.
        return response()->json(['data' => [
            'phone' => Phone::display($phone),
            'whatsapp_message' => 'Hola, pedí mi enlace para entrar a Mi cuenta. Mi celular es '.Phone::display($phone).'.',
        ]]);
    }

    /** Desde un celular que reservó sin enlace: pedir el enlace para ver planes, pagos, citas pasadas y datos. */
    public function requestVerification(Request $request): JsonResponse
    {
        $device = $this->device($request);

        if ($this->throttle('verificar:'.$device->account_id, 2)) {
            $this->access->requestAccess($device->account->phone);
        }

        return $this->done('Te enviaremos un enlace por WhatsApp. Al tocarlo, verás en este celular tus planes, pagos, citas anteriores y datos.');
    }

    public function confirm(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);

        if ($appointment->status === AppointmentStatus::Pending && $this->policy->canChange($appointment)) {
            $this->appointments->confirmByPatient($appointment);
        }

        return $this->done('¡Listo! La cita de '.$appointment->patient->first_name.' quedó confirmada.');
    }

    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);

        $request->validate(['reason' => ['nullable', 'string']]);

        try {
            $this->appointments->cancelByPatient($appointment, $request->string('reason')->limit(250)->toString() ?: null);
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->done('La cita fue cancelada.');
    }

    /**
     * "Pedir mi próxima sesión" desde la portada: quien está en tratamiento no reserva solo (su doctor programa las
     * sesiones), así que recepción recibe el pedido con su nombre y le escribe con el horario. Uno cada 12 horas.
     */
    public function requestSession(Request $request, Patient $patient, ReturningPatient $returning, StaffAlerts $alerts): JsonResponse
    {
        $device = $this->device($request);
        abort_unless($device->account_id === $patient->account_id, 403);

        $row = $returning->for($device->account)['sessions']->firstWhere('patient.id', $patient->getKey());

        if ($row === null) {
            return response()->json(['message' => null]);
        }

        $key = ReturningPatient::sessionRequestKey($patient);

        if (! RateLimiter::tooManyAttempts($key, 1)) {
            RateLimiter::hit($key, 12 * 3600);
            $alerts->toReception(
                'Pide su próxima sesión',
                "{$patient->full_name} quiere agendar su próxima sesión (lleva {$row['done']} de {$row['total']}".($row['doctor'] ? ", con {$row['doctor']}" : '').'). Escríbele con el horario.',
                link: ['label' => 'Ver paciente', 'url' => PatientResource::getUrl('view', ['record' => $patient])],
            );
        }

        return $this->done('Listo: recepción ya sabe que '.($patient->relationship === PatientRelationship::Self ? 'quieres tu' : "{$patient->first_name} quiere su").' próxima sesión. Te escribiremos por WhatsApp con el horario.');
    }

    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function change(Request $request, Appointment $appointment): array
    {
        $this->authorizeAppointment($request, $appointment);

        return ['data' => $this->changeScreen($appointment), 'meta' => $this->meta->for(title: 'Cambiar el horario', noindex: true)];
    }

    /**
     * @return array{data: array<string, mixed>}
     */
    public function changeSlots(Request $request, Appointment $appointment): array
    {
        $this->authorizeAppointment($request, $appointment);
        $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        return ['data' => $this->changePicker($appointment, null, (string) $request->query('date'))];
    }

    public function saveChange(ChangeAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);

        return $this->applyChange($appointment, $request->validated('chosen'), $request->validated('reason'));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->devices->forget($request);

        return $this->done('Listo. Este celular ya no está recordado.');
    }

    /**
     * Los planes que la familia aceptó, con lo hecho, lo pagado y la próxima cuota. Los terminados y
     * pagados ya no se muestran.
     *
     * @param  array<int, string>  $names
     * @return Collection<int, array<string, mixed>>
     */
    private function plansOf(Account $account, array $names): Collection
    {
        return TreatmentPlan::query()
            ->whereHas('patient', fn ($query) => $query->whereBelongsTo($account))
            ->whereNotNull('accepted_at')
            ->whereNot('status', TreatmentPlanStatus::Cancelled)
            ->with(['patient', 'doctor', 'items', 'installments', 'includedIn.patient'])
            ->latest('accepted_at')
            ->get()
            ->map(fn (TreatmentPlan $plan) => [
                'plan' => $plan,
                // En un 2x1, la otra persona no paga: su plan va incluido en el de quien paga.
                'progress' => $plan->included_in_plan_id ? null : $this->billing->planProgress($plan),
                'payment' => $plan->included_in_plan_id ? null : $this->plans->paymentText($plan),
            ])
            ->reject(fn (array $row) => $row['plan']->status === TreatmentPlanStatus::Completed && ($row['progress']['remaining'] ?? 0) <= 0)
            ->map(fn (array $row) => $this->planCard($row['plan'], $row['progress'], $row['payment'], $names))
            ->values();
    }

    /**
     * Un plan como lo lee el paciente: cómo va el pago y cuántos tratamientos lleva hechos.
     *
     * @param  array{total: float, paid: float, owed: float, remaining: float, late: bool, next: array<string, mixed>|null}|null  $progress
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    private function planCard(TreatmentPlan $plan, ?array $progress, ?string $payment, array $names): array
    {
        $items = $plan->items->filter(fn (TreatmentPlanItem $item) => $item->accepted_at !== null && $item->status !== PlanItemStatus::Cancelled)->values();
        $done = $items->where('status', PlanItemStatus::Done)->count();
        [$state, $tone] = match (true) {
            $progress === null => ['Incluido en el 2x1', 'ok'],
            $progress['late'] => ['Atrasado', 'bad'],
            $progress['owed'] > 0 => ['Por pagar', null],
            $progress['remaining'] <= 0 => ['Pagado', 'ok'],
            default => ['Al día', 'ok'],
        };

        return [
            'id' => $plan->id,
            'title' => $plan->title ?: 'Plan de tratamiento',
            'state' => $state,
            'tone' => $tone,
            'who' => $names[$plan->patient_id].' · '.$plan->doctor?->name,
            'included_in' => $progress === null ? $plan->includedIn?->patient?->first_name : null,
            'progress' => $progress === null ? null : [
                'percent' => $progress['total'] > 0 ? (int) round(min(1, $progress['paid'] / $progress['total']) * 100) : 100,
                'total' => Money::format($progress['total']),
                'paid' => Money::format($progress['paid']),
                'remaining' => Money::format($progress['remaining']),
                'owed' => $progress['owed'] > 0 ? [
                    'label' => $progress['late'] ? 'Vencido' : 'Para pagar ya',
                    'amount' => Money::format($progress['owed']),
                    'late' => $progress['late'],
                ] : null,
                'next' => $progress['next'] ? [
                    'amount' => Money::format($progress['next']['due']),
                    'day' => Dates::day($progress['next']['installment']->due_on),
                    'label' => $progress['next']['installment']->label(),
                ] : null,
            ],
            'payment' => $payment,
            'items_summary' => 'Tratamientos: '.$done.' de '.$items->count().' '.($items->count() === 1 ? 'hecho' : 'hechos'),
            'items' => $items->map(fn (TreatmentPlanItem $item) => [
                'id' => $item->id,
                'label' => $item->label(),
                'state' => $item->sessions_planned > 1 && $item->status !== PlanItemStatus::Done
                    ? $item->sessions_done.' de '.$item->sessions_planned.' sesiones'
                    : $item->status->getLabel(),
                'done' => $item->status === PlanItemStatus::Done,
            ])->values(),
        ];
    }

    /**
     * El saldo de cada persona de la familia que debe o tiene algo a favor.
     *
     * @param  Collection<int, Patient>  $patients
     * @return Collection<int, array{name: string, text: string, owed: bool}>
     */
    private function balancesOf(Collection $patients): Collection
    {
        return $patients
            ->map(fn (Patient $patient) => ['name' => $patient->full_name, 'balance' => $patient->balance()])
            ->reject(fn (array $row) => $row['balance'] === 0.0)
            ->map(fn (array $row) => [
                'name' => $row['name'],
                'text' => $row['balance'] > 0 ? Money::format($row['balance']).' por pagar' : Money::format(-$row['balance']).' a favor',
                'owed' => $row['balance'] > 0,
            ])
            ->values();
    }

    private function device(Request $request): RememberedDevice
    {
        return $this->access->device($request) ?? abort(403);
    }

    private function authorizeAppointment(Request $request, Appointment $appointment): void
    {
        abort_unless($this->access->canAccess($request, $appointment), 403);
    }

    private function done(string $message): JsonResponse
    {
        return response()->json(['message' => $message]);
    }

    /** true si todavía puede intentarlo (y cuenta el intento). */
    private function throttle(string $key, int $perHour): bool
    {
        if (RateLimiter::tooManyAttempts($key, $perHour)) {
            return false;
        }

        RateLimiter::hit($key, 3600);

        return true;
    }
}
