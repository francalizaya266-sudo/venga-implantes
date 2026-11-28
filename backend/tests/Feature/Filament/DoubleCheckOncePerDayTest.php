<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\SetUpTwoFactor;
use App\Filament\Support\DoubleCheck;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Cookie as DeviceCookie;
use Tests\TestCase;

/**
 * El código del celular se pide una vez al día en cada equipo: quien ya lo escribió entra el resto del día solo con
 * su contraseña en esa computadora o celular. Se recuerda al equipo, no a la persona, y vence a la medianoche.
 */
class DoubleCheckOncePerDayTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_after_the_code_the_same_device_enters_with_only_the_password_until_midnight(): void
    {
        $this->travelTo('2026-11-28 08:15:00');
        $rosa = User::factory()->reception()->create();
        $device = $this->enterWithTheCode($rosa);

        $this->travelTo('2026-11-28 23:59:30');
        $login = $this->typePassword($rosa, $device);

        $login->assertHasNoErrors()->assertSet('userUndertakingMultiFactorAuthentication', null);
        $this->assertAuthenticatedAs($rosa);
    }

    public function test_the_device_is_remembered_until_the_end_of_that_day_and_out_of_reach_of_scripts(): void
    {
        $this->travelTo('2026-11-28 08:15:00');
        $rosa = User::factory()->reception()->create();

        $device = $this->enterWithTheCode($rosa);

        $this->assertSame('2026-11-28 23:59:59', now()->setTimestamp((int) strtok((string) $device->getValue(), '|'))->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-29 00:00:00', now()->setTimestamp($device->getExpiresTime())->format('Y-m-d H:i:s'));
        $this->assertTrue($device->isHttpOnly());
        $this->assertSame('lax', $device->getSameSite());
    }

    public function test_the_next_day_the_same_device_is_asked_for_the_code_again(): void
    {
        $this->travelTo('2026-11-28 22:40:00');
        $rosa = User::factory()->reception()->create();
        $device = $this->enterWithTheCode($rosa);

        $this->travelTo('2026-11-29 00:00:01');
        $login = $this->typePassword($rosa, $device);

        $login->assertNotSet('userUndertakingMultiFactorAuthentication', null)->assertSee('Confirma que eres tú');
        $this->assertGuest();
    }

    public function test_another_device_is_asked_for_the_code_the_same_day(): void
    {
        $rosa = User::factory()->reception()->create();
        $this->enterWithTheCode($rosa);

        $login = $this->typePassword($rosa);

        $login->assertNotSet('userUndertakingMultiFactorAuthentication', null);
        $this->assertGuest();
    }

    public function test_on_a_shared_computer_one_persons_code_does_not_let_another_person_in(): void
    {
        $rosa = User::factory()->reception()->create();
        $carla = User::factory()->admin()->create();
        $device = $this->enterWithTheCode($rosa);

        // La computadora de recepción trae la cookie de Rosa. Carla la presenta también como si fuera la suya.
        Livewire::withCookie(DoubleCheck::cookieName($rosa), $device->getValue());
        $login = $this->typePassword($carla, new DeviceCookie(DoubleCheck::cookieName($carla), $device->getValue()));

        $login->assertNotSet('userUndertakingMultiFactorAuthentication', null);
        $this->assertGuest();
    }

    public function test_a_remembered_device_still_needs_the_right_password(): void
    {
        $rosa = User::factory()->reception()->create();
        $device = $this->enterWithTheCode($rosa);

        $login = $this->typePassword($rosa, $device, password: 'no-es-la-suya');

        $login->assertHasErrors(['data.email']);
        $this->assertGuest();
    }

    public function test_a_remembered_device_does_not_let_in_someone_whose_access_was_taken_away(): void
    {
        $rosa = User::factory()->reception()->create();
        $device = $this->enterWithTheCode($rosa);
        $rosa->update(['is_active' => false]);

        $login = $this->typePassword($rosa, $device);

        $login->assertHasErrors(['data.email']);
        $this->assertGuest();
    }

    public function test_changing_the_password_makes_every_device_ask_for_the_code_again(): void
    {
        $rosa = User::factory()->reception()->create();
        $device = $this->enterWithTheCode($rosa);
        $rosa->update(['password' => 'Otra-clave-2026']);

        $login = $this->typePassword($rosa, $device, password: 'Otra-clave-2026');

        $login->assertNotSet('userUndertakingMultiFactorAuthentication', null);
        $this->assertGuest();
    }

    public function test_a_new_phone_makes_the_devices_remembered_with_the_old_one_ask_again(): void
    {
        $rosa = User::factory()->reception()->create();
        $device = $this->enterWithTheCode($rosa);
        $rosa->saveAppAuthenticationSecret('KRSXG5CTMVRXEZLU');

        $login = $this->typePassword($rosa, $device);

        $login->assertNotSet('userUndertakingMultiFactorAuthentication', null);
        $this->assertGuest();
    }

    /** @return array<string, array{callable(string): string}> */
    public static function tamperedCookies(): array
    {
        return [
            'vence un año después con la misma firma' => [fn (string $value): string => (strtok($value, '|') + 31_536_000).'|'.substr($value, strpos($value, '|') + 1)],
            'la firma de otro' => [fn (string $value): string => strtok($value, '|').'|'.hash_hmac('sha256', 'cualquier cosa', 'otra clave')],
            'sin firma' => [fn (string $value): string => (string) strtok($value, '|')],
            'solo texto' => [fn (): string => 'recuérdame'],
        ];
    }

    /** @param  callable(string): string  $tamper */
    #[DataProvider('tamperedCookies')]
    public function test_a_cookie_changed_by_hand_does_not_skip_the_code(callable $tamper): void
    {
        $rosa = User::factory()->reception()->create();
        $device = $this->enterWithTheCode($rosa);

        $login = $this->typePassword($rosa, new DeviceCookie($device->getName(), $tamper((string) $device->getValue())));

        $login->assertNotSet('userUndertakingMultiFactorAuthentication', null);
        $this->assertGuest();
    }

    public function test_the_code_screen_offers_not_to_ask_again_today_already_ticked(): void
    {
        $rosa = User::factory()->reception()->create();

        $login = $this->typePassword($rosa);

        $login->assertSee(['Hoy no volver a pedirme el código en este equipo', 'Mañana se te pide de nuevo.'])
            ->assertSet('data.multiFactor.app.'.DoubleCheck::REMEMBER_FIELD, true);
    }

    public function test_unticking_the_box_keeps_the_device_from_being_remembered(): void
    {
        $rosa = User::factory()->reception()->create();

        $this->typePassword($rosa)
            ->set('data.multiFactor.app.'.DoubleCheck::REMEMBER_FIELD, false)
            ->set('data.multiFactor.app.code', app(AppAuthentication::class)->getCurrentCode($rosa))
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($rosa);
        $this->assertFalse(Cookie::hasQueued(DoubleCheck::cookieName($rosa)));
    }

    public function test_a_wrong_code_does_not_remember_the_device(): void
    {
        $rosa = User::factory()->reception()->create();

        $this->typePassword($rosa)
            ->set('data.multiFactor.app.code', '000000')
            ->call('authenticate')
            ->assertHasErrors();

        $this->assertGuest();
        $this->assertFalse(Cookie::hasQueued(DoubleCheck::cookieName($rosa)));
    }

    public function test_entering_with_a_recovery_code_also_remembers_the_device(): void
    {
        $rosa = User::factory()->reception()->create();
        $provider = app(AppAuthentication::class);
        $provider->saveRecoveryCodes($rosa, $codes = $provider->generateRecoveryCodes());

        $this->typePassword($rosa)
            ->set('data.multiFactor.app.useRecoveryCode', true)
            ->set('data.multiFactor.app.recoveryCode', $codes[0])
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($rosa);
        $this->assertTrue(Cookie::hasQueued(DoubleCheck::cookieName($rosa)));
    }

    public function test_entering_without_being_asked_for_the_code_does_not_remember_the_device(): void
    {
        $rosa = User::factory()->reception()->withoutMfa()->create();

        $this->typePassword($rosa)->assertHasNoErrors();

        $this->assertAuthenticatedAs($rosa);
        $this->assertFalse(Cookie::hasQueued(DoubleCheck::cookieName($rosa)));
    }

    public function test_turning_the_double_check_on_remembers_that_device_for_the_day(): void
    {
        $rosa = User::factory()->reception()->withoutMfa()->create();
        $this->actingAs($rosa);
        $page = Livewire::test(SetUpTwoFactor::class);
        $page->fillForm([
            'code' => app(AppAuthentication::class)->getCurrentCode($rosa, decrypt($page->get('pending'))['secret']),
            'password' => 'password',
        ])->call('activate')->assertHasNoFormErrors();
        $device = Cookie::queued(DoubleCheck::cookieName($rosa));
        Filament::auth()->logout();

        $login = $this->typePassword($rosa->refresh(), $device);

        $login->assertHasNoErrors()->assertSet('userUndertakingMultiFactorAuthentication', null);
        $this->assertAuthenticatedAs($rosa);
    }

    /** La primera vez del día: contraseña y código del celular. Devuelve la cookie que le queda a ese equipo, ya afuera. */
    private function enterWithTheCode(User $user): DeviceCookie
    {
        $this->typePassword($user)
            ->set('data.multiFactor.app.code', app(AppAuthentication::class)->getCurrentCode($user))
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $device = Cookie::queued(DoubleCheck::cookieName($user));
        $this->assertInstanceOf(DeviceCookie::class, $device);

        Filament::auth()->logout();

        return $device;
    }

    /**
     * Escribe su correo y su contraseña en un equipo que trae la cookie del día, o en uno que no la trae. Livewire
     * guarda las cookies de prueba de un intento al siguiente: por eso cada intento dice la suya, aunque sea vacía.
     *
     * @return Testable<Login>
     */
    private function typePassword(User $user, ?DeviceCookie $device = null, string $password = 'password'): Testable
    {
        return Livewire::withCookie(DoubleCheck::cookieName($user), (string) $device?->getValue())
            ->test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => $password])
            ->call('authenticate');
    }
}
