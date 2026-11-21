<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Enums\TreatmentPlanStatus;
use App\Filament\Pages\Agenda;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Resources\Consultations\ConsultationResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Livewire\PanelPulse;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Supply;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\Booking\AppointmentService;
use App\Services\Dashboard\TodayPulse;
use App\Services\StaffAlerts;
use Filament\Facades\Filament;
use Filament\Notifications\NotificationMailbox;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** Lo que guarda una persona lo ven las demás en pocos segundos, sin recargar la página. */
class LivePanelTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Doctor $doctor;

    private Appointment $rosa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $this->doctor = $this->doctorOffering($treatment);
        $this->rosa = Appointment::factory()
            ->for($this->doctor)
            ->for($treatment)
            ->for(Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']))
            ->at('2026-11-02 10:00')
            ->create();
    }

    public function test_the_doctor_screen_learns_in_its_next_check_that_reception_marked_the_arrival(): void
    {
        $reception = User::factory()->reception()->create();
        $this->actingAs($this->doctor->user);

        // La pantalla del doctor está abierta y nadie guardó nada: no se vuelve a pintar.
        $screen = Livewire::test(PanelPulse::class)
            ->call('check')
            ->assertNotDispatched(Dashboard::CHANGED)
            ->assertNotNotified();

        app(AppointmentService::class)->markArrived($this->rosa, $reception);

        // En la siguiente consulta se entera: se pintan las tarjetas, la campana se pone al día y sale el aviso.
        $screen->call('check')
            ->assertDispatched(Dashboard::CHANGED)
            ->assertDispatched('databaseNotificationsSent')
            ->assertNotified('Llegó Rosa Quispe');

        // Y no lo repite mientras no pase nada más.
        $screen->call('check')
            ->assertNotDispatched(Dashboard::CHANGED)
            ->assertNotNotified();
    }

    public function test_the_screen_where_the_change_was_made_is_not_painted_twice(): void
    {
        $this->actingAs(User::factory()->reception()->create());
        $screen = Livewire::test(PanelPulse::class);

        app(AppointmentService::class)->markArrived($this->rosa, auth()->user());

        // La página ya avisó a sus tarjetas al terminar la acción: el pulso solo toma nota.
        $screen->call('acknowledge')->assertNotDispatched(Dashboard::CHANGED);
        $screen->call('check')->assertNotDispatched(Dashboard::CHANGED);
    }

    public function test_the_doctor_is_told_their_patient_is_waiting_until_they_call_them_in(): void
    {
        $reception = User::factory()->reception()->create();
        $appointments = app(AppointmentService::class);

        $appointments->markArrived($this->rosa, $reception);

        $alert = $this->doctor->user->unreadNotifications()->sole();
        $this->assertSame('Llegó Rosa Quispe', $alert->data['title']);
        $this->assertSame('Te espera en sala. Cita de las 10:00 · Limpieza dental.', $alert->data['body']);
        // Recepción lo marcó: a ella no le llega nada.
        $this->assertSame(0, $reception->notifications()->count());

        // Al pasarla a consulta, el aviso ya cumplió: queda como leído y la campana vuelve a cero.
        $appointments->markStarted($this->rosa, $this->doctor->user);
        $this->assertSame(0, $this->doctor->user->unreadNotifications()->count());
        $this->assertSame(1, $this->doctor->user->notifications()->count());
    }

    public function test_the_arrival_stays_on_screen_with_attend_until_the_patient_goes_in(): void
    {
        $reception = User::factory()->reception()->create();
        $this->actingAs($this->doctor->user);
        $screen = Livewire::test(PanelPulse::class);

        app(AppointmentService::class)->markArrived($this->rosa, $reception);
        $alert = $this->doctor->user->unreadNotifications()->sole();

        // No se va sola: queda con "Atender" hasta que el doctor lo atienda o la cierre.
        $screen->call('check');
        $toast = $this->poppedUpAlerts()[0];
        $this->assertSame('Llegó Rosa Quispe', $toast['title']);
        $this->assertSame('alert-'.$alert->id, $toast['id']);
        $this->assertSame('persistent', $toast['duration']);
        $this->assertSame('success', $toast['iconColor']);
        $this->assertSame(['Atender', 'Cerrar'], array_column($toast['actions'], 'label'));
        $this->assertSame(PanelPulse::ATTEND, $toast['actions'][0]['event']);

        // Si pasa a consulta desde otra pantalla, el aviso se cierra solo.
        app(AppointmentService::class)->markStarted($this->rosa, $reception);
        $screen->call('check')->assertDispatched('close-notification', id: 'alert-'.$alert->id);
    }

    public function test_attend_on_the_arrival_opens_the_consultation(): void
    {
        app(AppointmentService::class)->markArrived($this->rosa, User::factory()->reception()->create());
        $this->actingAs($this->doctor->user);

        Livewire::test(PanelPulse::class)->dispatch(PanelPulse::ATTEND, appointment: $this->rosa->id)
            ->assertRedirect(ConsultationResource::getUrl('edit', ['record' => $this->rosa->consultation()->sole()]));

        $this->assertSame(AppointmentStatus::InProgress, $this->rosa->fresh()->status);
        $this->assertSame(0, $this->doctor->user->unreadNotifications()->count());
    }

    public function test_only_the_doctor_of_the_visit_can_attend_from_the_alert(): void
    {
        app(AppointmentService::class)->markArrived($this->rosa, User::factory()->reception()->create());
        $other = $this->doctorOffering(Treatment::factory()->create());
        $this->actingAs($other->user);

        Livewire::test(PanelPulse::class)->call('attend', $this->rosa->id)
            ->assertNoRedirect()
            ->assertNotified('Ese paciente ya no está en sala');

        $this->assertSame(AppointmentStatus::CheckedIn, $this->rosa->fresh()->status);
        $this->assertSame(0, Consultation::query()->count());
    }

    public function test_an_alert_that_asks_for_something_carries_its_button_on_screen_and_leaves_the_bell_read(): void
    {
        [$reception, $colleague] = User::factory()->reception()->count(2)->create();
        $this->actingAs($reception);
        $screen = Livewire::test(PanelPulse::class);

        app(StaffAlerts::class)->toReception('Nueva cita web', 'Rosa Quispe · Limpieza', link: ['label' => 'Enviar la confirmación', 'url' => WhatsappMessageResource::getUrl()]);
        app(StaffAlerts::class)->toReception('Dr. Luis Rojas agendó una cita', 'Rosa Quispe · Limpieza');
        $asks = $reception->notifications()->where('data->title', 'Nueva cita web')->sole();
        $tells = $reception->notifications()->where('data->title', 'Dr. Luis Rojas agendó una cita')->sole();

        // La que pide algo lleva su botón y se va sola como las demás; la que solo informa, en gris y sin botón.
        $screen->call('check');
        ['Nueva cita web' => $toAct, 'Dr. Luis Rojas agendó una cita' => $toKnow] = array_column($this->poppedUpAlerts(), null, 'title');
        $this->assertSame(['Enviar la confirmación'], array_column($toAct['actions'], 'label'));
        $this->assertSame(['alert' => $asks->id], $toAct['actions'][0]['eventData']);
        $this->assertSame('primary', $toAct['iconColor']);
        $this->assertNotSame('persistent', $toAct['duration']);
        $this->assertSame([], $toKnow['actions']);
        $this->assertSame('gray', $toKnow['iconColor']);

        $screen->call('openAlert', $asks->id)->assertRedirect(WhatsappMessageResource::getUrl());
        $this->assertNotNull($asks->fresh()->read_at);
        $this->assertNull($tells->fresh()->read_at);

        // Solo los suyos: el de otra persona no se toca.
        $other = $colleague->notifications()->where('data->title', 'Nueva cita web')->sole();
        $screen->call('openAlert', $other->id)->assertNoRedirect();
        $this->assertNull($other->fresh()->read_at);
    }

    public function test_the_doctor_who_marks_the_arrival_is_not_told_what_they_just_did(): void
    {
        app(AppointmentService::class)->markArrived($this->rosa, $this->doctor->user);

        $this->assertSame(0, $this->doctor->user->notifications()->count());
    }

    public function test_a_doctor_whose_user_is_no_longer_active_gets_no_alert(): void
    {
        $this->doctor->user->update(['is_active' => false]);

        app(AppointmentService::class)->markArrived($this->rosa, User::factory()->reception()->create());

        $this->assertSame(0, $this->doctor->user->notifications()->count());
    }

    public function test_a_new_alert_in_the_bell_pops_up_once_and_older_ones_do_not(): void
    {
        $reception = User::factory()->reception()->create();
        $alerts = app(StaffAlerts::class);
        $alerts->toReception('Se liberó un horario', 'Lunes a las 10:00.');
        $this->actingAs($reception);

        // Lo que ya estaba en la campana al abrir la pantalla no vuelve a saltar.
        $this->travel(5)->seconds();
        $screen = Livewire::test(PanelPulse::class);
        $this->rosa->touch();
        $screen->call('check')->assertDispatched(Dashboard::CHANGED)->assertNotNotified();

        // Llegan cuatro de golpe: saltan tres y el resto queda en la campana.
        collect(['Uno', 'Dos', 'Tres', 'Cuatro'])->each(fn (string $title) => $alerts->toReception($title));
        $screen->call('check')->assertDispatched('databaseNotificationsSent');
        $this->assertCount(3, $this->poppedUp());
        $this->assertSame(5, $reception->unreadNotifications()->count());

        // Otro aviso en ese mismo segundo sí salta; los de antes, no.
        $alerts->toReception('Cinco');
        $screen->call('check');
        $this->assertSame(['Cinco'], $this->poppedUp());
    }

    public function test_the_mark_moves_only_when_something_of_the_day_is_really_saved(): void
    {
        $mark = TodayPulse::stamp();

        // Lo que no cambia la agenda ni la caja no molesta a las pantallas.
        Treatment::factory()->create();
        $this->assertSame($mark, TodayPulse::stamp());

        // Dentro de una transacción la marca espera: quien la viera antes leería los datos viejos.
        DB::transaction(function () use ($mark) {
            $this->rosa->update(['internal_notes' => 'Viene con su mamá']);
            $this->assertSame($mark, TodayPulse::stamp());
        });
        $this->assertNotSame($mark, $mark = TodayPulse::stamp());

        // Si el cambio no llegó a guardarse, no pasó nada.
        try {
            DB::transaction(function () {
                $this->rosa->update(['internal_notes' => 'No se guarda']);

                throw new RuntimeException('Algo falló antes de terminar');
            });
        } catch (RuntimeException) {
            // Era lo esperado.
        }
        $this->assertSame($mark, TodayPulse::stamp());

        // Borrar también cuenta.
        $this->rosa->delete();
        $this->assertNotSame($mark, TodayPulse::stamp());
    }

    public function test_the_bell_keeps_todays_alerts_and_reads_the_ones_from_earlier_days(): void
    {
        $reception = User::factory()->reception()->create();
        $alerts = app(StaffAlerts::class);

        // Lo de días anteriores: una cita web del sábado y un horario que se liberó anoche, a las 23:59.
        $this->travelTo(now()->subDays(2));
        $alerts->toReception('Nueva cita web');
        $this->travelTo(now()->addDays(2)->setTime(0, 0)->subMinute());
        $alerts->toReception('Se liberó un horario');
        $this->travelTo(now()->addMinutes(2));
        $alerts->toReception('Pidió su enlace de Mi cuenta');

        $this->artisan('campana:limpiar')
            ->expectsOutput('2 avisos de días anteriores quedaron leídos.')
            ->assertSuccessful();

        // La campana muestra lo de hoy; lo anterior sigue ahí, ya leído.
        $this->assertSame(['Pidió su enlace de Mi cuenta'], $reception->unreadNotifications()->pluck('data')->pluck('title')->all());
        $this->assertSame(3, $reception->notifications()->count());
    }

    public function test_what_por_hacer_hoy_shows_also_moves_the_mark(): void
    {
        $rosa = $this->rosa->patient;
        $saves = [
            'un presupuesto' => fn () => TreatmentPlan::create(['patient_id' => $rosa->id, 'doctor_id' => $this->rosa->doctor_id, 'status' => TreatmentPlanStatus::Proposed]),
            'un trabajo de laboratorio' => fn () => LabOrder::factory()->create(),
            'un material' => fn () => Supply::factory()->create(),
        ];

        // Un presupuesto aceptado, el laboratorio o un material desde otra computadora: "Por hacer hoy" se entera.
        foreach ($saves as $what => $save) {
            $mark = TodayPulse::stamp();
            $save();
            $this->assertNotSame($mark, TodayPulse::stamp(), "Guardar {$what} no movió la marca.");
        }
    }

    public function test_the_pulse_is_on_every_screen_of_whoever_is_signed_in_and_the_agenda_and_the_list_listen(): void
    {
        $this->get(Filament::getPanel('admin')->getLoginUrl())->assertOk()->assertDontSeeLivewire(PanelPulse::class);

        $this->actingAs(User::factory()->reception()->create());
        $this->get(Dashboard::getUrl())->assertOk()->assertSeeLivewire(PanelPulse::class)->assertSee('wire:poll.3s="check"', escape: false);
        $this->get(Agenda::getUrl())->assertOk()->assertSeeLivewire(PanelPulse::class);

        // La grilla y la lista del día se vuelven a pintar con el mismo aviso que las tarjetas de "Hoy".
        Livewire::test(Agenda::class)->assertSeeHtml('x-on:today-changed.window="refresh()"');
        Livewire::withQueryParams(['mode' => 'list'])->test(Agenda::class)->assertSeeHtml('x-on:today-changed.window="refresh()"');
        Livewire::test(ListAppointments::class)->dispatch(Dashboard::CHANGED)->assertOk();
    }

    /**
     * Los avisos de la campana que saltaron en pantalla desde la última vez que se miró, tal como se mostraron.
     *
     * @return list<array<string, mixed>>
     */
    private function poppedUpAlerts(): array
    {
        $mailbox = app(NotificationMailbox::class);
        $mailbox->flush();

        return array_values(array_filter($mailbox->pull(), fn (array $toast) => str_starts_with((string) $toast['id'], 'alert-')));
    }

    /**
     * Los títulos de los avisos que saltaron en pantalla desde la última vez que se miró.
     *
     * @return list<string>
     */
    private function poppedUp(): array
    {
        $mailbox = app(NotificationMailbox::class);
        $mailbox->flush();

        return array_column($mailbox->pull(), 'title');
    }
}
