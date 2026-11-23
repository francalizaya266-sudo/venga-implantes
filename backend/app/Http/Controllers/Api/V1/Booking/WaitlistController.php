<?php

namespace App\Http\Controllers\Api\V1\Booking;

use App\Enums\DayPeriod;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWaitlistRequest;
use App\Services\Booking\WaitlistService;
use App\Services\Booking\WebAvailability;
use App\Services\Booking\WebWaitlistData;
use App\Support\Phone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

/**
 * "Avísame si se libera un horario": quien no encuentra en la web un horario que le sirva deja su dato y queda en la
 * lista de espera del consultorio, en vez de irse sin nada.
 */
class WaitlistController extends Controller
{
    /** Anotaciones seguidas que se aceptan desde una misma conexión en una hora. */
    private const MAX_PER_HOUR = 5;

    public function store(StoreWaitlistRequest $request, WebAvailability $availability, WaitlistService $waitlist): JsonResponse
    {
        $data = $request->validated();
        $device = $request->device();
        $treatment = $availability->treatments()->firstWhere('id', (int) $data['treatment_id']);

        if ($treatment === null) {
            return response()->json(['message' => BookingException::treatmentNotBookableOnline()->getMessage()], 422);
        }

        $limiter = 'lista-web:'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiter, self::MAX_PER_HOUR)) {
            return response()->json(['message' => 'Te anotaste varias veces seguidas. Espera un rato o escríbenos por WhatsApp.'], 429);
        }

        // El doctor que eligió al ver los horarios, si atiende ese tratamiento; si no, cualquiera.
        $doctor = $availability->doctorsFor($treatment)->firstWhere('id', (int) ($data['doctor_id'] ?? 0));
        $phone = $device ? $device->account->phone : (string) $data['phone'];

        try {
            $waitlist->addFromWeb(new WebWaitlistData(
                phone: $phone,
                patientId: isset($data['patient_id']) ? (int) $data['patient_id'] : null,
                firstName: $data['first_name'] ?? null,
                lastName: $data['last_name'] ?? null,
                period: DayPeriod::from($data['preferred_period']),
                days: filled($data['days'] ?? null) ? $data['days'] : null,
                acceptsPrivacy: $request->boolean('accepts_privacy') || $request->privacyAlreadyAccepted(),
            ), $treatment, $doctor, $device);
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        RateLimiter::hit($limiter, 3600);

        return response()->json([
            'message' => 'Listo, te anotamos. Si se libera un horario, te escribimos por WhatsApp al '.Phone::display($phone).'.',
        ], 201);
    }
}
