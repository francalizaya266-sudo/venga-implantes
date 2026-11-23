<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * La doble verificación con Google Authenticator, como la trae Filament pero sin "Apagar": en este panel es
 * obligatoria y apagarla solo llevaba a configurarla de nuevo. En Mi perfil queda "Cambié de celular": se confirma
 * con el celular de antes (o con un código de recuperación) y enseguida se escanea el código con el nuevo.
 */
class PhoneAuthentication extends AppAuthentication
{
    /** @return array<Action> */
    public function getActions(): array
    {
        return array_map(
            fn (Action $action): Action => $action->getName() === 'disableAppAuthentication' ? self::changePhone($action) : $action,
            parent::getActions(),
        );
    }

    /** El "Apagar" de Filament, con otro nombre (ver lang/vendor/.../disable.php): al confirmar lleva a activarla en el nuevo. */
    private static function changePhone(Action $action): Action
    {
        return $action
            ->color('gray')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->modalIcon(Heroicon::OutlinedDevicePhoneMobile)
            ->after(fn (Component $livewire) => $livewire->redirect((string) Filament::getSetUpRequiredMultiFactorAuthenticationUrl()));
    }
}
