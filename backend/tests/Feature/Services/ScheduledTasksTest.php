<?php

namespace Tests\Feature\Services;

use App\Filament\Widgets\SystemHealth;
use App\Models\User;
use App\Services\ScheduledTasks;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sin el programador encendido no sale nada automático (recordatorios, cuotas, copias). Cada pasada deja su latido y
 * Hoy le avisa a quien administra cuando pasa una hora sin él.
 */
class ScheduledTasksTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:00', 'America/Lima'));
        // Con la copia de hoy hecha, el único aviso posible es el de las tareas.
        Storage::disk('local')->put('respaldos/respaldo_2026-11-23_030000.sql.gz', 'copia');
    }

    public function test_each_pass_of_the_scheduler_leaves_the_time_it_ran(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();
        $heartbeat = collect(app(Schedule::class)->events())->sole(fn (Event $event) => $event->description === 'latido');

        $this->assertSame('* * * * *', $heartbeat->expression);

        $heartbeat->run($this->app);

        $this->assertSame(now()->getTimestamp(), Cache::get('scheduler.last_run_at'));
        $this->assertTrue(app(ScheduledTasks::class)->areRunning());
    }

    public function test_home_warns_the_admin_when_the_tasks_stop_for_more_than_an_hour(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        ScheduledTasks::beat();

        $this->travel(59)->minutes();
        $this->assertFalse(SystemHealth::canView());

        $this->travel(2)->minutes();
        $this->assertTrue(SystemHealth::canView());
        Livewire::test(SystemHealth::class)
            ->assertSee([
                'Las tareas automáticas no están corriendo',
                'Sin ellas no salen los recordatorios ni las copias de seguridad. Corrieron por última vez hace 1 hora.',
                'Pide a quien instaló el sistema que encienda la tarea que corre cada minuto en el servidor',
            ]);

        // Apenas vuelven a correr, el aviso se va.
        ScheduledTasks::beat();
        $this->assertFalse(SystemHealth::canView());

        // Recepción no ve el estado del sistema.
        $this->travel(2)->hours();
        $this->actingAs(User::factory()->reception()->create());
        $this->assertFalse(SystemHealth::canView());
    }

    public function test_a_new_installation_has_an_hour_to_turn_them_on_before_the_warning(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // Nunca corrieron: recién instalado, todavía no se avisa.
        $this->assertFalse(SystemHealth::canView());

        $this->travel(30)->minutes();
        $this->assertFalse(SystemHealth::canView());

        $this->travel(31)->minutes();
        Livewire::test(SystemHealth::class)
            ->assertSee('Las tareas automáticas no están corriendo')
            ->assertDontSee('Corrieron por última vez');
    }

    public function test_on_the_windows_laptop_it_says_how_to_turn_them_on_there(): void
    {
        $laptop = new ScheduledTasks('Windows');
        $server = new ScheduledTasks('Linux');

        $this->assertSame('de esta computadora', $laptop->ofMachine());
        $this->assertStringContainsString('infra › windows, haz doble clic en «encender-tareas-automaticas.bat»', $laptop->howToTurnOn());
        $this->assertSame('del servidor', $server->ofMachine());
        $this->assertStringNotContainsString('.bat', $server->howToTurnOn());

        // Los archivos que nombra el aviso existen y hacen lo que dice.
        foreach (['iniciar-consultorio.bat', 'encender-tareas-automaticas.bat', 'quitar-tareas-automaticas.bat'] as $file) {
            $this->assertFileExists(base_path('../infra/windows/'.$file));
        }
        $this->assertStringContainsString('schedule:run', file_get_contents(base_path('../infra/windows/encender-tareas-automaticas.bat')));
        $this->assertStringContainsString('schedule:work', file_get_contents(base_path('../infra/windows/iniciar-consultorio.bat')));
    }
}
