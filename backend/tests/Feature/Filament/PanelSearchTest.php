<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Filament\Pages\Agenda;
use App\Filament\Support\PanelSearch;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Livewire\GlobalSearch;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** Ctrl + K también hace: "nueva cita", "llegó sin cita", ir a una fecha y las próximas citas del paciente. */
class PanelSearchTest extends TestCase
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

    public function test_the_actions_come_first_with_a_few_letters(): void
    {
        $this->actingAs(User::factory()->reception()->create());

        $this->assertSame(['Nueva cita'], $this->titles('nue', 'Acciones'));
        $this->assertSame(['Nueva cita', 'Llegó sin cita'], $this->titles('cita', 'Acciones'));
        $this->assertSame(['Llegó sin cita'], $this->titles('llego', 'Acciones'));
        $this->assertSame(['Acciones'], $this->categories('nueva'));

        // Cada una abre su ventana sobre la agenda.
        $this->assertStringContainsString('action=createAppointment', $this->results('nueva cita', 'Acciones')[0]->url);
        $this->assertSame(Agenda::getUrl(['action' => 'walkIn']), $this->results('llego', 'Acciones')[0]->url);

        // Es el buscador del panel.
        $this->assertInstanceOf(PanelSearch::class, Filament::getGlobalSearchProvider());
        Livewire::test(GlobalSearch::class)->set('search', 'llegó')->assertSee(['Acciones', 'Llegó sin cita']);
    }

    public function test_a_date_written_as_said_goes_to_that_day(): void
    {
        $this->actingAs(User::factory()->reception()->create());

        $this->assertSame(['Ver la agenda del martes 3 de noviembre'], $this->titles('mañana', 'Acciones'));
        $this->assertSame(Agenda::getUrl(['date' => '2026-11-03', 'mode' => 'day']), $this->results('mañana', 'Acciones')[0]->url);

        foreach ([
            'hoy' => '2026-11-02',
            'ir a pasado mañana' => '2026-11-04',
            'viernes' => '2026-11-06',
            'lunes' => '2026-11-02',
            'el sábado' => '2026-11-07',
            '25' => '2026-11-25',
            '25/11' => '2026-11-25',
            '3/12/2026' => '2026-12-03',
            '2027-01-15' => '2027-01-15',
            '25 de noviembre' => '2026-11-25',
            'agenda del 8 dic' => '2026-12-08',
            // En noviembre, "5/1" es el enero que viene.
            '5/1' => '2027-01-05',
        ] as $written => $date) {
            $this->assertSame($date, PanelSearch::dateIn($written)?->toDateString(), $written);
        }

        foreach (['31/11', '12345678', 'rosa', '25 de nada', 'cita'] as $notADate) {
            $this->assertNull(PanelSearch::dateIn($notADate), $notADate);
        }
    }

    public function test_a_patient_name_also_brings_their_next_visits(): void
    {
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Huamán']);
        $next = $this->visit($rosa, '2026-11-03 10:00');
        $this->visit($rosa, '2026-10-20 10:00', AppointmentStatus::Completed);
        $this->visit($rosa, '2026-11-05 10:00', AppointmentStatus::Cancelled);
        $this->actingAs(User::factory()->reception()->create());

        // Sin tilde también: el paciente y, junto a él, solo su cita que viene.
        $this->assertSame(['Rosa Huamán · Mañana 10:00'], $this->titles('rosa huaman', 'Próximas citas'));
        $this->assertSame(['Tratamiento' => 'Limpieza dental', 'Doctor' => $this->doctor->name, 'Estado' => 'Confirmada'], $this->results('rosa', 'Próximas citas')[0]->details);
        $this->assertSame(Agenda::getUrl(['date' => '2026-11-03', 'mode' => 'day', 'action' => 'appointment', 'actionArguments' => ['id' => $next->id]]), $this->results('rosa', 'Próximas citas')[0]->url);
        $this->assertSame(['pacientes', 'Próximas citas'], array_slice($this->categories('rosa'), 0, 2));

        // Un doctor solo ve las suyas.
        $other = $this->doctorOffering($this->cleaning);
        $this->actingAs($other->user);
        $this->assertNotContains('Próximas citas', $this->categories('rosa'));
    }

    public function test_the_link_opens_the_visit_panel_on_its_day(): void
    {
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Huamán']);
        $next = $this->visit($rosa, '2026-11-03 10:00');
        $this->actingAs(User::factory()->reception()->create());

        Livewire::withQueryParams(['date' => '2026-11-03', 'mode' => 'day', 'action' => 'appointment', 'actionArguments' => ['id' => $next->id]])
            ->test(Agenda::class)
            ->assertSet('date', '2026-11-03')
            // La pantalla lo abre apenas carga.
            ->call('mountDefaultActions')
            ->assertMountedActionModalSee(['Rosa Huamán', 'Limpieza dental']);
    }

    /** @return list<GlobalSearchResult> */
    private function results(string $query, string $category): array
    {
        return collect(app(PanelSearch::class)->getResults($query)->getCategories()->get($category, []))->values()->all();
    }

    /** @return list<string> */
    private function titles(string $query, string $category): array
    {
        return array_map(fn (GlobalSearchResult $result) => (string) $result->title, $this->results($query, $category));
    }

    /** @return list<string> */
    private function categories(string $query): array
    {
        return app(PanelSearch::class)->getResults($query)->getCategories()->keys()->all();
    }

    private function visit(Patient $patient, string $startsAt, AppointmentStatus $status = AppointmentStatus::Confirmed): Appointment
    {
        return Appointment::factory()->for($this->doctor)->for($this->cleaning)->for($patient)->at($startsAt)->create(['status' => $status]);
    }
}
