<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Filament\Pages\Agenda;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Consent;
use App\Models\ConsentTemplate;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** El panel al costado de la agenda, por bloques: quién es, lo clínico, el detalle, lo que se cobra, la nota y un solo botón principal. */
class AppointmentSidePanelTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Treatment $cleaning;

    private Doctor $doctor;

    private Patient $maria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'duration_minutes' => 30, 'price_from' => 120]);
        $this->doctor = $this->doctorOffering($this->cleaning);
        $this->maria = Patient::factory()->create(['first_name' => 'María', 'last_name' => 'Quispe Huamán', 'document_number' => '45678912']);
    }

    public function test_the_header_says_who_it_is_with_a_phone_to_call_and_the_debt_only_once(): void
    {
        Charge::factory()->for($this->maria)->create(['description' => 'Radiografía', 'amount' => 40]);
        $appointment = $this->visit('2026-11-02 10:00');

        $panel = $this->panel($appointment, User::factory()->reception()->create())
            ->assertMountedActionModalSeeHtml('<span class="appt-panel-avatar" aria-hidden="true">MQ</span>')
            ->assertMountedActionModalSee(['DNI 45678912', 'Debe S/ 40'])
            ->assertMountedActionModalSeeHtml('href="tel:');

        // La deuda va una vez arriba y como línea de lo que se cobra; ya no hay una fila "Saldo" aparte.
        $panel->assertMountedActionModalDontSee('Saldo:');
    }

    public function test_the_status_counts_the_minutes_in_the_waiting_room_and_turns_red_after_twenty_of_delay(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:12', 'America/Lima'));
        $appointment = $this->visit('2026-11-02 10:00', ['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => '2026-11-02 09:58']);
        $reception = User::factory()->reception()->create();

        $this->panel($appointment, $reception)->assertMountedActionModalSee("En sala · 14\u{00A0}min");
        $this->assertSame(['label' => "En sala · 14\u{00A0}min", 'color' => 'info', 'long' => false, 'minutes' => 14], Agenda::liveState($appointment->fresh()));

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:25', 'America/Lima'));
        $this->assertSame('danger', Agenda::liveState($appointment->fresh())['color']);

        // En atención: los minutos desde que pasó, en ámbar cuando ya pasó la hora en que debía terminar.
        $appointment->update(['status' => AppointmentStatus::InProgress, 'started_at' => '2026-11-02 10:05']);
        $this->assertSame(["En atención · 20\u{00A0}min", 'primary'], [Agenda::liveState($appointment->fresh())['label'], Agenda::liveState($appointment->fresh())['color']]);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:40', 'America/Lima'));
        $this->assertSame('warning', Agenda::liveState($appointment->fresh())['color']);
    }

    public function test_whoever_attends_sees_if_nobody_asked_about_allergies_and_the_missing_consent(): void
    {
        ConsentTemplate::create(['name' => 'Consentimiento de limpieza', 'treatment_id' => $this->cleaning->id, 'body' => 'Limpieza de {paciente}.']);
        $appointment = $this->visit('2026-11-02 10:00');

        $this->panel($appointment, $this->doctor->user)
            ->assertMountedActionModalSee(['Antecedentes sin llenar', 'Falta consentimiento'])
            ->assertMountedActionModalDontSee('Sin alergias');

        // Recepción no ve lo clínico.
        $this->panel($appointment, User::factory()->reception()->create())
            ->assertMountedActionModalDontSee(['Antecedentes sin llenar', 'consentimiento']);
    }

    public function test_answered_background_without_alerts_says_so_and_a_signed_consent_is_ticked(): void
    {
        $template = ConsentTemplate::create(['name' => 'Consentimiento de limpieza', 'treatment_id' => $this->cleaning->id, 'body' => 'Limpieza de {paciente}.']);
        $this->maria->medicalBackground()->create(['allergies' => 'Ninguna']);
        $appointment = $this->visit('2026-11-02 10:00');
        Consent::create(['patient_id' => $this->maria->id, 'consent_template_id' => $template->id, 'treatment_id' => $this->cleaning->id, 'title' => $template->name, 'body_snapshot' => 'Firmado.', 'signer_name' => $this->maria->full_name, 'signed_at' => now()]);

        $this->panel($appointment, $this->doctor->user)
            ->assertMountedActionModalSee(['Sin alergias ni enfermedades registradas', 'Consentimiento firmado'])
            ->assertMountedActionModalDontSee('Antecedentes sin llenar');
    }

    public function test_what_is_charged_that_day_reads_like_a_receipt_and_the_list_price_is_approximate(): void
    {
        Charge::factory()->for($this->maria)->create(['description' => 'Radiografía', 'amount' => 40]);
        $appointment = $this->visit('2026-11-02 10:00');

        $this->panel($appointment, User::factory()->reception()->create())
            ->assertMountedActionModalSeeHtml('<h3>Para cobrar hoy</h3>')
            ->assertMountedActionModalSee(['Saldo pendiente (Radiografía)', 'S/ 40.00', 'Cita de hoy: Limpieza dental', '(aprox.)', 'S/ 120.00', 'Total a cobrar', 'S/ 160.00']);
    }

    public function test_the_internal_note_is_added_from_its_own_block(): void
    {
        $appointment = $this->visit('2026-11-02 10:00');

        $this->panel($appointment, User::factory()->reception()->create())
            ->assertMountedActionModalSee(['Nota interna', 'Sin nota.', 'Agregar nota'])
            ->mountAction(TestAction::make('editNotes')->arguments(['id' => $appointment->id]))
            ->setActionData(['internal_notes' => 'Viene con su mamá'])
            ->callMountedAction()
            ->assertNotified('Notas guardadas');

        $this->assertSame('Viene con su mamá', $appointment->fresh()->internal_notes);
    }

    public function test_one_step_is_the_main_button_and_the_others_are_outlined(): void
    {
        $appointment = $this->visit('2026-11-02 10:00', ['status' => AppointmentStatus::Pending]);
        $reception = User::factory()->reception()->create();

        // Pendiente de hoy: "Llegó" es el principal; "Confirmar" queda con borde.
        $html = $this->panel($appointment, $reception)->getMountedActionModalHtml();
        $this->assertSame(1, substr_count($html, 'appt-main-step'));
        $this->assertMatchesRegularExpression('/appt-main-step[^>]*>(?:(?!<\/button>).)*Llegó/s', $html);
        $this->assertMatchesRegularExpression('/fi-outlined[^>]*>(?:(?!<\/button>).)*Confirmar/s', $html);

        // Atendida sin pagar: "Cobrar".
        $appointment->update(['status' => AppointmentStatus::Completed]);
        Charge::factory()->for($this->maria)->create(['appointment_id' => $appointment->id, 'amount' => 120]);
        $this->assertMatchesRegularExpression('/appt-main-step[^>]*>(?:(?!<\/button>).)*Cobrar/s', $this->panel($appointment, $reception)->getMountedActionModalHtml());
    }

    /** @param  array<string, mixed>  $attributes */
    private function visit(string $startsAt, array $attributes = []): Appointment
    {
        return Appointment::factory()->for($this->doctor)->for($this->cleaning)->for($this->maria)->confirmed()->at($startsAt)->create($attributes);
    }

    private function panel(Appointment $appointment, User $user): Testable
    {
        $this->actingAs($user);

        return Livewire::test(Agenda::class, ['date' => $appointment->starts_at->toDateString()])
            ->mountAction(TestAction::make('appointment')->arguments(['id' => $appointment->id]));
    }
}
