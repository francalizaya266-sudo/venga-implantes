<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Auth\ChoosePassword;
use App\Filament\Pages\Auth\EditProfile;
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
        $this->get($this->setUpUrl())->assertOk();
    }

    public function test_someone_with_the_second_factor_reaches_the_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(Filament::getPanel('admin')->getUrl())->assertOk();
    }

    public function test_a_new_user_first_turns_on_the_second_factor_and_then_chooses_a_password(): void
    {
        $user = User::factory()->reception()->withoutMfa()->create(['must_change_password' => true]);
        $this->actingAs($user);

        $this->get(ChoosePassword::getUrl())->assertRedirect($this->setUpUrl());
        $this->get($this->setUpUrl())->assertOk();

        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        $this->get(Filament::getPanel('admin')->getUrl())->assertRedirect(ChoosePassword::getUrl());
        $this->get(ChoosePassword::getUrl())->assertOk();
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
