<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Livewire\BellAlerts;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Access\PatientAccess;
use App\Services\Booking\AppointmentReminders;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\RescheduleRequestService;
use App\Services\Booking\WebBookingData;
use App\Services\StaffAlerts;
use App\Services\WhatsApp\WhatsAppOutbox;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** La campana muestra solo lo pendiente: lo que solo informa se va después de verlo, y lo que pide algo, al hacerlo. */
class BellAlertsTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private User $reception;

    private Doctor $doctor;

    private Treatment $treatment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->reception = User::factory()->reception()->create();
        $this->treatment = Treatment::factory()->evaluation()->create();
        $this->doctor = $this->doctorOffering($this->treatment);
        $this->actingAs($this->reception);
    }

    public function test_the_bell_shows_only_what_is_pending_and_what_only_informs_goes_away_after_seeing_it(): void
    {
        $alerts = app(StaffAlerts::class);
        $alerts->toReception('Pidió su enlace de Mi cuenta', 'Rosa Quispe quiere entrar a Mi cuenta.', link: ['label' => 'Enviar el enlace', 'url' => WhatsappMessageResource::getUrl()]);
        $alerts->toReception('Un paciente canceló su cita', 'Pedro Mamani canceló el martes.');
        $alerts->toReception('Se liberó un horario', 'Martes 10:00.');
        $this->alert('Se liberó un horario')->markAsRead();

        // Lo ya leído no se muestra.
        $bell = Livewire::test(BellAlerts::class)
            ->assertSee('Pidió su enlace de Mi cuenta')
            ->assertSee('Un paciente canceló su cita')
            ->assertDontSee('Se liberó un horario');

        // Al abrirla, lo que solo informa queda visto, pero sigue a la vista mientras está abierta.
        $bell->call('opened')
            ->assertSee('Un paciente canceló su cita');
        $this->assertNotNull($this->alert('Un paciente canceló su cita')->read_at);
        $this->assertNull($this->alert('Pidió su enlace de Mi cuenta')->read_at);

        // Al cerrarla, se va. Lo que pide algo se queda hasta que se haga.
        $bell->call('closed')
            ->assertDontSee('Un paciente canceló su cita')
            ->assertSee('Pidió su enlace de Mi cuenta');
    }

    public function test_the_confirmation_to_send_leaves_the_bell_once_it_is_sent(): void
    {
        Account::factory()->create(['phone' => '+51987654321']);
        $appointment = app(AppointmentService::class)->bookFromWeb($this->webBooking());
        $bell = Livewire::test(BellAlerts::class)->assertSee('Nueva cita web');

        app(WhatsAppOutbox::class)->markSent(WhatsappMessage::sole(), $this->reception);

        $bell->call('$refresh')->assertDontSee('Nueva cita web');
        $this->assertNotNull($this->alert('Nueva cita web')->read_at);
        $this->assertSame(AppointmentStatus::Pending, $appointment->fresh()->status);
    }

    public function test_the_my_account_link_of_a_first_booking_also_leaves_when_the_appointment_is_cancelled(): void
    {
        $appointment = app(AppointmentService::class)->bookFromWeb($this->webBooking());
        $this->assertSame(0, app(StaffAlerts::class)->closeDone());

        app(AppointmentService::class)->cancelByPatient($appointment);

        $this->assertSame(1, app(StaffAlerts::class)->closeDone());
        $this->assertNotNull($this->alert('Nueva cita web')->read_at);
    }

    public function test_the_access_link_and_the_requested_change_leave_once_they_are_done(): void
    {
        $account = Account::factory()->create(['phone' => '+51987654321']);
        $patient = Patient::factory()->for($account)->create();
        app(PatientAccess::class)->requestAccess('987 654 321');
        $appointment = Appointment::factory()->for($this->doctor)->for($this->treatment)->for($patient)->at('2026-11-04 10:00')->create();
        $request = app(RescheduleRequestService::class)->requestByPatient($appointment, [CarbonImmutable::parse('2026-11-05 10:00')]);

        $this->assertSame(0, app(StaffAlerts::class)->closeDone());

        app(WhatsAppOutbox::class)->markSent(WhatsappMessage::sole(), $this->reception);
        app(RescheduleRequestService::class)->reject($request, $this->reception);

        $this->assertSame(2, app(StaffAlerts::class)->closeDone());
        $this->assertNotNull($this->alert('Pidió su enlace de Mi cuenta')->read_at);
        $this->assertNotNull($this->alert('Solicitud de cambio de horario')->read_at);
    }

    public function test_the_appointments_to_call_leave_once_none_is_left_to_confirm(): void
    {
        [$rosa, $pedro] = Appointment::factory()->for($this->doctor)->for($this->treatment)->count(2)->sequence(
            ['starts_at' => '2026-11-03 10:00', 'ends_at' => '2026-11-03 10:30'],
            ['starts_at' => '2026-11-03 11:00', 'ends_at' => '2026-11-03 11:30'],
        )->create();
        $this->travelTo(now()->setTime(16, 30));
        app(AppointmentReminders::class)->flagUnconfirmed();
        $appointments = app(AppointmentService::class);

        $appointments->confirmByPatient($rosa);
        $this->assertSame(0, app(StaffAlerts::class)->closeDone(), 'Falta confirmar a una.');

        $appointments->confirmByPatient($pedro);
        $this->assertSame(1, app(StaffAlerts::class)->closeDone());
        $this->assertNotNull($this->alert('2 citas de mañana sin confirmar')->read_at);
    }

    public function test_what_was_read_more_than_a_month_ago_is_deleted(): void
    {
        $alerts = app(StaffAlerts::class);
        $alerts->toReception('Se liberó un horario');
        $this->alert('Se liberó un horario')->markAsRead();
        $this->travelTo(now()->addDays(StaffAlerts::KEEP_READ_DAYS)->addMinute());
        $alerts->toReception('Un paciente canceló su cita');

        $this->artisan('campana:limpiar')
            ->expectsOutput('1 aviso leído hace más de un mes se borró.')
            ->assertSuccessful();

        $this->assertSame(['Un paciente canceló su cita'], $this->reception->notifications()->pluck('data')->pluck('title')->all());
    }

    private function alert(string $title): DatabaseNotification
    {
        return $this->reception->notifications()->where('data->title', $title)->sole();
    }

    private function webBooking(): WebBookingData
    {
        return new WebBookingData(
            doctorId: $this->doctor->id,
            treatmentId: $this->treatment->id,
            startsAt: CarbonImmutable::parse('2026-11-03 10:00'),
            phone: '987 654 321',
            firstName: 'Carmen',
            lastName: 'Mamani',
            acceptsPrivacy: true,
        );
    }
}
