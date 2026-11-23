<?php

namespace App\Services\Access;

use App\Models\Account;
use App\Models\RememberedDevice;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Dispositivo recordado: el paciente reserva con un toque y ve sus próximas citas. Cuando desde ese celular se tocó
 * un enlace que llegó al WhatsApp del número, también ve sus planes, pagos, citas pasadas y datos, hasta que toque
 * "Salir en este celular", pase mucho tiempo sin entrar o el consultorio cierre Mi cuenta en sus celulares.
 */
class DeviceService
{
    public const COOKIE = 'cd_device';

    /** @param  bool  $verified  Si llegó con un enlace del número; si no, queda sin verificar hasta que toque uno. */
    public function remember(Account $account, Request $request, bool $verified = true): RememberedDevice
    {
        $token = Str::random(48);
        $days = (int) Setting::get('devices.remember_days', 400);

        $device = $account->rememberedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
            'expires_at' => now()->addDays($days),
            'verified_at' => $verified ? now() : null,
        ]);

        Cookie::queue(Cookie::make(self::COOKIE, $token, $days * 24 * 60, httpOnly: true, sameSite: 'lax'));

        return $device;
    }

    /** Dispositivo recordado de esta petición; cada uso extiende su vigencia. */
    public function current(Request $request): ?RememberedDevice
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return null;
        }

        $device = RememberedDevice::query()
            ->usable()
            ->where('token_hash', hash('sha256', $token))
            ->with('account')
            ->first();

        if ($device === null || $device->account === null || $device->account->isBlocked()) {
            return null;
        }

        $device->forceFill([
            'last_used_at' => now(),
            'expires_at' => now()->addDays((int) Setting::get('devices.remember_days', 400)),
        ])->save();

        return $device;
    }

    /** El celular tocó un enlace de su número: queda verificado. */
    public function verify(RememberedDevice $device): void
    {
        if (! $device->isVerified()) {
            $device->forceFill(['verified_at' => now()])->save();
        }
    }

    /** Los celulares que entraron sin enlace dejan de estar recordados. */
    public function forgetUnverified(Account $account): void
    {
        $account->rememberedDevices()->whereNull('revoked_at')->whereNull('verified_at')->update(['revoked_at' => now()]);
    }

    /** "Salir en este celular": deja de estar recordado. */
    public function forget(Request $request): void
    {
        $token = $request->cookie(self::COOKIE);

        if (is_string($token) && $token !== '') {
            RememberedDevice::query()->where('token_hash', hash('sha256', $token))->update(['revoked_at' => now()]);
        }

        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /**
     * Desde el panel, si el paciente perdió o cambió de celular: Mi cuenta deja de estar abierta en todos los suyos.
     *
     * @return int Cuántos celulares dejaron de estar recordados.
     */
    public function forgetAll(Account $account): int
    {
        return RememberedDevice::query()->whereBelongsTo($account)->usable()->update(['revoked_at' => now()]);
    }
}
