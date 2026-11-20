<?php

namespace Tests\Feature\Services;

use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\User;
use App\Services\StaffAlerts;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Las alertas de la campana del panel. */
class StaffAlertsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_an_alert_is_in_the_bell_right_away_without_waiting_for_the_queue(): void
    {
        // Como en el hosting: la cola es la base de datos y la procesa una tarea programada.
        config(['queue.default' => 'database']);
        $reception = User::factory()->reception()->create();
        $admin = User::factory()->admin()->create();
        $doctor = User::factory()->doctor()->create();
        $inactive = User::factory()->reception()->create(['is_active' => false]);

        app(StaffAlerts::class)->toReception('Nueva cita web', 'Rosa Huamán · Limpieza', link: ['label' => 'Enviar la confirmación', 'url' => WhatsappMessageResource::getUrl()]);

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, $doctor->notifications()->count() + $inactive->notifications()->count());
        $this->assertSame(1, $admin->notifications()->count());

        $alert = $reception->notifications()->sole()->data;
        $this->assertSame('Nueva cita web', $alert['title']);
        $this->assertSame('Enviar la confirmación', $alert['actions'][0]['label']);
        $this->assertSame(WhatsappMessageResource::getUrl(), $alert['actions'][0]['url']);
        // Pide hacer algo: se distingue en la campana por su botón y el ícono en el color de la marca.
        $this->assertSame('primary', $alert['iconColor']);
        $this->assertTrue(Action::fromArray($alert['actions'][0])->isButton());
    }

    public function test_an_alert_that_only_informs_is_gray_and_has_no_button(): void
    {
        $reception = User::factory()->reception()->create();

        app(StaffAlerts::class)->toReception('Dr. Luis Rojas agendó una cita', 'Rosa Huamán · Limpieza');

        $alert = $reception->notifications()->sole()->data;
        $this->assertSame('gray', $alert['iconColor']);
        $this->assertSame('heroicon-o-information-circle', $alert['icon']);
        $this->assertSame([], $alert['actions']);
    }

    public function test_an_alert_to_one_person_is_also_there_right_away(): void
    {
        config(['queue.default' => 'database']);
        $doctor = User::factory()->doctor()->create();

        app(StaffAlerts::class)->toUser($doctor, 'Solicitud de cambio de horario', 'Rosa Huamán pidió mover su cita.');

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame('Solicitud de cambio de horario', $doctor->notifications()->sole()->data['title']);
    }
}
