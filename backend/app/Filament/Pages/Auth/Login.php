<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Entrar al panel: la de Filament, con un cambio. Debajo de "Confirma que eres tú" dice "Abre Google Authenticator en
 * tu celular"; a quien tocó "No tengo mi celular a la mano" eso ya no le sirve, así que se le dice con qué entra.
 */
class Login extends BaseLogin
{
    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication) && data_get($this->data, 'multiFactor.app.useRecoveryCode')) {
            return 'Sin tu celular, entras con un código de recuperación.';
        }

        return parent::getSubheading();
    }
}
