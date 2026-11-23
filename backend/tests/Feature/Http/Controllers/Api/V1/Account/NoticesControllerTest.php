<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Account;

use App\Models\Account;
use App\Models\Patient;
use App\Models\PushSubscription;
use App\Models\RememberedDevice;
use App\Services\Access\DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Avisos en Mi cuenta: desde un celular verificado, el paciente los activa en la pantalla de su celular y elige si
 * además le llegan por WhatsApp. Sin un celular con avisos, todo sigue por WhatsApp.
 */
class NoticesControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        config(['consultorio.push.public_key' => 'clave-publica', 'consultorio.push.private_key' => 'clave-privada']);
        // El frontend vive en el mismo dominio: sus peticiones llevan las cookies (el celular recordado).
        $this->withCredentials();
        $this->account = Account::factory()->create();
        Patient::factory()->for($this->account)->create(['relationship' => 'self']);
    }

    private function onThisPhone(bool $verified = true, string $token = 'este-celular'): RememberedDevice
    {
        $device = RememberedDevice::create(['account_id' => $this->account->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMonth(), 'verified_at' => $verified ? now() : null]);
        $this->withCookie(DeviceService::COOKIE, $token);

        return $device;
    }

    /** @return array<string, mixed> */
    private function browserSubscription(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc'): array
    {
        return ['endpoint' => $endpoint, 'expirationTime' => null, 'keys' => ['p256dh' => 'BPublica', 'auth' => 'secreto'], 'content_encoding' => 'aes128gcm'];
    }

    public function test_a_verified_phone_sees_that_notices_go_by_whatsapp_until_it_turns_them_on(): void
    {
        $this->onThisPhone();

        $this->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.notices', ['key' => 'clave-publica', 'this_phone' => false, 'phones' => 0, 'whatsapp' => true]);
    }

    public function test_without_the_clinic_keys_or_from_a_phone_that_only_booked_there_are_no_notices_to_choose(): void
    {
        $this->onThisPhone(verified: false);
        $this->getJson(route('api.v1.account.show'))->assertJsonPath('data.notices', null);
        $this->postJson(route('api.v1.account.notices.subscribe'), $this->browserSubscription())->assertForbidden();

        config(['consultorio.push.public_key' => null]);
        $this->onThisPhone(token: 'otro-celular');
        $this->getJson(route('api.v1.account.show'))->assertJsonPath('data.notices', null);
        $this->postJson(route('api.v1.account.notices.whatsapp'), ['whatsapp' => false])->assertForbidden();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_turning_them_on_saves_this_phone_and_turning_them_off_forgets_it(): void
    {
        $device = $this->onThisPhone();

        $this->postJson(route('api.v1.account.notices.subscribe'), $this->browserSubscription())
            ->assertOk()
            ->assertJsonPath('message', 'Listo. Los avisos de tus citas y cuotas saldrán en la pantalla de este celular.');

        $phone = PushSubscription::sole();
        $this->assertTrue($phone->device->is($device));
        $this->assertSame(hash('sha256', 'https://fcm.googleapis.com/fcm/send/abc'), $phone->endpoint_hash);
        $this->getJson(route('api.v1.account.show'))->assertJsonPath('data.notices.this_phone', true)->assertJsonPath('data.notices.phones', 1);

        // El navegador da una dirección nueva al volver a activarlos: la anterior de este celular ya no sirve.
        $this->postJson(route('api.v1.account.notices.subscribe'), $this->browserSubscription('https://fcm.googleapis.com/fcm/send/nueva'))->assertOk();
        $this->assertSame('https://fcm.googleapis.com/fcm/send/nueva', PushSubscription::sole()->endpoint);

        $this->postJson(route('api.v1.account.notices.unsubscribe'))->assertOk()->assertJsonPath('message', 'Listo. Este celular ya no mostrará los avisos.');
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_only_a_browser_address_is_accepted(): void
    {
        $this->onThisPhone();

        $this->postJson(route('api.v1.account.notices.subscribe'), ['endpoint' => 'http://no-cifrado.test/x', 'keys' => ['p256dh' => 'a']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys.auth']);
    }

    public function test_leaving_whatsapp_needs_a_phone_with_notices_and_comes_back_when_none_is_left(): void
    {
        $this->onThisPhone();

        $this->postJson(route('api.v1.account.notices.whatsapp'), ['whatsapp' => false])
            ->assertUnprocessable()
            ->assertJsonPath('errors.whatsapp.0', 'Primero activa los avisos en este celular: así no te quedas sin enterarte de tus citas.');
        $this->assertTrue($this->account->fresh()->whatsapp_notices);

        $this->postJson(route('api.v1.account.notices.subscribe'), $this->browserSubscription())->assertOk();
        $this->postJson(route('api.v1.account.notices.whatsapp'), ['whatsapp' => false])
            ->assertOk()
            ->assertJsonPath('message', 'Listo. Tus avisos ya no llegarán por WhatsApp, solo a tu celular. Los enlaces para entrar siguen llegando por WhatsApp.');
        $this->getJson(route('api.v1.account.show'))->assertJsonPath('data.notices.whatsapp', false);

        // Sin celulares con avisos, todo vuelve a WhatsApp; y el siguiente que los active empieza con los dos.
        $this->postJson(route('api.v1.account.notices.unsubscribe'))->assertOk();
        $this->getJson(route('api.v1.account.show'))->assertJsonPath('data.notices.whatsapp', true);
        $this->postJson(route('api.v1.account.notices.subscribe'), $this->browserSubscription())->assertOk();
        $this->assertTrue($this->account->fresh()->whatsapp_notices);
    }

    public function test_leaving_this_phone_or_closing_my_account_on_every_phone_stops_the_notices_there(): void
    {
        $this->onThisPhone();
        $this->postJson(route('api.v1.account.notices.subscribe'), $this->browserSubscription())->assertOk();
        PushSubscription::factory()->for($this->account)->create();

        $this->postJson(route('api.v1.account.logout'))->assertOk();
        $this->assertDatabaseCount('push_subscriptions', 1);

        app(DeviceService::class)->forgetAll($this->account);
        $this->assertDatabaseCount('push_subscriptions', 0);
    }
}
