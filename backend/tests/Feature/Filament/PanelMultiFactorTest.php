<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Auth\ChoosePassword;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\SetUpTwoFactor;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** El panel se abre desde internet: además de la contraseña pide el código de la app autenticadora. */
class PanelMultiFactorTest extends TestCase
{
    use RefreshDatabase;

    private function setUpUrl(): string
    {
        return route('filament.admin.auth.multi-factor-authentication.set-up-required');
    }

    public function test_someone_without_the_second_factor_only_sees_the_screen_to_turn_it_on(): void
    {
        $this->actingAs(User::factory()->admin()->withoutMfa()->create());

        $this->get(Filament::getPanel('admin')->getUrl())->assertRedirect($this->setUpUrl());
        // Sin la barra de abajo del celular: sus botones solo lo traerían de vuelta aquí.
        $this->get($this->setUpUrl())->assertOk()->assertDontSee('Accesos rápidos');
    }

    public function test_someone_with_the_second_factor_reaches_the_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(Filament::getPanel('admin')->getUrl())->assertOk();
    }

    public function test_a_new_user_first_chooses_a_password_and_then_turns_on_the_double_check(): void
    {
        $user = User::factory()->reception()->withoutMfa()->create(['must_change_password' => true]);
        $this->actingAs($user);

        $this->get($this->setUpUrl())->assertRedirect(ChoosePassword::getUrl());
        $this->get(ChoosePassword::getUrl())->assertOk();

        $user->forceFill(['must_change_password' => false])->save();

        $this->get(Filament::getPanel('admin')->getUrl())->assertRedirect($this->setUpUrl());
        $this->get($this->setUpUrl())->assertOk();
    }

    public function test_the_first_entry_is_password_then_three_steps_then_recovery_codes_then_in(): void
    {
        $rosa = User::factory()->reception()->withoutMfa()->create(['must_change_password' => true]);
        $this->actingAs($rosa);

        // 1. Elige su contraseña.
        Livewire::test(ChoosePassword::class)
            ->fillForm(['password' => 'Solo-mia-2026', 'passwordConfirmation' => 'Solo-mia-2026'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Contraseña guardada')
            ->assertRedirect(Filament::getPanel('admin')->getUrl());

        // 2. La doble verificación en tres pasos numerados, sin volver a pedirle la contraseña que acaba de elegir.
        $this->get(Filament::getPanel('admin')->getUrl())->assertRedirect($this->setUpUrl());

        $page = Livewire::test(SetUpTwoFactor::class)
            ->assertSeeInOrder([
                'Protege tu cuenta con tu celular',
                'Además de tu contraseña, te pediremos un código de 6 números que aparece en tu celular.',
                'Instala Google Authenticator en tu celular.',
                'Escanea este código.',
                'Escribe los 6 números que te muestra.',
                'Activar',
            ])
            ->assertDontSee(['Tu contraseña', 'usted', 'Usted', '2FA', 'Utilice', 'Ingrese', 'Escanée'])
            ->assertFormFieldHidden('password');

        $secret = decrypt($page->get('pending'))['secret'];

        // Un código que no es no la activa.
        $page->fillForm(['code' => '000000'])
            ->call('activate')
            ->assertHasFormErrors(['code'])
            ->assertSee('Ese código no coincide.');
        $this->assertNull($rosa->fresh()->getAppAuthenticationSecret());

        // 3. Con los 6 números de su celular queda activada y ve sus códigos de recuperación para guardarlos.
        $page->fillForm(['code' => app(AppAuthentication::class)->getCurrentCode($rosa, $secret)])
            ->call('activate')
            ->assertHasNoFormErrors()
            ->assertSet('isActivated', true)
            ->assertSee(['Guarda tus códigos de recuperación', 'Descargar', 'Copiar', 'Ya los guardé, entrar'])
            ->assertSee(decrypt($page->get('pending'))['recoveryCodes']);

        $this->assertSame($secret, $rosa->fresh()->getAppAuthenticationSecret());
        $this->assertCount(8, $rosa->fresh()->getAppAuthenticationRecoveryCodes());

        // 4. Entra.
        $page->call('enter')->assertRedirect(Filament::getPanel('admin')->getUrl());
        $this->get(Filament::getPanel('admin')->getUrl())->assertOk();
    }

    public function test_whoever_did_not_just_type_the_password_confirms_it_to_turn_the_double_check_on(): void
    {
        // A quien se la quitó el administrador y vuelve con la sesión recordada: se le pide su contraseña.
        $rosa = User::factory()->reception()->withoutMfa()->create();
        $this->actingAs($rosa);

        $page = Livewire::test(SetUpTwoFactor::class)->assertFormFieldVisible('password');
        $code = app(AppAuthentication::class)->getCurrentCode($rosa, decrypt($page->get('pending'))['secret']);

        $page->fillForm(['code' => $code, 'password' => 'otra-cosa'])
            ->call('activate')
            ->assertHasFormErrors(['password']);
        $this->assertNull($rosa->fresh()->getAppAuthenticationSecret());

        $page->fillForm(['code' => $code, 'password' => 'password'])
            ->call('activate')
            ->assertHasNoFormErrors()
            ->assertSet('isActivated', true);
        $this->assertNotNull($rosa->fresh()->getAppAuthenticationSecret());
    }

    public function test_entering_with_the_password_counts_as_just_typed(): void
    {
        $rosa = User::factory()->reception()->withoutMfa()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $rosa->email, 'password' => 'password'])
            ->call('authenticate');

        $this->assertAuthenticatedAs($rosa);
        Livewire::test(SetUpTwoFactor::class)->assertFormFieldHidden('password');
    }

    public function test_leaving_the_double_check_screen_closes_the_session(): void
    {
        $this->actingAs(User::factory()->reception()->withoutMfa()->create());

        Livewire::test(SetUpTwoFactor::class)
            ->call('leave')
            ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

        $this->assertGuest();
    }

    public function test_the_password_alone_does_not_open_the_panel(): void
    {
        $user = User::factory()->reception()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertNotSet('userUndertakingMultiFactorAuthentication', null);

        $this->assertGuest();
    }

    public function test_the_password_and_the_app_code_open_the_panel(): void
    {
        $user = User::factory()->reception()->create();
        $code = app(AppAuthentication::class)->getCurrentCode($user);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->set('data.multiFactor.app.code', $code)
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_app_code_does_not_open_the_panel(): void
    {
        $user = User::factory()->reception()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->set('data.multiFactor.app.code', '000000')
            ->call('authenticate')
            ->assertHasErrors();

        $this->assertGuest();
    }

    public function test_entering_asks_for_the_code_in_plain_words(): void
    {
        $user = User::factory()->reception()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertSee(['Confirma que eres tú', 'Abre Google Authenticator en tu celular.', 'Escribe los 6 números de la aplicación', 'No tengo mi celular a la mano', 'Entrar'])
            ->assertDontSee(['Verifique', 'Ingrese', 'Use un código', 'Confirmar inicio de sesión', 'su identidad'])
            // Sin el celular a la mano entra con un código de recuperación.
            ->set('data.multiFactor.app.useRecoveryCode', true)
            ->assertSee('Escribe uno de los códigos de recuperación que guardaste');
    }

    public function test_my_profile_does_not_turn_the_double_check_off_but_moves_it_to_a_new_phone(): void
    {
        $user = User::factory()->reception()->create();
        $this->actingAs($user);

        // Es obligatoria: no hay "Apagar". Quedan los códigos de recuperación nuevos y el cambio de celular.
        $this->get(Filament::getProfileUrl())
            ->assertOk()
            ->assertSee(['Doble verificación', 'Código en tu celular', 'Activada', 'Nuevos códigos de recuperación', 'Cambié de celular'])
            ->assertDontSee(['Apagar', '2FA', 'Utilice', 'Deshabilitar', 'dos factores']);

        // Se confirma con el código del celular de antes y enseguida se escanea el código con el nuevo.
        Livewire::test(EditProfile::class)
            ->mountAction(TestAction::make('disableAppAuthentication')->schemaComponent('app', schema: 'content'))
            ->setActionData(['password' => 'password', 'code' => app(AppAuthentication::class)->getCurrentCode($user)])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Ahora escanea el código con tu celular nuevo')
            ->assertRedirect($this->setUpUrl());

        $this->assertNull($user->fresh()->getAppAuthenticationSecret());
        $this->get(Filament::getPanel('admin')->getUrl())->assertRedirect($this->setUpUrl());
    }

    public function test_the_secret_is_stored_encrypted_and_never_in_the_history(): void
    {
        $user = User::factory()->reception()->withoutMfa()->create();

        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        $this->assertNotSame('JBSWY3DPEHPK3PXP', $user->getRawOriginal('app_authentication_secret'));
        $this->assertArrayNotHasKey('app_authentication_secret', $user->toArray());
        $this->assertTrue(AuditLog::where('auditable_id', $user->id)->where('event', 'mfa_enabled')->exists());
        $this->assertFalse(AuditLog::where('auditable_id', $user->id)->get()->contains(
            fn (AuditLog $log) => str_contains(json_encode([$log->old_values, $log->new_values]), 'JBSWY3DPEHPK3PXP'),
        ));
    }

    public function test_the_admin_removes_the_second_factor_of_someone_who_lost_the_phone(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $reception = User::factory()->reception()->create();

        Livewire::test(ManageUsers::class)
            ->callTableAction('resetMfa', $reception)
            ->assertNotified('Doble verificación quitada');

        $this->assertNull($reception->fresh()->getAppAuthenticationSecret());
        $this->assertTrue(AuditLog::where('auditable_id', $reception->id)->where('event', 'mfa_disabled')->exists());
    }
}
