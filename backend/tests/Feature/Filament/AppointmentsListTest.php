<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Filament\Pages\Agenda;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Widgets\FollowUpsToday;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** La lista de Citas: sus pestañas, sus números y su orden. */
class AppointmentsListTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Treatment $treatment;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
        $this->freezeMonday();
        $this->treatment = Treatment::factory()->create(['duration_minutes' => 30]);
        $this->doctor = $this->doctorOffering($this->treatment);
        $this->actingAs(User::factory()->reception()->create());
    }

    public function test_unclosed_means_the_same_in_the_agenda_the_list_and_the_home_page(): void
    {
        $unmarked = $this->visit('Lucía', '2026-11-02 09:00');
        $inTheRoom = $this->visit('Bruno', '2026-11-02 09:30', ['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => '2026-11-02 09:25']);
        $leftInTheRoom = $this->visit('Carla', '2026-10-30 10:00', ['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => '2026-10-30 09:55']);
        $happeningNow = $this->visit('Diego', '2026-11-02 10:45');
        $later = $this->visit('Elena', '2026-11-02 16:00');
        // Pasó a consulta a las 7 y nadie cerró su atención: horas después ya no está en curso.
        $forgottenInTheChair = $this->visit('Fabio', '2026-11-02 07:00', ['status' => AppointmentStatus::InProgress, 'checked_in_at' => '2026-11-02 06:55']);
        $this->travelTo('2026-11-02 11:00');

        // La de hoy que nadie marcó, la que llegó otro día y nunca se cerró, y la que sigue en atención horas después de
        // su hora. Quien está en sala hoy sigue su curso.
        $list = Livewire::test(ListAppointments::class, ['activeTab' => 'unclosed'])
            ->assertCanSeeTableRecords([$unmarked, $leftInTheRoom, $forgottenInTheChair])
            ->assertCanNotSeeTableRecords([$inTheRoom, $happeningNow, $later]);
        $this->assertSame('3', (string) $list->instance()->getTabs()['unclosed']->getBadge());

        Livewire::test(FollowUpsToday::class)
            ->assertSeeHtmlInOrder(['Citas sin cerrar', '<span class="dash-count is-warning">3</span>']);

        $agenda = Livewire::test(Agenda::class)->set('mode', 'list');
        $this->assertSame(2, substr_count($agenda->html(), 'class="tag tag-unclosed"'));
        $agenda->assertSeeHtmlInOrder(['Fabio', 'Sin cerrar', 'Lucía', 'Sin cerrar', 'Bruno', 'En sala', 'Diego']);

        // Cada cita por venir o sin marcar está en una sola pestaña. Quien está en sala hoy sigue en Próximas; la que
        // quedó en atención desde temprano ya está en "Sin cerrar".
        Livewire::test(ListAppointments::class, ['activeTab' => 'upcoming'])
            ->assertCanSeeTableRecords([$inTheRoom, $happeningNow, $later], inOrder: true)
            ->assertCanNotSeeTableRecords([$unmarked, $leftInTheRoom, $forgottenInTheChair]);
    }

    public function test_a_patient_who_arrives_stays_in_upcoming_until_the_visit_is_closed(): void
    {
        $visit = $this->visit('Lucía', '2026-11-02 10:00');
        $this->travelTo('2026-11-02 10:05');

        Livewire::test(ListAppointments::class)
            ->callAction(TestAction::make('arrived')->table($visit))
            ->assertCanSeeTableRecords([$visit]);
        $this->assertSame(AppointmentStatus::CheckedIn, $visit->fresh()->status);

        $visit->update(['status' => AppointmentStatus::InProgress, 'started_at' => now()]);
        Livewire::test(ListAppointments::class)->assertCanSeeTableRecords([$visit]);

        $visit->update(['status' => AppointmentStatus::Completed, 'finished_at' => now()]);
        Livewire::test(ListAppointments::class)->assertCanNotSeeTableRecords([$visit]);
    }

    public function test_only_visits_forgotten_on_earlier_days_bring_the_notice(): void
    {
        $this->visit('Lucía', '2026-11-02 09:00');
        $this->travelTo('2026-11-02 11:00');

        // La de hoy que nadie marcó todavía se cierra en el día: solo cuenta en la pestaña.
        Livewire::test(ListAppointments::class)->assertDontSee(['días anteriores sin cerrar', 'Revisar ahora']);

        $this->visit('Bruno', '2026-10-30 10:00');
        $this->visit('Carla', '2026-10-31 10:00', ['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => '2026-10-31 09:55']);

        $list = Livewire::test(ListAppointments::class)
            ->assertSee(['Quedaron 2 citas de días anteriores sin cerrar', 'Revisar ahora'])
            ->assertSeeHtml(e(ListAppointments::getResource()::getUrl('index', ['tab' => 'unclosed'])));
        $this->assertSame('3', (string) $list->instance()->getTabs()['unclosed']->getBadge());

        // En la pestaña misma ya no hace falta.
        Livewire::test(ListAppointments::class, ['activeTab' => 'unclosed'])->assertDontSee('Revisar ahora');
    }

    public function test_the_list_says_what_it_searches_who_owes_and_how_to_open_a_visit(): void
    {
        $owing = $this->visit('Lucía', '2026-11-05 10:00');
        Charge::factory()->for($owing->patient)->create(['amount' => 120, 'doctor_id' => $this->doctor->id]);
        $upToDate = $this->visit('Bruno', '2026-11-06 10:00');

        Livewire::test(ListAppointments::class)
            ->assertSee(['Buscar paciente, DNI o celular', 'Filtros', 'Avisos', 'Acciones', 'Lo más cercano primero'])
            ->assertTableColumnStateSet('flags', ['Debe S/ 120'], $owing)
            ->assertTableColumnStateNotSet('flags', ['Debe S/ 120'], $upToDate)
            // La fila entera abre la cita, y el menú también lo dice.
            ->assertActionVisible(TestAction::make('openAppointment')->table($owing))
            ->assertActionHasUrl(TestAction::make('openAppointment')->table($owing), ListAppointments::getResource()::getUrl('view', ['record' => $owing]));

        Livewire::test(ListAppointments::class, ['activeTab' => 'all'])->assertSee('Lo más reciente primero');

        // El doctor no ve cuánto debe el paciente.
        $this->actingAs($this->doctor->user);
        Livewire::test(ListAppointments::class)->assertTableColumnStateNotSet('flags', ['Debe S/ 120'], $owing);
    }

    public function test_the_number_on_to_confirm_is_the_number_of_rows_and_turns_to_warning_when_someone_must_be_called(): void
    {
        $tomorrow = $this->visit('Lucía', '2026-11-03 10:00', ['status' => AppointmentStatus::Pending, 'confirmed_at' => null]);
        $nextWeek = $this->visit('Bruno', '2026-11-09 10:00', ['status' => AppointmentStatus::Pending, 'confirmed_at' => null]);
        $confirmed = $this->visit('Carla', '2026-11-03 11:00');

        $list = Livewire::test(ListAppointments::class, ['activeTab' => 'pending'])
            ->assertCanSeeTableRecords([$tomorrow, $nextWeek])
            ->assertCanNotSeeTableRecords([$confirmed]);
        $tab = $list->instance()->getTabs()['pending'];
        $this->assertSame(['2', 'gray', 'Citas que todavía no confirman'], [(string) $tab->getBadge(), $tab->getBadgeColor(), $tab->getBadgeTooltip()]);

        $tomorrow->update(['unconfirmed_flagged_at' => now()]);

        $list = Livewire::test(ListAppointments::class, ['activeTab' => 'pending']);
        $tab = $list->instance()->getTabs()['pending'];
        $this->assertSame(['2', 'warning', '1 dice "Llamar": está a un día de su cita'], [(string) $tab->getBadge(), $tab->getBadgeColor(), $tab->getBadgeTooltip()]);

        // El aviso "citas sin confirmar" llega con este filtro: quedan las que toca llamar.
        $list->filterTable('to_call')
            ->assertCanSeeTableRecords([$tomorrow])
            ->assertCanNotSeeTableRecords([$nextWeek]);
    }

    public function test_today_and_tomorrow_are_no_longer_tabs_and_an_old_link_opens_the_first_one(): void
    {
        $list = Livewire::test(ListAppointments::class, ['activeTab' => 'today'])
            ->assertSet('activeTab', 'upcoming');

        $this->assertSame(['upcoming', 'pending', 'unclosed', 'all'], array_keys($list->instance()->getTabs()));
    }

    public function test_all_starts_with_the_latest_and_says_the_year_when_it_is_not_this_one(): void
    {
        $lastYear = $this->visit('Lucía', '2025-12-01 10:00', ['status' => AppointmentStatus::Completed]);
        $lastWeek = $this->visit('Bruno', '2026-10-30 10:00', ['status' => AppointmentStatus::Completed]);
        $thisWeek = $this->visit('Carla', '2026-11-05 10:00');
        $nextWeek = $this->visit('Diego', '2026-11-10 10:00');

        Livewire::test(ListAppointments::class, ['activeTab' => 'all'])
            ->assertCanSeeTableRecords([$nextWeek, $thisWeek, $lastWeek, $lastYear], inOrder: true)
            ->assertTableColumnFormattedStateSet('starts_at', 'lun. 01/12/2025 10:00', $lastYear)
            ->assertTableColumnFormattedStateSet('starts_at', 'vie. 30/10 10:00', $lastWeek);

        // Lo que viene sigue en orden de llegada.
        Livewire::test(ListAppointments::class, ['activeTab' => 'upcoming'])
            ->assertCanSeeTableRecords([$thisWeek, $nextWeek], inOrder: true);
    }

    public function test_the_list_filters_by_a_range_of_dates_and_by_treatment(): void
    {
        $other = Treatment::factory()->create();
        $this->doctor->treatments()->attach($other);
        $monday = $this->visit('Lucía', '2026-11-02 10:00');
        $thursday = $this->visit('Bruno', '2026-11-05 10:00');
        $nextWeek = Appointment::factory()->for($this->doctor)->for($other)->confirmed()->at('2026-11-10 10:00')->create();

        Livewire::test(ListAppointments::class, ['activeTab' => 'all'])
            ->filterTable('dates', ['from' => '2026-11-03', 'until' => '2026-11-10'])
            ->assertCanSeeTableRecords([$thursday, $nextWeek])
            ->assertCanNotSeeTableRecords([$monday])
            ->assertSee('Del 03/11/2026 al 10/11/2026')
            ->filterTable('treatment_id', $other->id)
            ->assertCanSeeTableRecords([$nextWeek])
            ->assertCanNotSeeTableRecords([$thursday]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function visit(string $firstName, string $startsAt, array $attributes = []): Appointment
    {
        return Appointment::factory()
            ->for($this->doctor)
            ->for($this->treatment)
            ->for(Patient::factory()->state(['first_name' => $firstName, 'last_name' => 'Prueba']))
            ->confirmed()
            ->at($startsAt)
            ->create($attributes);
    }
}
