<?php

namespace App\Filament\Support;

use App\Models\Setting;
use App\Models\User;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Cookie;

/**
 * A quién se le pide la doble verificación del panel (el código del celular además de la contraseña) y cuándo.
 * Sigue siendo obligatoria para todos, con dos salvedades:
 *
 * - Quien ya escribió el código en un equipo no lo vuelve a escribir ese día en ese equipo: queda una cookie que vence
 *   a la medianoche. Se recuerda al equipo, no a la persona: en otra computadora u otro celular se le pide igual.
 * - El administrador la puede apagar para todo el equipo (Ajustes › Seguridad) o para una sola persona (Usuarios).
 *   Las dos cosas se confirman con su contraseña y quedan en el historial de cambios con su nombre.
 */
final class DoubleCheck
{
    /** El ajuste que la enciende o la apaga para todo el equipo. */
    public const REQUIRED_SETTING = 'security.double_check_required';

    /** La casilla "Hoy no volver a pedirme el código en este equipo" de la pantalla del código. */
    public const REMEMBER_FIELD = 'rememberDevice';

    public static function isOnForEveryone(): bool
    {
        return (bool) Setting::get(self::REQUIRED_SETTING, true);
    }

    /** Si a esta persona se le pide el código de su celular. */
    public static function isAskedOf(User $user): bool
    {
        return self::isOnForEveryone() && ! $user->skips_double_check;
    }

    /** Se le pide y todavía no la activó: al entrar solo ve la pantalla para activarla. */
    public static function isPendingFor(User $user): bool
    {
        return self::isAskedOf($user) && ! MultiFactorChallenge::make()->hasEnabledProviders($user);
    }

    /** La cookie del día. Una por persona: en la computadora de recepción entra más de una. */
    public static function cookieName(User $user): string
    {
        return 'cd_panel_checked_'.$user->getKey();
    }

    /** Acaba de escribir el código de su celular en este equipo: hasta la medianoche no se le vuelve a pedir aquí. */
    public static function rememberThisDeviceToday(User $user): void
    {
        $until = now()->endOfDay()->getTimestamp();

        Cookie::queue(Cookie::make(
            self::cookieName($user),
            $until.'|'.self::proof($user, $until),
            (int) ceil(($until - now()->getTimestamp()) / 60),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }

    /** Si hoy ya escribió el código en el equipo desde el que entra ahora. */
    public static function wasPassedTodayOnThisDevice(User $user): bool
    {
        $cookie = request()->cookie(self::cookieName($user));

        if (! is_string($cookie) || ! str_contains($cookie, '|')) {
            return false;
        }

        [$until, $proof] = explode('|', $cookie, 2);

        return ctype_digit($until)
            && (int) $until >= now()->getTimestamp()
            && hash_equals(self::proof($user, (int) $until), $proof);
    }

    /** Apagarla le quita una protección a la cuenta: quien lo hace lo confirma con su propia contraseña. */
    public static function ownPasswordField(): TextInput
    {
        return TextInput::make('password')
            ->label('Tu contraseña')
            ->helperText('Por seguridad, escríbela para confirmar.')
            ->password()
            ->revealable()
            ->autocomplete('current-password')
            ->currentPassword(guard: Filament::getAuthGuard())
            ->required()
            ->dehydrated(false);
    }

    /**
     * Lo que va en la cookie no se puede armar desde afuera ni estirar: va firmado con la clave del sistema, para esa
     * persona y hasta esa hora. La firma también lleva su contraseña y el secreto de su celular: si cambia la
     * contraseña, cambia de celular o el administrador le quita la doble verificación, deja de valer.
     */
    private static function proof(User $user, int $until): string
    {
        return hash_hmac('sha256', implode('|', [
            'panel-double-check',
            $user->getKey(),
            $until,
            $user->getAuthPassword(),
            $user->getAppAuthenticationSecret(),
        ]), (string) config('app.key'));
    }
}
