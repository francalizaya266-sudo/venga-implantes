<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\RememberedDevice;
use App\Services\Access\PatientAccess;
use App\Services\Notices\PhoneNotices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Avisos de Mi cuenta: el paciente elige si los de sus citas y cuotas le llegan en la pantalla de este celular, por
 * WhatsApp o por los dos. Solo desde un celular verificado (que tocó un enlace del número): cambia lo de toda la familia.
 */
class NoticesController extends Controller
{
    public function __construct(private readonly PatientAccess $access, private readonly PhoneNotices $notices) {}

    /** El navegador de este celular aceptó mostrar los avisos: aquí llega su dirección y sus claves. */
    public function subscribe(Request $request): JsonResponse
    {
        $device = $this->verifiedDevice($request);
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:120'],
            'keys.auth' => ['required', 'string', 'max:60'],
            'content_encoding' => ['nullable', 'string', 'in:aes128gcm,aesgcm'],
        ]);

        $this->notices->subscribe($device, [
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
        ]);

        return response()->json(['message' => 'Listo. Los avisos de tus citas y cuotas saldrán en la pantalla de este celular.']);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $this->notices->unsubscribe($this->verifiedDevice($request));

        return response()->json(['message' => 'Listo. Este celular ya no mostrará los avisos.']);
    }

    /** Si además le llegan por WhatsApp. Para dejar WhatsApp, antes tiene que tener los avisos en un celular. */
    public function whatsapp(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $this->verifiedDevice($request)->account;
        $whatsapp = $request->validate(['whatsapp' => ['required', 'boolean']])['whatsapp'];

        if (! $this->notices->receiveByWhatsApp($account, (bool) $whatsapp)) {
            $problem = 'Primero activa los avisos en este celular: así no te quedas sin enterarte de tus citas.';

            return response()->json(['message' => $problem, 'errors' => ['whatsapp' => [$problem]]], 422);
        }

        return response()->json(['message' => $whatsapp
            ? 'Listo. Tus avisos también llegarán por WhatsApp.'
            : 'Listo. Tus avisos ya no llegarán por WhatsApp, solo a tu celular. Los enlaces para entrar siguen llegando por WhatsApp.']);
    }

    private function verifiedDevice(Request $request): RememberedDevice
    {
        $device = $this->access->device($request) ?? abort(403);

        abort_unless($this->notices->isAvailable() && $device->isVerified(), 403);

        return $device;
    }
}
