<?php

namespace App\Http\Controllers\Api\V1\Booking;

use App\Enums\AppointmentOrigin;
use App\Enums\PatientRelationship;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBookingRequest;
use App\Http\Resources\V1\SlotPickerResource;
use App\Http\Resources\V1\TreatmentResource;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Campaign;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\RememberedDevice;
use App\Models\Treatment;
use App\Services\Access\PatientAccess;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\WebAvailability;
use App\Services\Booking\WebBookingData;
use App\Services\Web\ClinicInfo;
use App\Services\Web\PageMeta;
use App\Support\CampaignText;
use App\Support\Dates;
use App\Support\FamilyNames;
use App\Support\Money;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * Reserva en la web en pocos pasos: qué necesita, día y hora, sus datos y listo.
 * Desde un dispositivo recordado no pide el celular y deja elegir al familiar.
 */
class BookingController extends Controller
{
    /** Reservas seguidas que se aceptan desde una misma conexión en una hora. */
    private const MAX_PER_HOUR = 8;

    public function __construct(
        private readonly WebAvailability $availability,
        private readonly PatientAccess $access,
    ) {}

    /**
     * Con qué empieza la reserva: los servicios que se reservan solos, la promoción con que llegó y, desde un
     * celular recordado, la familia entre la que elige.
     *
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function options(Request $request, PageMeta $meta): array
    {
        $promoCode = strtoupper(substr(trim((string) $request->query('codigo')), 0, 30));
        $campaignSlug = filled($request->query('promo')) ? substr((string) $request->query('promo'), 0, 120) : null;
        $campaign = Campaign::forBooking($promoCode, $campaignSlug);
        $treatments = $this->availability->treatments();
        $device = $this->access->device($request);

        // Con una promoción de un tratamiento que no se reserva solo, se empieza por la evaluación.
        $initial = $treatments->firstWhere('slug', (string) $request->query('tratamiento'))
            ?? ($campaign?->treatment_id ? ($treatments->firstWhere('id', $campaign->treatment_id) ?? $treatments->firstWhere('is_evaluation', true)) : null);

        return [
            'data' => [
                'treatments' => $treatments->map(fn (Treatment $treatment) => [
                    ...TreatmentResource::make($treatment)->resolve(),
                    'label' => $treatment->is_evaluation ? 'No sé qué necesito: '.mb_strtolower($treatment->name) : $treatment->name,
                    'price' => $this->priceOf($treatment, $campaign),
                    'price_line' => CampaignText::price($treatment, $campaign),
                ])->values(),
                'initial_treatment_id' => $initial?->getKey(),
                // Doctor elegido desde su ficha en la web: se usa en cuanto elige un tratamiento que atiende.
                'preferred_doctor_id' => filled($request->query('doctor')) ? Doctor::query()->where('slug', (string) $request->query('doctor'))->value('id') : null,
                'campaign' => $campaign ? [
                    'name' => $campaign->name,
                    'discount' => CampaignText::discount($campaign),
                    'treatment' => $campaign->treatment ? ['id' => $campaign->treatment->getKey(), 'name' => $campaign->treatment->name] : null,
                ] : null,
                'promo_code' => $promoCode,
                'promo_problem' => filled($promoCode) && $campaign === null ? Campaign::UNKNOWN_CODE : null,
                'campaign_slug' => $campaignSlug,
                'origin' => AppointmentOrigin::tryFrom((string) $request->query('origen'))?->value,
                'device' => $device ? $this->family($device, (int) $request->query('para')) : null,
                'relationships' => collect(PatientRelationship::cases())
                    ->reject(fn (PatientRelationship $relationship) => $relationship === PatientRelationship::Self)
                    ->map(fn (PatientRelationship $relationship) => ['value' => $relationship->value, 'label' => $relationship->getLabel()])
                    ->values(),
            ],
            'meta' => $meta->for(page: 'reservar', description: 'Reserva tu cita en línea: elige el servicio, el día y la hora. Confirmas por WhatsApp.'),
        ];
    }

    /**
     * Los próximos días con horarios libres para un servicio (con un doctor o con el primero disponible) y los
     * horarios del primer día, o del que ya venía elegido.
     *
     * @return array{data: array<string, mixed>}
     */
    public function days(Request $request): array
    {
        $treatment = $this->treatment($request);
        $doctors = $this->availability->doctorsFor($treatment);
        $doctorId = $doctors->contains('id', $request->integer('doctor')) ? $request->integer('doctor') : null;
        $searched = $this->searched($doctors, $doctorId);

        $days = array_map(fn (CarbonImmutable $day) => $day->toDateString(), $this->availability->days($treatment, $searched));
        $date = in_array($request->query('date'), $days, true) ? (string) $request->query('date') : ($days[0] ?? null);
        $slots = $date ? $this->availability->slots($treatment, $searched, CarbonImmutable::parse($date)) : collect();

        return ['data' => [
            'doctors' => $doctors->map(fn (Doctor $doctor) => ['id' => $doctor->getKey(), 'name' => $doctor->name])->values(),
            'doctor_id' => $doctorId,
            ...SlotPickerResource::make(['days' => $days, 'date' => $date, 'slots' => $slots, 'suggested' => $this->availability->suggested($slots)])->resolve(),
        ]];
    }

