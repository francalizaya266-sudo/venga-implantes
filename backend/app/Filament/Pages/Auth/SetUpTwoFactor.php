<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Pages\SetUpRequiredMultiFactorAuthentication;
use Filament\Facades\Filament;
use Filament\Forms\Components\OneTimeCodeInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * "Protege tu cuenta con tu celular": la doble verificación en tres pasos a la vista (instalar Google Authenticator,
 * escanear el código y escribir los 6 números) y, ya activada, los códigos de recuperación para guardar antes de
 * entrar. La ve quien todavía no la tiene: la persona nueva después de elegir su contraseña, o a quien se la quitaron.
 *
 * @property-read Schema $form
 */
class SetUpTwoFactor extends SetUpRequiredMultiFactorAuthentication
{
    /** Cuándo escribió su contraseña en esta sesión (al entrar o al elegirla): si fue hace poco, no se le vuelve a pedir. */
    public const PASSWORD_TYPED_AT = 'auth.password_typed_at';

    private const PASSWORD_STILL_FRESH_SECONDS = 15 * 60;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** El secreto y los códigos de recuperación por activar, cifrados: no viajan legibles al navegador. */
    public ?string $pending = null;

    /** Ya activada: quedan a la vista los códigos de recuperación hasta que entra. */
    public bool $isActivated = false;

    /** Al entrar con su contraseña o al elegirla: lo que mira esta pantalla para no pedirla otra vez. */
    public static function rememberPasswordWasTyped(): void
    {
        session()->put(self::PASSWORD_TYPED_AT, now()->getTimestamp());
    }

    public function mount(): void
    {
        if ($this->isEnabled()) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $provider = self::provider();

        $this->pending = encrypt([
            'secret' => $provider->generateSecret(),
            'recoveryCodes' => $provider->generateRecoveryCodes(),
            'userKey' => Filament::getUserScopedAuthIdentifier($this->user()),
        ]);

        $this->form->fill();
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->isActivated ? 'Guarda tus códigos de recuperación' : parent::getHeading();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->isActivated ? 'Si un día no tienes tu celular a la mano, entras con uno de estos códigos.' : parent::getSubheading();
    }

    public function form(Schema $schema): Schema
    {
        $provider = self::provider();
        $secret = (string) ($this->pending()['secret'] ?? '');

        return $schema
            ->components([
                View::make('filament.auth.two-factor-steps')
                    ->viewData([
                        'qrCode' => $secret !== '' ? $provider->generateQrCodeDataUri($secret) : null,
                        'secret' => trim(chunk_split($secret, 4, ' ')),
                    ])
                    ->schema([
                        OneTimeCodeInput::make('code')
                            ->label('Escribe los 6 números que te muestra')
                            ->hiddenLabel()
                            ->belowContent('Cambian cada 30 segundos: escribe los que ves ahora.')
                            ->required()
                            ->rule(fn () => function (string $attribute, #[SensitiveParameter] mixed $value, Closure $fail) use ($provider, $secret): void {
                                if (! is_string($value) || ! $provider->verifyCode($value, $secret)) {
                                    $fail('Ese código no coincide. Escribe el que aparece ahora en la aplicación.');
                                }
                            }),
                    ]),
                // Si escribió su contraseña hace un momento (al entrar o al elegirla) no se le pide de nuevo.
                TextInput::make('password')
                    ->label('Tu contraseña')
                    ->helperText('Por seguridad, escríbela para activar.')
                    ->password()
                    ->revealable()
                    ->autocomplete('current-password')
                    ->currentPassword(guard: Filament::getAuthGuard())
                    ->required()
                    ->dehydrated(false)
                    ->visible(fn (): bool => ! self::passwordIsFresh()),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('activate')
                ->footer([
                    Actions::make([
                        Action::make('activate')->label('Activar')->submit('activate'),
                    ])->fullWidth(),
                ])
                ->hidden(fn (): bool => $this->isActivated),
            // Ya activada: sus códigos de recuperación, para guardarlos antes de entrar.
            View::make('filament.auth.recovery-codes')
                ->viewData(fn (): array => ['codes' => $this->pending()['recoveryCodes'] ?? []])
                ->visible(fn (): bool => $this->isActivated),
            Actions::make([
                Action::make('enter')
                    ->label('Ya los guardé, entrar')
                    ->action(fn () => $this->enter()),
            ])
                ->fullWidth()
                ->visible(fn (): bool => $this->isActivated),
            Actions::make([
                Action::make('leave')
                    ->label('Salir')
                    ->color('gray')
                    ->link()
                    ->action(fn () => $this->leave()),
            ])
                ->alignCenter()
                ->hidden(fn (): bool => $this->isActivated),
        ]);
    }

    /** Con el código que muestra su celular queda activada; enseguida se le muestran sus códigos de recuperación. */
    public function activate(): void
    {
        if ($this->isActivated) {
            return;
        }

        $user = $this->user();
        $pending = $this->pending();

        // Lo que se armó al abrir la pantalla es de esa persona: no sirve si en esta sesión entró otra.
        if (($pending['userKey'] ?? null) !== Filament::getUserScopedAuthIdentifier($user) || $this->isEnabled()) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $rateLimitingKey = 'filament:auth:set_up_app_authentication:rate_limit:'.Filament::getUserScopedAuthIdentifier($user);

        if (RateLimiter::tooManyAttempts($rateLimitingKey, maxAttempts: 5)) {
            throw ValidationException::withMessages(['data.code' => 'Demasiados intentos. Espera un momento y vuelve a intentarlo.']);
        }

        RateLimiter::hit($rateLimitingKey);

        $this->form->getState();

        $provider = self::provider();

        DB::transaction(function () use ($provider, $pending, $user): void {
            $provider->saveSecret($user, $pending['secret']);
            $provider->saveRecoveryCodes($user, $pending['recoveryCodes']);
        });

        $this->isActivated = true;
    }

    /** Con sus códigos guardados, a la pantalla que quería abrir (o a Hoy). */
    public function enter(): void
    {
        $this->redirect((string) session()->pull('url.intended', Filament::getUrl()));
    }

    /** Salir sin activarla: la próxima vez que entre se le vuelve a pedir. */
    public function leave(): void
    {
        Filament::auth()->logout();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(Filament::getLoginUrl());
    }

    /** @return array<string, mixed> */
    private function pending(): array
    {
        return filled($this->pending) ? (array) decrypt($this->pending) : [];
    }

    private static function passwordIsFresh(): bool
    {
        return now()->getTimestamp() - (int) session(self::PASSWORD_TYPED_AT, 0) < self::PASSWORD_STILL_FRESH_SECONDS;
    }

    private static function provider(): AppAuthentication
    {
        /** @var AppAuthentication $provider */
        $provider = Filament::getMultiFactorAuthenticationProviders()['app'];

        return $provider;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
