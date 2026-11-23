<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Filament\Pages\Agenda;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** Atajos del día: qué tan lleno está bajo la fecha, "Buscar hueco" que recuerda y el siguiente paso en las citas largas. */
class AgendaDayShortcutsTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Treatment $cleaning;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'duration_minutes' => 30]);
        $this->doctor = $this->doctorOffering($this->cleaning);
    }

    public function test_under_the_date_it_says_how_full_the_day_is(): void
    {
        // Ocho horas de atención: dos citas de una hora, cada una con sus 10 minutos para preparar el sillón, son el 29 %.
        $this->visit('Rosa', '2026-11-02 09:00', 60);
        $this->visit('Bruno', '2026-11-02 15:00', 60);
        $this->actingAs(User::factory()->reception()->create());

        $agenda = Livewire::test(Agenda::class);
        $this->assertSame('29 % ocupado', $agenda->instance()->getSubheading());

        $agenda->set('date', '2026-11-08');
        $this->assertSame('No se atiende', $agenda->instance()->getSubheading());

        // En la semana cada día dice lo suyo.
        $agenda->set('mode', 'week');
        $this->assertNull($agenda->instance()->getSubheading());
    }

    public function test_the_admin_also_sees_what_each_attended_hour_brought_in(): void
    {
        $rosa = $this->visit('Rosa', '2026-11-02 09:00', 60, AppointmentStatus::Completed);
        $bruno = $this->visit('Bruno', '2026-11-02 10:00', 30, AppointmentStatus::Completed);
        Charge::factory()->for($rosa->patient)->create(['appointment_id' => $rosa->id, 'doctor_id' => $this->doctor->id, 'amount' => 250, 'discount' => 10]);
        Charge::factory()->for($bruno->patient)->create(['appointment_id' => $bruno->id, 'doctor_id' => $this->doctor->id, 'amount' => 120]);
        $this->travelTo('2026-11-02 12:00');

        // S/ 360 en una hora y media.
        $this->actingAs(User::factory()->admin()->create());
        $this->assertSame('23 % ocupado · S/ 240 por hora atendida', Livewire::test(Agenda::class)->instance()->getSubheading());

        // Recepción no lo ve, y sin nadie atendido no se dice.
        $this->actingAs(User::factory()->reception()->create());
        $this->assertSame('23 % ocupado', Livewire::test(Agenda::class)->instance()->getSubheading());
        $this->actingAs(User::factory()->admin()->create());
        $this->assertSame('No se atiende', Livewire::test(Agenda::class, ['date' => '2026-11-08'])->instance()->getSubheading());
    }

    public function test_find_a_slot_remembers_the_last_treatment_and_shows_its_times_on_arrival(): void
    {
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(Agenda::class)
            ->assertSee('Elige un tratamiento para ver cuándo entra.')
            ->call('findFor', $this->cleaning->id);

        // Al volver a la agenda ya están sus horas, sin elegirlo otra vez.
        Livewire::test(Agenda::class)
            ->assertSet('findTreatment', $this->cleaning->id)
            ->assertSeeHtmlInOrder(['Buscar hueco', 'Hoy', '09:00'])
            ->assertDontSee('Elige un tratamiento para ver cuándo entra.');

        // Otro toque en el mismo lo quita, y eso también se recuerda.
        Livewire::test(Agenda::class)->call('findFor', $this->cleaning->id);
        Livewire::test(Agenda::class)->assertSet('findTreatment', null);
    }

    public function test_a_long_visit_brings_its_next_step_on_the_day_grid(): void
    {
        $this->travelTo('2026-11-02 09:50');
        $long = $this->visit('Rosa', '2026-11-02 10:00', 60);
        $this->visit('Bruno', '2026-11-02 11:30', 30);
        $this->actingAs(User::factory()->reception()->create());

        $html = Livewire::test(Agenda::class)->html();

        // Solo la de una hora: en la de 30 minutos no hay lugar.
        $this->assertSame(1, substr_count($html, 'class="agenda-appt-step"'));
        $this->assertMatchesRegularExpression('/agenda-appt-step.*?Llegó/s', $html);
        // Su tarjeta lo sabe: en una columna angosta, sus líneas le dejan lugar al botón para que no tape la hora.
        $this->assertSame(1, substr_count($html, 'has-step'));
        $this->assertMatchesRegularExpression('/class="agenda-appt [^"]*has-step[^"]*"[^>]*>\s*<b>Rosa/s', $html);

        Livewire::test(Agenda::class)
            ->callAction('listArrived', arguments: ['id' => $long->id])
            ->assertHasNoActionErrors();
        $this->assertSame(AppointmentStatus::CheckedIn, $long->fresh()->status);

        // En la semana las columnas son angostas: no va.
        Livewire::test(Agenda::class, ['mode' => 'week', 'doctor' => $this->doctor->id])->assertDontSeeHtml('class="agenda-appt-step"');
    }

    private function visit(string $firstName, string $startsAt, int $minutes, AppointmentStatus $status = AppointmentStatus::Confirmed): Appointment
    {
        return Appointment::factory()
            ->for($this->doctor)
            ->for($this->cleaning)
            ->for(Patient::factory()->state(['first_name' => $firstName, 'last_name' => 'Prueba']))
            ->at($startsAt, $minutes)
            ->create(['status' => $status]);
    }
}
