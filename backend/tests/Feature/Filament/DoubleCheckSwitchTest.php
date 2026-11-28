<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Auth\ChoosePassword;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\SetUpTwoFactor;
use App\Filament\Pages\Settings;
use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Support\DoubleCheck;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El administrador puede dejar de pedir la doble verificación: a una persona (Usuarios) o a todo el equipo
 * (Ajustes › Seguridad). Lo confirma con su contraseña y queda en el historial de cambios con su nombre.
 */
class DoubleCheckSwitchTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_admin_stops_asking_one_person_and_it_stays_in_the_history_with_the_admins_name(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Carla Vega']);
        $rosa = User::factory()->reception()->create(['name' => 'Rosa Quispe']);
        $luis = User::factory()->reception()->create();
        $this->actingAs($admin);

        $page = Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('stopDoubleCheck')->table($rosa), data: ['password' => 'password']);

        $page->assertHasNoActionErrors()->assertNotified('A Rosa Quispe ya no se le pide la doble verificación');

        $this->assertTrue($rosa->fresh()->skips_double_check);
        $this->assertFalse($luis->fresh()->skips_double_check);
        // Su celular sigue configurado: si se le vuelve a pedir, no tiene que activarla de nuevo.
        $this->assertSame('JBSWY3DPEHPK3PXP', $rosa->fresh()->getAppAuthenticationSecret());

        $change = AuditLog::query()->where('auditable_type', User::class)->where('auditable_id', $rosa->id)->where('event', 'updated')->sole();
        $this->assertSame($admin->id, $change->user_id);
        $this->assertSame([['skips_double_check' => false], ['skips_double_check' => true]], [$change->old_values, $change->new_values]);

        Livewire::test(ManageAuditLogs::class)
            ->assertTableColumnStateSet('user.name', 'Carla Vega', $change)
            ->assertTableColumnStateSet('section', 'Usuarios', $change)
            ->assertTableColumnStateSet('subject', 'Rosa Quispe', $change)
            ->assertTableColumnStateSet('summary', 'Entra sin doble verificación: No → Sí', $change);
    }

    public function test_stopping_it_for_one_person_needs_the_admins_own_password(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $rosa = User::factory()->reception()->create();

        $page = Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('stopDoubleCheck')->table($rosa), data: ['password' => 'no-es-la-mia']);

        $page->assertHasActionErrors(['password']);
        $this->assertFalse($rosa->fresh()->skips_double_check);
        $this->assertFalse(AuditLog::query()->where('auditable_id', $rosa->id)->where('event', 'updated')->exists());
    }

    public function test_the_admin_can_stop_it_for_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $page = Livewire::test(ManageUsers::class)
            // Lo demás del menú sigue sin ofrecerse para uno mismo: ni quitarse el celular ni borrarse.
            ->assertActionHidden(TestAction::make('resetMfa')->table($admin))
            ->assertActionHidden(TestAction::make('delete')->table($admin))
            ->callAction(TestAction::make('stopDoubleCheck')->table($admin), data: ['password' => 'password']);

        $page->assertHasNoActionErrors()->assertNotified('Ya no se te pide la doble verificación');
        $this->assertTrue($admin->fresh()->skips_double_check);
    }

    public function test_someone_who_is_not_asked_enters_with_only_the_password(): void
    {
        $rosa = User::factory()->reception()->skippingDoubleCheck()->create();

        $login = $this->typePassword($rosa);

        $login->assertHasNoErrors()->assertSet('userUndertakingMultiFactorAuthentication', null);
        $this->assertAuthenticatedAs($rosa);
    }

    public function test_someone_who_is_not_asked_and_never_turned_it_on_is_not_sent_to_turn_it_on(): void
    {
        $this->actingAs(User::factory()->reception()->skippingDoubleCheck()->withoutMfa()->create());
        $panel = Filament::getPanel('admin')->getUrl();

        $this->get($panel)->assertOk();
        // Y la pantalla para activarla, si la abre a mano, la devuelve al panel.
        Livewire::test(SetUpTwoFactor::class)->assertRedirect($panel);
    }

    public function test_asking_one_person_again_stays_in_the_history_and_brings_the_code_back(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $rosa = User::factory()->reception()->skippingDoubleCheck()->create(['name' => 'Rosa Quispe']);

        $page = Livewire::test(ManageUsers::class)
            ->assertActionHidden(TestAction::make('stopDoubleCheck')->table($rosa))
            ->callAction(TestAction::make('resumeDoubleCheck')->table($rosa));

        $page->assertHasNoActionErrors()->assertNotified('A Rosa Quispe se le vuelve a pedir la doble verificación');
        $this->assertFalse($rosa->fresh()->skips_double_check);

        $change = AuditLog::query()->where('auditable_type', User::class)->where('auditable_id', $rosa->id)->where('event', 'updated')->sole();
        Livewire::test(ManageAuditLogs::class)->assertTableColumnStateSet('summary', 'Entra sin doble verificación: Sí → No', $change);

        Filament::auth()->logout();
        $this->typePassword($rosa->fresh())->assertNotSet('userUndertakingMultiFactorAuthentication', null);
        $this->assertGuest();
    }

    public function test_the_users_list_shows_each_persons_double_check_at_a_glance(): void
    {
        $this->actingAs($admin = User::factory()->admin()->create());
        $notYet = User::factory()->reception()->withoutMfa()->create();
        $notAsked = User::factory()->reception()->skippingDoubleCheck()->create();

        $list = Livewire::test(ManageUsers::class);

        $list->assertTableColumnStateSet('double_check', 'Activada', $admin)
            ->assertTableColumnStateSet('double_check', 'Sin activar', $notYet)
            ->assertTableColumnStateSet('double_check', 'No se le pide', $notAsked);
    }

    /** @return array<string, array{string}> */
    public static function rolesThatDoNotManageThePanel(): array
    {
        return ['recepción' => ['reception'], 'doctor' => ['doctor']];
    }

    #[DataProvider('rolesThatDoNotManageThePanel')]
    public function test_only_the_admin_reaches_the_switches(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create());

        Livewire::test(ManageUsers::class)->assertForbidden();
        Livewire::test(Settings::class)->assertForbidden();
    }

    public function test_the_admin_turns_it_off_for_the_whole_team_and_it_stays_in_the_history(): void
    {
        $this->actingAs($admin = User::factory()->admin()->create(['name' => 'Carla Vega']));

        $page = Livewire::test(Settings::class)
            ->assertSee(['Encendida para todo el equipo.', 'Apagarla para todo el equipo'])
            ->callAction($this->forEveryone('stopDoubleCheckForEveryone'), data: ['password' => 'password']);

        $page->assertHasNoActionErrors()
            ->assertNotified('Doble verificación apagada para todo el equipo')
            ->assertSee(['Apagada para todo el equipo: se entra solo con la contraseña.', 'Encenderla para todo el equipo']);
        $this->assertFalse(DoubleCheck::isOnForEveryone());

        $change = AuditLog::query()->where('auditable_type', Setting::class)->sole();
        $this->assertSame($admin->id, $change->user_id);

        Livewire::test(ManageAuditLogs::class)
            ->assertTableColumnStateSet('user.name', 'Carla Vega', $change)
            ->assertTableColumnStateSet('section', 'Ajustes y página web', $change)
            ->assertTableColumnStateSet('subject', 'Doble verificación para todo el equipo', $change)
            ->assertTableColumnStateSet('summary', 'Sí → No', $change);
    }

    public function test_turning_it_off_for_the_whole_team_needs_the_admins_own_password(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $page = Livewire::test(Settings::class)
            ->callAction($this->forEveryone('stopDoubleCheckForEveryone'), data: ['password' => 'no-es-la-mia']);

        $page->assertHasActionErrors(['password']);
        $this->assertTrue(DoubleCheck::isOnForEveryone());
        $this->assertFalse(AuditLog::query()->where('auditable_type', Setting::class)->exists());
    }

    public function test_with_it_off_for_the_whole_team_nobody_is_asked_for_the_code(): void
    {
        Setting::set(DoubleCheck::REQUIRED_SETTING, false);
        $rosa = User::factory()->reception()->create();

        $login = $this->typePassword($rosa);

        $login->assertHasNoErrors()->assertSet('userUndertakingMultiFactorAuthentication', null);
        $this->assertAuthenticatedAs($rosa);
    }

    public function test_with_it_off_for_the_whole_team_nobody_is_sent_to_turn_it_on_and_the_list_says_so(): void
    {
        Setting::set(DoubleCheck::REQUIRED_SETTING, false);
        $this->actingAs($admin = User::factory()->admin()->withoutMfa()->create());

        $this->get(Filament::getPanel('admin')->getUrl())->assertOk();
        Livewire::test(ManageUsers::class)
            ->assertTableColumnStateSet('double_check', 'Apagada para todos', $admin)
            // Con todo apagado no hay a quién dejar de pedírsela.
            ->assertActionHidden(TestAction::make('stopDoubleCheck')->table($admin));
    }

    public function test_turning_it_back_on_asks_the_team_again_but_not_whoever_was_left_out_one_by_one(): void
    {
        Setting::set(DoubleCheck::REQUIRED_SETTING, false);
        $this->actingAs(User::factory()->admin()->create());
        $rosa = User::factory()->reception()->create();
        $luis = User::factory()->reception()->skippingDoubleCheck()->create();
        $new = User::factory()->reception()->withoutMfa()->create();

        $page = Livewire::test(Settings::class)->callAction($this->forEveryone('resumeDoubleCheckForEveryone'));

        $page->assertHasNoActionErrors()->assertNotified('Doble verificación encendida para todo el equipo');
        $this->assertTrue(DoubleCheck::isOnForEveryone());
        $change = AuditLog::query()->where('auditable_type', Setting::class)->where('user_id', '!=', null)->sole();
        Livewire::test(ManageAuditLogs::class)->assertTableColumnStateSet('summary', 'No → Sí', $change);

        Filament::auth()->logout();
        $this->typePassword($rosa)->assertNotSet('userUndertakingMultiFactorAuthentication', null);
        $this->assertGuest();

        $this->typePassword($luis)->assertHasNoErrors();
        $this->assertAuthenticatedAs($luis);

        // Quien nunca la activó vuelve a ver primero la pantalla para activarla.
        $this->actingAs($new)->get(Filament::getPanel('admin')->getUrl())
            ->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));
    }

    public function test_saving_ajustes_leaves_the_switch_as_it_was(): void
    {
        Setting::set(DoubleCheck::REQUIRED_SETTING, false);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Settings::class)->call('save')->assertHasNoFormErrors();

        $this->assertFalse(DoubleCheck::isOnForEveryone());
    }

    public function test_my_profile_says_it_is_off_instead_of_offering_to_set_it_up(): void
    {
        $this->actingAs(User::factory()->reception()->skippingDoubleCheck()->create());

        $profile = $this->get(Filament::getProfileUrl());

        $profile->assertOk()
            ->assertSee(['Doble verificación', 'Apagada: entras solo con tu contraseña, sin el código de tu celular.', 'La enciende quien administra el panel.'])
            ->assertDontSee(['Cambié de celular', 'Nuevos códigos de recuperación', 'Código en tu celular']);
    }

    public function test_my_profile_tells_the_admin_where_to_turn_it_back_on(): void
    {
        Setting::set(DoubleCheck::REQUIRED_SETTING, false);
        $this->actingAs(User::factory()->admin()->create());

        $profile = $this->get(Filament::getProfileUrl());

        $profile->assertOk()->assertSee('Se enciende en Configuración › Ajustes › Seguridad');
    }

    public function test_a_new_person_who_is_not_asked_goes_straight_in_after_choosing_a_password(): void
    {
        Setting::set(DoubleCheck::REQUIRED_SETTING, false);
        $this->actingAs(User::factory()->reception()->withoutMfa()->create(['must_change_password' => true]));

        $page = Livewire::test(ChoosePassword::class);

        $page->assertSee('Guardar y entrar')->assertDontSee(['Guardar y seguir', 'Después protegerás tu cuenta con tu celular.']);
    }

    /** Los dos botones de Ajustes › Seguridad. */
    private function forEveryone(string $action): TestAction
    {
        return TestAction::make($action)->schemaComponent('doubleCheckActions', 'form');
    }

    /**
     * Escribe su correo y su contraseña en un equipo que hoy no tiene su código.
     *
     * @return Testable<Login>
     */
    private function typePassword(User $user): Testable
    {
        return Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate');
    }
}
