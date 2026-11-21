<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Agenda;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** Lo que la agenda le pide a la base de datos en cada recarga (se recarga sola cada 30 segundos, en cada pestaña). */
class AgendaServerLoadTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Treatment $cleaning;

    private Doctor $doctor;

    private Doctor $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'duration_minutes' => 30]);
        $this->doctor = $this->doctorOffering($this->cleaning);
        $this->other = $this->doctorOffering($this->cleaning);
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_the_week_asks_the_same_however_many_days_have_appointments(): void
    {
        $this->visitOn('2026-11-02', $this->doctor);
        $this->visitOn('2026-11-02', $this->other);
        $oneDay = $this->queriesOnRefresh(Livewire::test(Agenda::class, ['mode' => 'week', 'doctor' => $this->doctor->id]));
        $clinicOneDay = $this->queriesOnRefresh(Livewire::test(Agenda::class, ['mode' => 'week', 'doctor' => 0]));

        foreach (['2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-07'] as $day) {
            $this->visitOn($day, $this->doctor);
            $this->visitOn($day, $this->other);
        }

        $week = Livewire::test(Agenda::class, ['mode' => 'week', 'doctor' => $this->doctor->id])->assertSee('Prueba');

        // Antes eran 72 con un doctor y 146 con todo el consultorio: lo mismo pedido por cada día.
        $this->assertSame($oneDay, $this->queriesOnRefresh($week));
        $this->assertSame($clinicOneDay, $this->queriesOnRefresh(Livewire::test(Agenda::class, ['mode' => 'week', 'doctor' => 0])));
        $this->assertLessThan(25, $clinicOneDay);
    }

    public function test_the_side_panel_is_only_worked_out_while_it_is_seen(): void
    {
        $this->visitOn('2026-11-02', $this->doctor);
        $agenda = Livewire::test(Agenda::class)->assertSee(['Noviembre 2026', 'Buscar hueco']);
        $shown = $this->queriesOnRefresh($agenda);

        // Escondido, o en el celular sin abrir, la pantalla lo avisa y el panel no se arma.
        $agenda->set('railShown', false)->assertDontSee('Noviembre 2026')->assertSeeHtml('aria-busy="true"');
        $this->assertLessThanOrEqual($shown - 5, $this->queriesOnRefresh($agenda));

        // Al mostrarlo vuelve con lo suyo.
        $agenda->set('railShown', true)->assertSee('Noviembre 2026');
    }

    private function queriesOnRefresh(Testable $agenda): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $agenda->call('$refresh');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function visitOn(string $day, Doctor $doctor): Appointment
    {
        return Appointment::factory()
            ->for($doctor)
            ->for($this->cleaning)
            ->for(Patient::factory()->state(['last_name' => 'Prueba']))
            ->confirmed()
            ->at($day.' 10:00')
            ->create();
    }
}
