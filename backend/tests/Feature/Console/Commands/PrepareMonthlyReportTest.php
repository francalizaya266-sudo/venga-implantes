<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\PaymentMethod;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Reports\MonthlyReport;
use App\Services\StaffAlerts;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/** A9: el día 1, el reporte del mes anterior queda listo y quien administra recibe el aviso en la campana. */
class PrepareMonthlyReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-01 05:50', 'America/Lima'));
        $this->admin = User::factory()->admin()->create(['name' => 'Dueña']);
    }

    /** Pagos de octubre: S/ 120 en efectivo y S/ 450 por Yape. */
    private function octoberPayments(): void
    {
        $doctor = Doctor::factory()->create();
        $treatment = Treatment::factory()->create();
        $patient = Patient::factory()->create();

        foreach ([[120, PaymentMethod::Cash, '2026-10-02 10:00'], [450, PaymentMethod::Yape, '2026-10-30 16:00']] as [$amount, $method, $at]) {
            Payment::factory()->for($patient)->create(['doctor_id' => $doctor->id, 'treatment_id' => $treatment->id, 'amount' => $amount, 'method' => $method, 'paid_at' => $at]);
        }
    }

    private function scheduled(): Event
    {
        $this->artisan('schedule:list')->assertSuccessful();

        return collect(app(Schedule::class)->events())->sole(fn (Event $event) => str_contains((string) $event->command, 'reportes:preparar-mes'));
    }

    public function test_on_the_first_whoever_administers_gets_last_months_report_in_the_bell(): void
    {
        $this->octoberPayments();
        $reception = User::factory()->reception()->create();
        $doctor = Doctor::factory()->create()->user;
        $former = User::factory()->admin()->create(['is_active' => false]);
        $task = $this->scheduled();

        $this->assertFalse($task->filtersPass($this->app), 'El día 1 todavía no son las 6:00.');

        $this->travelTo(CarbonImmutable::parse('2026-11-01 06:00', 'America/Lima'));
        $this->assertTrue($task->filtersPass($this->app));

        $this->artisan('reportes:preparar-mes')
            ->expectsOutput('Reporte de octubre de 2026: se avisó a 1 persona.')
            ->assertSuccessful();

        $notice = $this->admin->unreadNotifications()->sole()->data;
        $this->assertSame('El reporte de octubre está listo', $notice['title']);
        $this->assertSame('Octubre de 2026: se cobró S/ 570.00 en 2 pagos.', $notice['body']);
        $this->assertSame(['Ver el resumen', 'Imprimir o PDF', 'Descargar Excel'], array_column($notice['actions'], 'label'));

        $month = 'period=custom&from=2026-10-01&to=2026-10-31';
        [$summary, $print, $excel] = array_column($notice['actions'], 'url');
        $this->assertStringContainsString('/panel/resumen?'.http_build_query(['filters' => ['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31']]), $summary);
        $this->assertStringContainsString('/panel/reportes/ingresos/imprimir?'.$month, $print);
        $this->assertStringContainsString('/panel/reportes/ingresos/excel?'.$month, $excel);

        // Solo a quien administra (y sigue en el consultorio).
        foreach ([$reception, $doctor, $former] as $other) {
            $this->assertSame(0, $other->notifications()->count());
        }

        // Una sola vez: la siguiente pasada no repite el aviso.
        $this->travelTo(CarbonImmutable::parse('2026-11-01 06:15', 'America/Lima'));
        $this->assertFalse($task->filtersPass($this->app));
        $this->artisan('reportes:preparar-mes')->assertSuccessful();
        $this->assertSame(1, $this->admin->notifications()->count());

        // Lo anota el sistema: no es un cambio de ajustes hecho por alguien.
        $this->assertSame('2026-10', Setting::get(MonthlyReport::SETTING));
        $this->assertFalse(AuditLog::query()->where('auditable_type', Setting::class)->exists());
    }

    public function test_if_the_laptop_was_off_on_the_first_the_report_arrives_as_soon_as_it_is_turned_on(): void
    {
        $this->octoberPayments();
        $task = $this->scheduled();

        $this->travelTo(CarbonImmutable::parse('2026-11-03 09:10', 'America/Lima'));
        $this->assertTrue($task->filtersPass($this->app));

        $this->artisan('reportes:preparar-mes')->assertSuccessful();

        $this->assertSame('El reporte de octubre está listo', $this->admin->notifications()->sole()->data['title']);
        $this->assertFalse($task->filtersPass($this->app));
    }

    public function test_the_links_open_last_months_report(): void
    {
        $this->octoberPayments();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00', 'America/Lima'));
        app(MonthlyReport::class)->announce();
        [$summary, $print, $excel] = array_column($this->admin->notifications()->sole()->data['actions'], 'url');

        $this->actingAs($this->admin);

        $this->get($summary)->assertOk()->assertSee('Reportes');
        $this->get($print)->assertOk()->assertSee(['Octubre de 2026', 'S/ 570.00']);
        $this->get($excel)->assertOk()->assertDownload('ingresos_2026-10-01_2026-10-31.xlsx');
    }

    public function test_december_is_announced_in_january(): void
    {
        $doctor = Doctor::factory()->create();
        Payment::factory()->for(Patient::factory())->create(['doctor_id' => $doctor->id, 'amount' => 80, 'paid_at' => '2026-12-15 11:00']);

        $this->travelTo(CarbonImmutable::parse('2027-01-01 08:00', 'America/Lima'));
        $this->artisan('reportes:preparar-mes')->assertSuccessful();

        $notice = $this->admin->notifications()->sole()->data;
        $this->assertSame('El reporte de diciembre está listo', $notice['title']);
        $this->assertSame('Diciembre de 2026: se cobró S/ 80.00 en 1 pago.', $notice['body']);
    }

    public function test_a_month_without_any_movement_is_not_announced(): void
    {
        // El sistema se instaló en noviembre: octubre no tiene nada que contar.
        $task = $this->scheduled();
        $this->travelTo(CarbonImmutable::parse('2026-11-01 07:00', 'America/Lima'));

        $this->artisan('reportes:preparar-mes')
            ->expectsOutput('Reporte de octubre de 2026: se avisó a 0 personas.')
            ->assertSuccessful();

        $this->assertSame(0, $this->admin->notifications()->count());
        $this->assertFalse($task->filtersPass($this->app));
    }

    public function test_the_report_waits_unread_in_the_bell_for_a_week(): void
    {
        $this->octoberPayments();
        $this->travelTo(CarbonImmutable::parse('2026-11-01 07:00', 'America/Lima'));
        app(MonthlyReport::class)->announce();
        app(StaffAlerts::class)->toReception('Nueva cita web');

        // El 1 cayó domingo: el lunes lo demás de ayer queda leído, el reporte sigue esperando.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00', 'America/Lima'));
        $this->artisan('campana:limpiar')->assertSuccessful();
        $this->assertSame(['El reporte de octubre está listo'], $this->admin->unreadNotifications()->get()->pluck('data.title')->all());

        // Una semana después también queda leído.
        $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00', 'America/Lima'));
        $this->artisan('campana:limpiar')->assertSuccessful();
        $this->assertSame(0, $this->admin->unreadNotifications()->count());
    }
}