    /**
     * Los horarios libres de un día.
     *
     * @return array{data: array<string, mixed>}
     */
    public function slots(Request $request): array
    {
        $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        $treatment = $this->treatment($request);
        $doctors = $this->availability->doctorsFor($treatment);
        $searched = $this->searched($doctors, $doctors->contains('id', $request->integer('doctor')) ? $request->integer('doctor') : null);
        $slots = $this->availability->slots($treatment, $searched, CarbonImmutable::parse((string) $request->query('date')));

        return ['data' => SlotPickerResource::make([
            'days' => null,
            'date' => (string) $request->query('date'),
            'slots' => $slots,
            'suggested' => $this->availability->suggested($slots),
        ])->resolve()];
    }

    /** Reserva la cita. Si el horario se acaba de ocupar responde 409, para volver a elegir. */
    public function store(StoreBookingRequest $request, AppointmentService $appointments, ClinicInfo $clinic): JsonResponse
    {
        $data = $request->validated();
        $device = $request->device();
        $treatment = $this->availability->treatments()->firstWhere('id', (int) $data['treatment_id']);

        if ($treatment === null) {
            return $this->problem(BookingException::treatmentNotBookableOnline()->getMessage());
        }

        $limiter = 'reserva-web:'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiter, self::MAX_PER_HOUR)) {
            return $this->problem('Hiciste varias reservas seguidas. Espera un rato o escríbenos por WhatsApp.', 429);
        }

        $doctors = $this->availability->doctorsFor($treatment);
        $searched = $this->searched($doctors, $doctors->contains('id', (int) ($data['doctor_id'] ?? 0)) ? (int) $data['doctor_id'] : null);
        $slot = $this->availability->slots($treatment, $searched, CarbonImmutable::parse($data['date']))->firstWhere('time', $data['time']);

        if ($slot === null) {
            return $this->slotTaken();
        }

        $family = $device ? $this->patients($device) : collect();
        $existing = isset($data['patient_id']) ? $family->firstWhere('id', (int) $data['patient_id']) : null;

        if (isset($data['patient_id']) && $existing === null) {
            return $this->problem('No encontramos a esa persona en este celular. Elige de nuevo para quién es la cita.');
        }

        // Un número que el consultorio no tenía: la cuenta nace con esta reserva y este celular queda recordado.
        $newNumber = $device === null && Account::withTrashed()->where('phone', Phone::normalize($data['phone']))->doesntExist();

        try {
            $appointment = $appointments->bookFromWeb(new WebBookingData(
                doctorId: $slot['doctor_id'],
                treatmentId: $treatment->getKey(),
                startsAt: CarbonImmutable::parse($data['date'].' '.$data['time']),
                phone: $device ? $device->account->phone : $data['phone'],
                patientId: $existing?->getKey(),
                firstName: $existing ? null : $data['first_name'],
                lastName: $existing ? null : $data['last_name'],
                birthDate: ! $existing && filled($data['birth_date'] ?? null) ? CarbonImmutable::parse($data['birth_date']) : null,
                relationship: $this->relationship($data, $family),
                acceptsPrivacy: $request->boolean('accepts_privacy') || $request->privacyAlreadyAccepted(),
                acceptsPromotions: $request->boolean('accepts_promotions'),
                origin: AppointmentOrigin::tryFrom((string) ($data['origin'] ?? '')),
                promoCode: filled($data['promo_code'] ?? null) ? $data['promo_code'] : null,
                note: filled($data['note'] ?? null) ? $data['note'] : null,
                campaignSlug: $data['campaign_slug'] ?? null,
            ), $device);
        } catch (BookingException $e) {
            return $e->getMessage() === BookingException::slotTaken()->getMessage() ? $this->slotTaken() : $this->problem($e->getMessage());
        } catch (AuthorizationException $e) {
            return $this->problem($e->getMessage(), 403);
        }

        RateLimiter::hit($limiter, 3600);

        $appointment->load(['patient.account', 'doctor', 'treatment', 'campaign']);

        if ($newNumber) {
            $this->access->rememberNewcomer($appointment->patient->account, $request);
        }

        $asksOrigin = $this->asksOrigin($appointment, $device);
        $day = Dates::day($appointment->starts_at->toImmutable());
        $hour = Dates::time($appointment->starts_at->format('H:i'));

        return response()->json(['data' => [
            'treatment' => $appointment->treatment->name,
            'when' => $day.', '.$hour,
            'who' => $appointment->patient->full_name.' · con '.$appointment->doctor->name,
            'price_line' => CampaignText::price($appointment->treatment, $appointment->campaign),
            'phone' => Phone::display($appointment->patient->account->phone),
            'remembered' => $device !== null || $newNumber,
            // La cita como archivo para el calendario del celular. La dirección va firmada: solo la tiene quien reservó.
            'calendar_url' => URL::signedRoute('site.booking.calendar', ['appointment' => $appointment->getKey()], absolute: false),
            // Para escribir al consultorio con la reserva ya contada.
            'whatsapp_message' => "Hola, acabo de reservar {$appointment->treatment->name} para el ".mb_strtolower($day).' a las '.$hour." a nombre de {$appointment->patient->full_name}.",
            'origins' => $asksOrigin ? self::originsToAsk()->map(fn (AppointmentOrigin $origin) => ['value' => $origin->value, 'label' => $origin->getLabel()]) : [],
            'origin_url' => $asksOrigin ? URL::signedRoute('api.v1.booking.origin', ['appointment' => $appointment->getKey()], absolute: false) : null,
        ]], 201);
    }

    /**
     * "¿Cómo nos conociste?" se pregunta con la cita ya reservada, no antes: solo a quien reserva desde un
     * celular nuevo y todavía no lo dijo (ni llegó desde un anuncio o una promoción que ya lo dice).
     */
    public function origin(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate(['origin' => ['required', Rule::in(self::originsToAsk()->map->value->all())]]);

        if ($this->asksOrigin($appointment, $this->access->device($request))) {
            $origin = AppointmentOrigin::from($data['origin']);
            $appointment->update(['origin' => $origin]);
            $appointment->patient->update(['origin' => $origin]);
        }

        return response()->json(['message' => '¡Gracias por contarnos!']);
    }

    private function asksOrigin(Appointment $appointment, ?RememberedDevice $device): bool
    {
        // El celular que quedó recordado con esta misma reserva (un número nuevo) todavía no lo dijo.
        return ($device === null || ! $device->isVerified()) && $appointment->origin === null && $appointment->patient->origin === null;
    }

    /** @return Collection<int, AppointmentOrigin> Campaña y referido no se preguntan: los pone el sistema. */
    private static function originsToAsk(): Collection
    {
        return collect(AppointmentOrigin::cases())->reject(fn (AppointmentOrigin $origin) => in_array($origin, [AppointmentOrigin::Campaign, AppointmentOrigin::Referral], true))->values();
    }

    private function treatment(Request $request): Treatment
    {
        return $this->availability->treatments()->firstWhere('id', $request->integer('treatment')) ?? abort(404);
    }

    /**
     * Los doctores entre los que se buscan horarios: el elegido o, sin elegir, todos los que atienden el servicio.
     *
     * @param  Collection<int, Doctor>  $doctors
     * @return Collection<int, Doctor>
     */
    private function searched(Collection $doctors, ?int $doctorId): Collection
    {
        return $doctorId ? $doctors->where('id', $doctorId)->values() : $doctors;
    }

    /**
     * El precio de un servicio en el primer paso: con la promoción, el normal tachado y el nuevo.
     *
     * @return array{was: string|null, now: string}|null
     */
    private function priceOf(Treatment $treatment, ?Campaign $campaign): ?array
    {
        if ($campaignPrice = $campaign?->priceFor($treatment)) {
            return ['was' => Money::short($treatment->price_from), 'now' => 'desde '.Money::short($campaignPrice)];
        }

        return $treatment->show_price && $treatment->price_from ? ['was' => null, 'now' => 'desde '.Money::short($treatment->price_from)] : null;
    }

    /**
     * Pacientes a cargo del celular recordado, con el titular primero. Quien reservó con este número desde otro
     * celular no se ofrece hasta que la familia confirme su cita.
     *
     * @return Collection<int, Patient>
     */
    private function patients(RememberedDevice $device): Collection
    {
        $account = $device->account;

        return $account->patients()
            ->whereKeyNot($account->guestIds())
            ->orderByRaw('relationship = ? desc', [PatientRelationship::Self->value])
            ->orderBy('first_name')
            ->get();
    }

    /**
     * La familia del celular recordado para elegir de quién es la cita. De quien está en tratamiento se dice
     * desde el primer paso: su doctor programa sus sesiones y la web no las reserva.
     *
     * @return array<string, mixed>
     */
    private function family(RememberedDevice $device, int $preferredPatientId): array
    {
        $family = $this->patients($device);
        $names = FamilyNames::for($family);

        return [
            'phone' => Phone::display($device->account->phone),
            'family' => $family->map(fn (Patient $patient) => [
                'id' => $patient->getKey(),
                'name' => $names[$patient->getKey()],
                'first_name' => $patient->first_name,
                'is_self' => $patient->relationship === PatientRelationship::Self,
                'in_treatment' => $patient->hasActiveTreatment(),
            ])->values(),
            // Hay un titular registrado en el celular recordado: "otra persona" es un familiar.
            'has_holder' => $family->contains('relationship', PatientRelationship::Self),
            // El titular va primero; desde "Te toca tu control" en el inicio, ya a nombre de quien le toca.
            'patient_id' => $family->contains('id', $preferredPatientId) ? $preferredPatientId : $family->first()?->getKey(),
            'privacy_accepted' => $device->account->privacy_accepted_at !== null,
            'promotions_accepted' => (bool) $device->account->accepts_promotions,
        ];
    }

    /**
     * Para mí (si el celular aún no tiene titular) o el parentesco elegido.
     *
     * @param  array<string, mixed>  $data
     * @param  Collection<int, Patient>  $family
     */
    private function relationship(array $data, Collection $family): PatientRelationship
    {
        $relationship = PatientRelationship::tryFrom((string) $data['relationship']) ?? PatientRelationship::Child;

        if (($data['who'] ?? 'me') === 'me' && ! $family->contains('relationship', PatientRelationship::Self)) {
            return PatientRelationship::Self;
        }

        return $relationship === PatientRelationship::Self ? PatientRelationship::Other : $relationship;
    }

    private function slotTaken(): JsonResponse
    {
        return response()->json(['message' => BookingException::slotTaken()->getMessage(), 'code' => 'slot_taken'], 409);
    }

    private function problem(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
