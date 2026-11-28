<?php

namespace App\Filament\Support;

use App\Filament\Pages\Auth\SetUpTwoFactor;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\OneTimeCodeInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Auth\Authenticatable;
use Livewire\Component;

/**
 * La doble verificación con Google Authenticator, como la trae Filament pero sin "Apagar": nadie se la apaga a sí
 * mismo desde Mi perfil (eso lo decide el administrador, ver DoubleCheck) y apagarla ahí solo llevaba a configurarla
 * de nuevo. En Mi perfil queda "Cambié de celular": se confirma con el celular de antes (o con un código de
 * recuperación) y enseguida se escanea el código con el nuevo.
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

    /**
     * Al entrar, "No tengo mi celular a la mano" deja solo el código de recuperación. Debajo, la casilla que ya viene
     * marcada: en este equipo no se le vuelve a pedir el código hasta mañana (DoubleCheck). Quien entra desde un equipo
     * prestado la desmarca.
     */
    public function getChallengeFormComponents(Authenticatable $user): array
    {
        return [
            ...self::recoveryCodeInsteadOfPhone(parent::getChallengeFormComponents($user), 'Mejor uso mi celular'),
            Checkbox::make(DoubleCheck::REMEMBER_FIELD)
                ->label('Hoy no volver a pedirme el código en este equipo')
                ->helperText('Mañana se te pide de nuevo. Desmárcala si esta computadora o este celular no es tuyo ni del consultorio.')
                ->default(true),
        ];
    }

    /**
     * El "Apagar" de Filament, con otro nombre (ver lang/vendor/.../disable.php): al confirmar lleva a activarla en el
     * nuevo. Para confirmar ya escribió su contraseña: en la pantalla de los tres pasos no se le vuelve a pedir.
     */
    private static function changePhone(Action $action): Action
    {
        $components = $action->getSchema(Schema::make())?->getComponents(withHidden: true) ?? [];

        return $action
            ->schema(self::recoveryCodeInsteadOfPhone($components, 'Todavía tengo el celular de antes'))
            ->color('gray')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->modalIcon(Heroicon::OutlinedDevicePhoneMobile)
            ->after(function (Component $livewire): void {
                SetUpTwoFactor::rememberPasswordWasTyped();

                $livewire->redirect((string) Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
            });
    }

    /**
     * Sin el celular a la mano se esconden los 6 casilleros y queda solo el código de recuperación, que entonces es
     * obligatorio. Antes los casilleros seguían a la vista y el navegador los pedía igual: había que tocar el botón dos
     * veces. Debajo queda la vuelta al celular, por si se tocó sin querer.
     *
     * @param  array<mixed>  $components
     * @return array<mixed>
     */
    private static function recoveryCodeInsteadOfPhone(array $components, string $backToPhone): array
    {
        foreach ($components as $component) {
            if ($component instanceof OneTimeCodeInput && $component->getName() === 'code') {
                $component->hidden(fn (Get $get): bool => (bool) $get('useRecoveryCode'));
            }

            if ($component instanceof TextInput && $component->getName() === 'recoveryCode') {
                $component
                    ->required()
                    ->belowContent(Action::make('usePhoneCode')
                        ->label($backToPhone)
                        ->link()
                        ->action(fn (Set $set) => $set('useRecoveryCode', false)));
            }
        }

        return $components;
    }
}
