<?php

namespace App\Http\Middleware;

use App\Filament\Support\DoubleCheck;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quien todavía no activó la doble verificación no pasa de la pantalla para activarla. Es el de Filament
 * (EnsureMultiFactorAuthenticationIsEnabled) con una salvedad: no detiene a quien el administrador dejó de pedírsela,
 * sea a todo el equipo o solo a esa persona.
 */
class RequireDoubleCheck
{
    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if ($user instanceof User && DoubleCheck::isPendingFor($user)) {
            return redirect()->guest((string) Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
        }

        return $next($request);
    }
}
