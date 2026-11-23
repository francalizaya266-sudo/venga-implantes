<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Services\Access\AppCode;
use App\Services\Access\PatientAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Código para la app instalada en iPhone: Mi cuenta en Safari lo muestra y la app lo pide la primera vez que se abre,
 * porque no comparte lo que Safari recuerda.
 */
class AppCodeController extends Controller
{
    public function __construct(private readonly PatientAccess $access, private readonly AppCode $codes) {}

    public function store(Request $request): JsonResponse
    {
        $device = $this->access->device($request) ?? abort(403);

        return response()->json([
            'data' => ['code' => $this->codes->issue($device), 'minutes' => AppCode::MINUTES],
            'message' => null,
        ]);
    }

    public function enter(Request $request): JsonResponse
    {
        $code = (string) preg_replace('/\D/', '', (string) $request->validate(['code' => ['required', 'string', 'max:12']], attributes: ['code' => 'Código'])['code']);

        // 5 intentos por minuto desde cada conexión y 60 entre todas: adivinar un código de 6 números no es práctico.
        foreach (['codigo-app:'.$request->ip() => 5, 'codigo-app' => 60] as $key => $perMinute) {
            if (RateLimiter::tooManyAttempts($key, $perMinute)) {
                return response()->json(['message' => 'Demasiados intentos. Espera un minuto y vuelve a intentarlo.'], 429);
            }

            RateLimiter::hit($key, 60);
        }

        if (strlen($code) !== 6 || $this->codes->enter($code, $request) === null) {
            $problem = 'Ese código no sirve: ya se usó o pasaron más de '.AppCode::MINUTES.' minutos. Pide otro en Mi cuenta, en Safari.';

            return response()->json(['message' => $problem, 'errors' => ['code' => [$problem]]], 422);
        }

        return response()->json(['message' => 'Listo. Mi cuenta ya está abierta en esta app.']);
    }
}
