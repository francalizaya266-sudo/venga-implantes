<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Filament\Widgets\CashClosingCheck;
use App\Filament\Widgets\FollowUpsToday;
use App\Filament\Widgets\TodayStats;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Billing\CashClosing;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/**
 * "Citas sin cerrar" con una sola regla en Hoy y en el cierre del día, "En curso" aparte, y las citas abiertas que se
 * resuelven desde el mismo cierre con "Atendido" y "No asistió".
 */
class UnclosedAppointmentsTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Treatment $treatment;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->treatment = Treatment::factory()->create(['name' => 'Limpieza dental', 'price_from' => 120]);
        $this->doctor = $this->doctorOffering($this->treatment);
    }

    public function test_today_and_the_closing_count_the_same_unclosed_appointments_and_name_the_ones_in_course_apart(): void
    {
        // Lunes 12:13: lo que vio la auditoría, más una atención olvidada desde la mañana y una cita de otro día.
        $forgotten = $this->visit('Luis', '2026-11-02 08:00', AppointmentStatus::InProgress);
        $unmarked = $this->visit('María', '2026-11-02 09:00', AppointmentStatus::Confirmed);
        $done = $this->visit('Ana', '2026-11-02 10:00', AppointmentStatus::Completed);
        $inRoom = $this->visit('Rosa', '2026-11-02 11:30', AppointmentStatus::CheckedIn);
        $late = $this->visit('Bautista', '2026-11-02 12:00', AppointmentStatus::Confirmed);
        $earlier = $this->visit('Pedro', '2026-10-30 10:00', AppointmentStatus::Pending);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:13', 'America/Lima'));

        // Una sola regla: la consulta y la cita una por una dicen lo mismo.
        $unclosed = [$forgotten->id, $unmarked->id, $earlier->id];
        $this->assertEqualsCanonicalizing($unclosed, Appointment::query()->unclosed()->pluck('id')->all());
        foreach ([$forgotten, $unmarked, $done, $inRoom, $late, $earlier] as $appointment) {
            $this->assertSame(in_array($appointment->id, $unclosed, true), $appointment->fresh()->isUnclosed());
        }
        $this->assertSame([$inRoom->id], Appointment::query()->inCourse()->pluck('id')->all());

        // El cierre de hoy: las dos de hoy sin cerrar, la que sigue en sala aparte y la de otro día nombrada.
        $closing = new CashClosing(CarbonImmutable::today());
        $this->assertSame([$forgotten->id, $unmarked->id], $closing->unclosedAppointments()->pluck('id')->all());
        $this->assertSame([$inRoom->id], $closing->inCourseAppointments()->pluck('id')->all());
        $this->assertSame(1, $closing->earlierUnclosedCount());

        $this->actingAs(User::factory()->reception()->create());

        // Hoy: 3 sin cerrar (las dos de hoy y la de otro día) y, entre las de hoy, 2.
        Livewire::test(FollowUpsToday::class)
            ->assertSeeHtmlInOrder(['Citas sin cerrar', '<span class="dash-count is-warning">3</span>']);
        Livewire::test(TodayStats::class)
            ->assertSee('1 atendida · 1 en la clínica · 1 por llegar · 2 sin cerrar');

        Livewire::test(CashClosingCheck::class, ['pageFilters' => ['date' => '2026-11-02']])
            ->assertSeeInOrder(['Citas sin cerrar', '2', '08:00 · Luis Prueba', '09:00 · María Prueba', 'Además queda 1 cita de días anteriores sin cerrar.', 'En curso', '1', '11:30 · Rosa Prueba'])
            // La de las 12:00 todavía está en su hora: no está sin cerrar ni en curso.
            ->assertDontSee(['12:00 · Bautista Prueba', '10:00 · Ana Prueba']);
    }

    public function test_the_closing_marks_who_came_and_who_did_not_without_opening_each_appointment(): void
    {
        $pedro = $this->visit('Pedro', '2026-11-02 16:00', AppointmentStatus::Confirmed);
        $ana = $this->visit('Ana', '2026-11-02 17:00', AppointmentStatus::Pending);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Lima'));
        $this->actingAs(User::factory()->reception()->create());

        $closing = Livewire::test(CashClosingCheck::class, ['pageFilters' => ['date' => '2026-11-02']])
            ->assertSeeInOrder(['Citas sin cerrar', '16:00 · Pedro Prueba', 'Atendido', 'No asistió', '17:00 · Ana Prueba', 'Atendido', 'No asistió'])
            ->assertActionVisible(TestAction::make('complete')->arguments(['appointment' => $pedro->id]))
            ->assertActionVisible(TestAction::make('missed')->arguments(['appointment' => $ana->id]));

        // Pedro vino: queda atendido con lo programado, y como no pagó, se fue debiendo.
        $closing->mountAction(TestAction::make('complete')->arguments(['appointment' => $pedro->id]))
            ->assertMountedActionModalSee('¿El paciente vino el lunes 2 de noviembre?')
            ->callMountedAction()
            ->assertNotified('Cita atendida')
            ->assertSeeInOrder(['Se fueron debiendo', 'Pedro Prueba', 'Cobrar'])
            ->assertSee('S/ 120.00')
            // Ana no vino: cuenta como falta, como en la agenda.
            ->callAction(TestAction::make('missed')->arguments(['appointment' => $ana->id]))
            ->assertNotified('Marcada como inasistencia')
            ->assertDontSee(['Citas sin cerrar', '17:00 · Ana Prueba']);

        $this->assertSame(AppointmentStatus::Completed, $pedro->refresh()->status);
        $this->assertSame(AppointmentStatus::NoShow, $ana->refresh()->status);
        $this->assertSame(120.0, $pedro->patient->balance());
    }

    public function test_unclosed_appointments_at_the_same_hour_are_listed_in_the_order_they_were_booked(): void
    {
        // Las dos a las 4 p. m., cada una con su doctor: primero se agendó la de Sofía.
        $sofia = $this->visit('Sofía', '2026-11-02 16:00', AppointmentStatus::Confirmed);
        $andres = $this->visit('Andrés', '2026-11-02 16:00', AppointmentStatus::Pending, $this->doctorOffering($this->treatment));
        $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Lima'));
        $this->actingAs(User::factory()->reception()->create());

        // El mismo orden en cualquier base de datos, aunque tengan la misma hora.
        $this->assertSame([$sofia->id, $andres->id], (new CashClosing(CarbonImmutable::today()))->unclosedAppointments()->pluck('id')->all());
        Livewire::test(CashClosingCheck::class, ['pageFilters' => ['date' => '2026-11-02']])
            ->assertSeeInOrder(['Citas sin cerrar', '16:00 · Sofía Prueba', '16:00 · Andrés Prueba']);
    }

    public function test_before_closing_the_day_it_says_what_is_still_open(): void
    {
        $this->visit('Pedro', '2026-11-02 16:00', AppointmentStatus::Confirmed);
        $this->visit('Rosa', '2026-11-02 18:30', AppointmentStatus::CheckedIn);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Lima'));
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(CashClosingCheck::class, ['pageFilters' => ['date' => '2026-11-02']])
            ->mountAction('closeDay')
            ->assertMountedActionModalSee('Antes de cerrar: 1 cita quedó sin cerrar y 1 cita sigue en curso.');
    }

    public function test_the_buttons_of_each_row_look_up_its_appointment_only_once(): void
    {
        $this->visit('Pedro', '2026-11-02 09:00', AppointmentStatus::Confirmed);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Lima'));
        $this->actingAs(User::factory()->reception()->create());
        $withOneRow = $this->queriesToShowTheClosing();

        foreach (['Ana', 'Luis', 'Rosa'] as $hour => $firstName) {
            $this->visit($firstName, '2026-11-02 1'.$hour.':00', AppointmentStatus::Confirmed);
        }

        // Cada botón mira su cita varias veces al pintarse (si se ve, si se puede): antes cada vez la volvía a buscar
        // y eran unas 60 consultas por fila.
        $this->assertLessThanOrEqual($withOneRow + 3 * 8, $this->queriesToShowTheClosing());
    }

    private function visit(string $firstName, string $when, AppointmentStatus $status, ?Doctor $doctor = null): Appointment
    {
        return Appointment::factory()
            ->for($doctor ?? $this->doctor)
            ->for($this->treatment)
            ->for(Patient::factory()->create(['first_name' => $firstName, 'last_name' => 'Prueba']))
            ->at($when)
            ->create(['status' => $status]);
    }

    private function queriesToShowTheClosing(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(CashClosingCheck::class, ['pageFilters' => ['date' => '2026-11-02']])
            ->assertSee(['Atendido', 'No asistió']);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
