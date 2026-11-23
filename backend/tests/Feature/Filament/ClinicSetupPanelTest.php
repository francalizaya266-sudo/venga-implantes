<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ClinicSetup;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Setting;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Backup\ExtraCopy;
use App\Services\ClinicSetupChecklist;
use App\Services\ScheduledTasks;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** La puesta a punto del consultorio: lo que falta para empezar a atender, con "x de y listos" y su enlace. */
class ClinicSetupPanelTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('web');
        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:00', 'America/Lima'));
    }

    public function test_the_admin_sees_what_is_missing_to_start_and_where_to_do_it(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $doctor = Doctor::factory()->create(['name' => 'Dra. Ana Torres']);
        Treatment::factory()->create(['name' => 'Resina', 'price_from' => null]);

        // Solo viene listo el celular del consultorio: es el del recetario.
        Livewire::test(ClinicSetup::class)
            ->assertSee([
                'Lo que falta para empezar a atender',
                '1 de 8 listos',
                'Falta el de Dra. Ana Torres. Sin horario no se le pueden dar citas',
                'Falta el precio de Resina. Sin precio, la caja no sabe cuánto cobrar.',
                'Un usuario para recepción',
                'El RUC y la razón social',
                'Las tareas automáticas encendidas',
                'La primera copia de seguridad',
                'La copia extra en otra carpeta',
            ])
            ->assertSee([DoctorResource::getUrl('edit', ['record' => $doctor]), Settings::getUrl()], false);

        $this->assertSame('7', ClinicSetup::getNavigationBadge());
        $this->get(Settings::getUrl())->assertOk()->assertSee(['Puesta a punto', ClinicSetup::getUrl()], false);
    }

    public function test_when_everything_is_ready_it_says_so_and_leaves_the_menu_number(): void
    {
        $folder = sys_get_temp_dir().'/copias-del-consultorio-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($folder);
        $this->actingAs(User::factory()->admin()->create());
        Doctor::factory()->withSchedule()->create();
        Treatment::factory()->create(['price_from' => 0]);
        User::factory()->reception()->create();
        Setting::set('clinic.ruc', '20601234567');
        Setting::set('clinic.legal_name', 'Vega Implant S.A.C.');
        ScheduledTasks::beat();
        Setting::set(ExtraCopy::FOLDER_SETTING, $folder);
        $this->artisan('respaldo:crear')->assertSuccessful();

        Livewire::test(ClinicSetup::class)
            ->assertSee(['Todo listo para atender', '8 de 8 listos'])
            ->assertDontSee('Lo que falta para empezar a atender');
        $this->assertNull(ClinicSetup::getNavigationBadge());

        // Si las tareas se apagan, vuelve a faltar.
        $this->travel(2)->hours();
        $this->assertSame('1', ClinicSetup::getNavigationBadge());
        Livewire::test(ClinicSetup::class)->assertSee(['7 de 8 listos', 'Sin ellas no salen los recordatorios ni las copias de seguridad.']);

        File::deleteDirectory($folder);
    }

    public function test_without_anyone_at_reception_the_admin_marks_it_and_it_counts_as_ready(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(ClinicSetup::class)
            ->assertSee('Si nadie trabaja en recepción, márcalo.')
            ->callAction('skipReception')
            ->assertNotified('Listo: no hace falta un usuario de recepción')
            ->assertDontSee('Si nadie trabaja en recepción, márcalo.');

        $this->assertTrue(collect(app(ClinicSetupChecklist::class)->items())->firstWhere('key', 'reception')['done']);
        $this->assertTrue(AuditLog::query()->where('auditable_type', Setting::class)->where('user_id', $admin->id)->exists());
    }

    public function test_only_the_admin_opens_it(): void
    {
        foreach ([User::factory()->reception()->create(), Doctor::factory()->create()->user] as $user) {
            $this->actingAs($user)->get(ClinicSetup::getUrl())->assertForbidden();
        }

        $this->actingAs(User::factory()->admin()->create())->get(ClinicSetup::getUrl())->assertOk()->assertSee('Puesta a punto del consultorio');
    }
}
