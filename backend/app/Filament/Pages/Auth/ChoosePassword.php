<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasMaxWidth;
use Filament\Pages\Concerns\HasTopbar;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * "Elige tu contraseña": la primera pantalla de quien entra con una contraseña que le puso otra persona (el
 * administrador al crear su usuario o al cambiársela). Hasta que elige la suya no puede abrir nada más.
 * Va antes que la doble verificación: primero su contraseña y después la protege con su celular.
 *
 * @property-read Schema $form
 */
class ChoosePassword extends Page
{
    use HasMaxWidth, HasTopbar;

    protected static ?string $slug = 'elegir-contrasena';

    protected static ?string $title = 'Elige tu contraseña';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament-panels::components.layout.simple';

    /** Se abre sin la doble verificación activada: la persona nueva la activa después de elegir su contraseña. */
    protected static string|array $withoutRouteMiddleware = [EnsureMultiFactorAuthenticationIsEnabled::class];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        if (! $this->user()->must_change_password) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $this->form->fill();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'La contraseña con la que entraste te la puso otra persona. Elige una que solo tú conozcas para seguir.'
            .($this->hasDoubleCheck() ? '' : ' Después protegerás tu cuenta con tu celular.');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('password')
                    ->label('Contraseña nueva')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->required()
                    ->rule(Password::default())
                    ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                        if (Hash::check((string) $value, (string) $this->user()->password)) {
                            $fail('Elige una distinta de la que te dieron.');
                        }
                    })
                    ->helperText('Mínimo 8 caracteres, con letras y números.'),
                TextInput::make('passwordConfirmation')
                    ->label('Escríbela otra vez')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->required()
                    ->same('password')
                    ->validationMessages(['same' => 'No es igual a la de arriba.']),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')->label($this->hasDoubleCheck() ? 'Guardar y entrar' : 'Guardar y seguir')->submit('save'),
                            Action::make('leave')
                                ->label('Salir')
                                ->color('gray')
                                ->link()
                                ->action(fn () => $this->leave()),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $user = $this->user();
        $user->update(['password' => $this->form->getState()['password'], 'must_change_password' => false]);

        // Cambiar la contraseña cierra las demás sesiones; esta sigue abierta.
        session()->put(['password_hash_'.Filament::getAuthGuard() => $user->getAuthPassword()]);

        // Acaba de escribirla dos veces: al activar la doble verificación no se le vuelve a pedir.
        SetUpTwoFactor::rememberPasswordWasTyped();

        Notification::make()
            ->success()
            ->title('Contraseña guardada')
            ->body($this->hasDoubleCheck() ? 'Desde ahora entras con ella. Solo tú la conoces.' : 'Ahora protege tu cuenta con tu celular.')
            ->send();

        $this->redirect(Filament::getUrl());
    }

    /** Salir sin elegirla: la próxima vez que entre se le vuelve a pedir. */
    public function leave(): void
    {
        Filament::auth()->logout();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(Filament::getLoginUrl());
    }

    /** @return array<string, mixed> */
    protected function getLayoutData(): array
    {
        return [
            'hasTopbar' => $this->hasTopbar(),
            'maxContentWidth' => $maxContentWidth = $this->getMaxWidth() ?? $this->getMaxContentWidth(),
            'maxWidth' => $maxContentWidth,
        ];
    }

    /** Si ya tiene la doble verificación (se la pidieron al entrar); la persona nueva la activa después de esta pantalla. */
    private function hasDoubleCheck(): bool
    {
        return MultiFactorChallenge::make()->hasEnabledProviders($this->user());
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
