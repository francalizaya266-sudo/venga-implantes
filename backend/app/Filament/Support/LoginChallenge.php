<?php

namespace App\Filament\Support;

use App\Models\User;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * La pantalla del código al entrar: no se arma si a la persona no se le pide la doble verificación (apagada para
 * todos o para ella) o si hoy ya escribió el código en este equipo. Una pantalla del código que ya está abierta sí
 * se responde: lo que cambia es si se empieza a pedir, no si un código pendiente se da por bueno.
 */
class LoginChallenge extends MultiFactorChallenge
{
    public function getFirstEnabledProvider(Authenticatable $user): ?MultiFactorAuthenticationProvider
    {
        if ($user instanceof User && (! DoubleCheck::isAskedOf($user) || DoubleCheck::wasPassedTodayOnThisDevice($user))) {
            return null;
        }

        return parent::getFirstEnabledProvider($user);
    }
}
