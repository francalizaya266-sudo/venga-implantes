<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\PushSubscription;
use App\Models\RememberedDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    /**
     * Un celular recordado y verificado de la cuenta, con el permiso de su navegador.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/'.Str::random(40);

        return [
            'account_id' => Account::factory(),
            'remembered_device_id' => fn (array $attributes) => RememberedDevice::create([
                'account_id' => $attributes['account_id'],
                'token_hash' => hash('sha256', Str::random(48)),
                'expires_at' => now()->addYear(),
                'verified_at' => now(),
            ])->getKey(),
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'public_key' => rtrim(strtr(base64_encode(random_bytes(65)), '+/', '-_'), '='),
            'auth_token' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
            'content_encoding' => 'aes128gcm',
        ];
    }
}
