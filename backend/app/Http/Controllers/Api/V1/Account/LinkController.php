<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Exceptions\BookingException;
use App\Http\Controllers\Api\V1\Account\Concerns\ChangesAppointments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangeAppointmentRequest;
use App\Http\Resources\V1\AppointmentCardResource;
use App\Models\MagicLink;
use App\Services\Access\MagicLinkService;
use App\Services\Access\PatientAccess;
use App\Services\Booking\AppointmentChange;
use App\Services\Booking\AppointmentService;
use App\Services\Web\PageMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Enlace personal que llega por WhatsApp. El de una cita la muestra para confirmarla,
 * cambiarla o cancelarla; el de acceso abre Mi cuenta. Abrir el enlace no cambia nada
 * (la vista previa de WhatsApp también lo abre): solo los botones actúan.
 */
class LinkController extends Controller
{
    use ChangesAppointments;

    public function __construct(
        private readonly MagicLinkService $links,
        private readonly PatientAccess $access,
        private readonly AppointmentService $appointments,
        private readonly PageMeta $meta,
    ) {}

    /**
     * Qué abre el enlace: "expired" (ya no vale), "access" (entrar a Mi cuenta) o "appointment" (una cita).
     *
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function show(string $token, AppointmentChange $change): array
    {
        $link = $this->links->find($token);

        if ($link === null) {
            return ['data' => ['kind' => 'expired'], 'meta' => $this->meta->for(title: 'Enlace vencido', noindex: true)];
        }

        if ($link->appointment === null) {
            return ['data' => ['kind' => 'access'], 'meta' => $this->meta->for(title: 'Entrar a Mi cuenta', noindex: true)];
        }

        $appointment = $link->appointment->load(['patient', 'doctor', 'treatment']);
        // A quien está por confirmar no se le saluda por su nombre: el enlace llega al dueño del número.
        $guest = in_array((int) $appointment->patient_id, $appointment->patient->account?->guestIds() ?? [], true);

        return [
            'data' => [
                'kind' => 'appointment',
                'title' => $guest ? '¿Reconoces esta cita?' : 'Hola, '.$appointment->patient->first_name,
                'appointment' => AppointmentCardResource::make($appointment)->shownAs(
                    name: $guest ? $appointment->patient->short_name : null,
                    guest: $guest,
                    pendingRequest: $change->pendingRequest($appointment),
                ),
            ],
            'meta' => $this->meta->for(title: 'Tu cita', noindex: true),
        ];
    }

    public function confirm(Request $request, string $token): JsonResponse
    {
        $link = $this->act($request, $token);
        $this->appointments->confirmByPatient($link->appointment);

        return response()->json(['message' => '¡Listo! Tu asistencia quedó confirmada.']);
    }

    public function cancel(Request $request, string $token): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string']]);

        $link = $this->act($request, $token);

        try {
            $this->appointments->cancelByPatient($link->appointment, $request->string('reason')->limit(250)->toString() ?: null);
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Tu cita fue cancelada. Cuando quieras, puedes reservar otra.']);
    }

    /** Enlace de acceso: el paciente tocó "Entrar" y este celular queda recordado. */
    public function enter(Request $request, string $token): JsonResponse
    {
        $link = $this->links->find($token);
        abort_if($link === null || $link->appointment_id !== null, 404);

        $this->access->actWithLink($request, $link);

        return response()->json(['message' => '¡Listo! Ya puedes ver tus citas. Este celular quedará recordado.']);
    }

    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function change(string $token): array
    {
        return [
            'data' => $this->changeScreen($this->linkOfAppointment($token)->appointment),
            'meta' => $this->meta->for(title: 'Cambiar el horario', noindex: true),
        ];
    }

    /**
     * @return array{data: array<string, mixed>}
     */
    public function changeSlots(Request $request, string $token): array
    {
        $link = $this->linkOfAppointment($token);
        $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        return ['data' => $this->changePicker($link->appointment, null, (string) $request->query('date'))];
    }

    /** Cambiar el horario también es actuar con el enlace: el celular queda recordado. */
    public function saveChange(ChangeAppointmentRequest $request, string $token): JsonResponse
    {
        $link = $this->linkOfAppointment($token);
        $response = $this->applyChange($link->appointment, $request->validated('chosen'), $request->validated('reason'));

        if ($response->isSuccessful()) {
            $this->access->actWithLink($request, $link);
        }

        return $response;
    }

    private function act(Request $request, string $token): MagicLink
    {
        $link = $this->linkOfAppointment($token);
        $this->access->actWithLink($request, $link);

        return $link;
    }

    private function linkOfAppointment(string $token): MagicLink
    {
        $link = $this->links->find($token);
        abort_if($link?->appointment === null, 404);

        return $link;
    }
}
