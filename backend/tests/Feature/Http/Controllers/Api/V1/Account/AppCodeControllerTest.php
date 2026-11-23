<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Account;

use App\Models\Account;
use App\Models\Patient;
use App\Models\RememberedDevice;
use App\Services\Access\DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Código para la app instalada en iPhone: Mi cuenta en Safari lo muestra y, escrito en la app, la deja abierta igual
 * que en Safari. Sirve una vez y por 15 minutos.
 */
class AppCodeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCredentials();
        $this->account = Account::factory()->create();
        Patient::factory()->for($this->account)->create();
    }

    private function codeFromSafari(bool $verified = true): string
    {
        $token = 'safari-'.RememberedDevice::count();
        RememberedDevice::create(['account_id' => $this->account->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMonth(), 'verified_at' => $verified ? now() : null]);

        $code = $this->withCookie(DeviceService::COOKIE, $token)
            ->postJson(route('api.v1.account.app-code.store'))
            ->assertOk()
            ->assertJsonPath('data.minutes', 15)
            ->json('data.code');

        // La app instalada no tiene la cookie de Safari.
        $this->defaultCookies = [];

        return $code;
    }

    public function test_the_app_opens_my_account_with_the_code_that_safari_shows(): void
    {
        $code = $this->codeFromSafari();

        $this->getJson(route('api.v1.account.show'))->assertJsonPath('data.guest', true);

        $this->postJson(route('api.v1.account.app-code.enter'), ['code' => substr($code, 0, 3).' '.substr($code, 3)])
            ->assertOk()
            ->assertJsonPath('message', 'Listo. Mi cuenta ya está abierta en esta app.')
            ->assertCookie(DeviceService::COOKIE);

        $this->assertSame(2, RememberedDevice::count());
        $app = RememberedDevice::query()->latest('id')->firstOrFail();
        $this->assertTrue($app->account->is($this->account));
        $this->assertTrue($app->isVerified());
    }

    public function test_a_code_works_once_and_for_15_minutes(): void
    {
        $code = $this->codeFromSafari();
        $this->postJson(route('api.v1.account.app-code.enter'), ['code' => $code])->assertOk();

        $this->postJson(route('api.v1.account.app-code.enter'), ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Ese código no sirve: ya se usó o pasaron más de 15 minutos. Pide otro en Mi cuenta, en Safari.');

        $late = $this->codeFromSafari();
        $this->travel(16)->minutes();
        $this->postJson(route('api.v1.account.app-code.enter'), ['code' => $late])->assertUnprocessable();
    }

    public function test_a_phone_that_only_booked_passes_on_only_what_it_sees(): void
    {
        $code = $this->codeFromSafari(verified: false);

        $this->postJson(route('api.v1.account.app-code.enter'), ['code' => $code])->assertOk();

        $this->assertFalse(RememberedDevice::query()->latest('id')->firstOrFail()->isVerified());
    }

    public function test_only_a_remembered_phone_can_show_a_code_and_guessing_is_cut_off(): void
    {
        $this->postJson(route('api.v1.account.app-code.store'))->assertForbidden();

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('api.v1.account.app-code.enter'), ['code' => (string) (100000 + $attempt)])->assertUnprocessable();
        }

        $this->postJson(route('api.v1.account.app-code.enter'), ['code' => '100006'])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Demasiados intentos. Espera un minuto y vuelve a intentarlo.');
    }
}
