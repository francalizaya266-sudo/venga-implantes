<?php

namespace App\Services\Access;

use App\Models\Account;
use App\Models\RememberedDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Código para la app: en iPhone, la web agregada a la pantalla de inicio no comparte lo que recuerda Safari, así que
 * al abrirla por primera vez no sabe de quién es. Mi cuenta en Safari muestra un código de 6 números; escrito en la
 * app, ese celular queda recordado ahí también, igual que en Safari (verificado si allá lo estaba). Sirve una vez y
 * por 15 minutos.
 */
class AppCode
{
    public const MINUTES = 15;

    public function __construct(private readonly DeviceService $devices) {}

    public function issue(RememberedDevice $device): string
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while (Cache::has(self::key($code)));

        Cache::put(self::key($code), $device->getKey(), now()->addMinutes(self::MINUTES));

        return $code;
    }

    /** Null si el código no existe, ya se usó o venció, o el celular que lo mostró ya no está recordado. */
    public function enter(string $code, Request $request): ?RememberedDevice
    {
        $deviceId = Cache::pull(self::key($code));
        $source = is_int($deviceId) ? RememberedDevice::query()->usable()->with('account')->find($deviceId) : null;

        /** @var Account|null $account */
        $account = $source?->account;

        if ($source === null || $account === null || $account->isBlocked()) {
            return null;
        }

        return $this->devices->remember($account, $request, verified: $source->isVerified());
    }

    private static function key(string $code): string
    {
        return 'codigo-app:'.hash('sha256', $code);
    }
}
