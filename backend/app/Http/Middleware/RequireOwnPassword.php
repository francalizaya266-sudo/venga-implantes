<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Auth\ChoosePassword;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quien entra con una contraseña que le puso otra persona no pasa de la pantalla donde elige la suya: así el
 * administrador que lo creó deja de conocerla.
 */
class RequireOwnPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        // La pantalla para activar el segundo factor también pasa: si no, las dos se mandarían una a la otra.
        $allowedRoutes = [
            ChoosePassword::getRouteName(),
            'filament.*.auth.logout',
            'filament.*.auth.multi-factor-authentication.set-up-required',
        ];

        if ($request->user()?->must_change_password && ! $request->routeIs(...$allowedRoutes)) {
            return redirect()->to(ChoosePassword::getUrl());
        }

        return $next($request);
    }
}
