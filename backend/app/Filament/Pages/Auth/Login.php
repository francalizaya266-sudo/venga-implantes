<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Support\DoubleCheck;
use App\Filament\Support\LoginChallenge;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Entrar al panel: la de Filament, con dos cambios. Debajo de "Confirma que eres tú" dice "Abre Google Authenticator en
 * tu celular"; a quien tocó "No tengo mi celular a la mano" eso ya no le sirve, así que se le dice con qué entra.
 * Y el código del celular se pide una vez al día en cada equipo, y solo a quien se le pide (ver DoubleCheck).
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();
        $user = Filament::auth()->user();

        // Entró y la pantalla del código sigue armada: lo acaba de escribir (o un código de recuperación). Si no
        // desmarcó la casilla, este equipo queda recordado hasta la medianoche.
        if ($response !== null && $user instanceof User && filled($this->userUndertakingMultiFactorAuthentication) && data_get($this->data, 'multiFactor.app.'.DoubleCheck::REMEMBER_FIELD)) {
            DoubleCheck::rememberThisDeviceToday($user);
        }

        return $response;
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication) && data_get($this->data, 'multiFactor.app.useRecoveryCode')) {
            return 'Sin tu celular, entras con un código de recuperación.';
        }

        return parent::getSubheading();
    }

    protected function getMultiFactorChallenge(): MultiFactorChallenge
    {
        return LoginChallenge::make();
    }
}
