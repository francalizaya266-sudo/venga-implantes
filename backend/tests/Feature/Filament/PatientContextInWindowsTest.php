<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Enums\PlanItemStatus;
use App\Enums\PlanPaymentMode;
use App\Enums\TreatmentPlanStatus;
use App\Filament\Pages\Dashboard;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\Billing\BillingService;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/**
 * "Nueva cita" y "Llegó sin cita" dicen lo que toca con el paciente que se elige (lo que le falta de su plan, que lo
 * elegido es una sesión de su plan, que ya tuvo su evaluación, el presupuesto sin respuesta, la cita a la que faltó)
 * y lo dejan listo de un toque.
 */
class PatientContextInWindowsTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Treatment $control;

    private Treatment $filling;

    private Doctor $doctor;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->control = Treatment::factory()->create(['name' => 'Control de ortodoncia', 'duration_minutes' => 30]);
        $this->filling = Treatment::factory()->create(['name' => 'Curación con resina', 'duration_minutes' => 30]);
        $this->doctor = $this->doctorOffering($this->control, $this->filling);
        $this->doctorOffering($this->control, $this->filling);
        $this->patient = Patient::factory()->create();
        $this->actingAs(User::factory()->reception()->create());
    }

    public function test_the_plan_session_left_to_book_is_offered_and_one_tap_fills_the_window(): void
    {
        $plan = $this->plan(TreatmentPlanStatus::InProgress, PlanPaymentMode::Installments);
        $item = $plan->items()->create(['treatment_id' => $this->control->id, 'description' => 'Control de ortodoncia', 'unit_price' => 0, 'sessions_planned' => 12, 'sessions_done' => 2, 'status' => PlanItemStatus::InProgress, 'accepted_at' => now()->subMonths(2), 'in_payment_schedule' => true]);
        $plan->installments()->create(['number' => 1, 'due_on' => '2026-10-01', 'amount' => 250]);
        app(BillingService::class)->chargeDueInstallments();
        $useIt = TestAction::make('usePatientContext')->schemaComponent('patientContext', 'mountedActionSchema0');

        Livewire::test(Dashboard::class)
            ->mountAction('newAppointment')
            ->setActionData(['patient_id' => $this->patient->id])
            // Lo que debe va bajo su nombre, y lo que le toca de su plan, con el botón que lo agenda.
            ->assertMountedActionModalSee(['Debe S/ 250.00 · cuota vencida desde el 1 de octubre.', 'Le toca de su plan: Control de ortodoncia · sesión 3 de 12', 'Todavía no está agendada.', 'Agendar esa sesión'])
            ->callAction($useIt)
            ->assertActionDataSet(['treatment_plan_item_id' => $item->id, 'treatment_id' => $this->control->id, 'doctor_id' => $this->doctor->id])
            ->assertMountedActionModalSee('El acordado en su plan')
            ->assertMountedActionModalDontSee('Le toca de su plan');

        // Si llega sin cita, se le pregunta si viene por eso; lo que debe sale "Antes de pasar".
        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $this->patient->id])
            ->assertMountedActionModalSee(['Le toca de su plan: Control de ortodoncia · sesión 3 de 12', '¿Viene por eso?', 'Sí, viene por eso', 'Debe S/ 250.00 de antes (cuota vencida desde el 1 de octubre).'])
            ->callAction($useIt)
            ->assertActionDataSet(['treatment_plan_item_id' => $item->id, 'treatment_id' => $this->control->id, 'doctor_id' => $this->doctor->id]);
    }

    public function test_the_same_treatment_as_the_plan_asks_whether_it_is_that_session_and_counts_the_booked_ones(): void
    {
        $item = $this->plan(TreatmentPlanStatus::InProgress)->items()->create(['treatment_id' => $this->filling->id, 'description' => 'Curación con resina', 'tooth' => '36', 'unit_price' => 150, 'sessions_planned' => 2, 'status' => PlanItemStatus::Pending, 'accepted_at' => now()->subWeek()]);
        Appointment::factory()->for($this->doctor)->for($this->filling)->for($this->patient)->at('2026-11-04 10:00')->confirmed()->create(['treatment_plan_item_id' => $item->id]);

        Livewire::test(Dashboard::class)
            ->mountAction('newAppointment')
            ->setActionData(['patient_id' => $this->patient->id, 'date' => '2026-11-04'])
            // La sesión 1 ya tiene cita: la que toca agendar es la 2 (recepción no ve la pieza).
            ->assertFormFieldExists('treatment_plan_item_id', 'mountedActionSchema0', fn (Select $field) => $field->getOptions() === [$item->id => 'Curación con resina · sesión 2 de 2'])
            ->assertMountedActionModalDontSee('Le toca de su plan')
            ->setActionData(['treatment_id' => $this->filling->id])
            ->assertMountedActionModalSee(['¿Es la sesión de su plan?', 'Curación con resina · sesión 2 de 2 está en su plan. Si es eso, márcala', 'Sí, es esa sesión'])
            ->callAction(TestAction::make('usePatientContext')->schemaComponent('patientContext', 'mountedActionSchema0'))
            ->assertActionDataSet(['treatment_plan_item_id' => $item->id])
            // Su cita de ese día es la sesión anterior, no la misma.
            ->assertMountedActionModalSee(['Ese día ya tiene cita a las 10:00', 'Esa es la sesión anterior de este procedimiento: esta cita sería la siguiente.'])
            ->assertMountedActionModalDontSee('Esta sesión de su plan ya está agendada.');

        Appointment::factory()->for($this->doctor)->for($this->filling)->for($this->patient)->at('2026-11-11 10:00')->confirmed()->create(['treatment_plan_item_id' => $item->id]);

        // Con las dos agendadas, se dice; y elegirla otra vez sí es la misma cita.
        Livewire::test(Dashboard::class)
            ->mountAction('newAppointment')
            ->setActionData(['patient_id' => $this->patient->id, 'date' => '2026-11-11'])
            ->assertFormFieldExists('treatment_plan_item_id', 'mountedActionSchema0', fn (Select $field) => $field->getOptions() === [$item->id => 'Curación con resina · ya agendada'])
            ->setActionData(['treatment_id' => $this->filling->id])
            ->assertMountedActionModalDontSee('¿Es la sesión de su plan?')
            ->setActionData(['treatment_plan_item_id' => $item->id])
            ->assertMountedActionModalSee('Esta sesión de su plan ya está agendada. Continúa solo si necesita otra.');
    }

    public function test_an_evaluation_for_who_already_had_one_and_has_a_plan_is_questioned(): void
    {
        $evaluation = Treatment::factory()->evaluation()->create();
        $this->doctor->treatments()->attach($evaluation->id);
        Appointment::factory()->for($this->doctor)->for($evaluation)->for($this->patient)->at('2026-10-20 10:00')->create(['status' => AppointmentStatus::Completed]);
        $item = $this->plan(TreatmentPlanStatus::Proposed)->items()->create(['treatment_id' => $this->filling->id, 'description' => 'Curación con resina', 'unit_price' => 150]);

        Livewire::test(Dashboard::class)
            ->mountAction('newAppointment')
            ->setActionData(['patient_id' => $this->patient->id])
            ->assertMountedActionModalDontSee('Ya tuvo su evaluación')
            ->setActionData(['treatment_id' => $evaluation->id])
            ->assertMountedActionModalSee(['Ya tuvo su evaluación el 20 de octubre', 'Y ya tiene un plan. ¿Es otra evaluación?', 'Es por su plan: Curación con resina'])
            ->callAction(TestAction::make('usePatientContext')->schemaComponent('patientContext', 'mountedActionSchema0'))
            ->assertActionDataSet(['treatment_plan_item_id' => $item->id, 'treatment_id' => $this->filling->id]);
    }

    public function test_a_budget_without_answer_is_asked_about_when_booking_and_when_they_arrive(): void
    {
        $plan = $this->plan(TreatmentPlanStatus::Proposed);
        $plan->update(['proposed_at' => '2026-10-23 10:00']);
        $plan->items()->create(['description' => 'Ortodoncia con brackets', 'unit_price' => 4500]);

        Livewire::test(Dashboard::class)
            ->mountAction('newAppointment')
            ->setActionData(['patient_id' => $this->patient->id])
            ->assertMountedActionModalSee(['Presupuesto sin respuesta', 'Ortodoncia con brackets · S/ 4,500.00 · se le presentó el 23 de octubre. Pregúntale si lo acepta.']);

        // En el mostrador, lo dice "Antes de pasar".
        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $this->patient->id])
            ->assertMountedActionModalSee('Tiene un presupuesto sin respuesta desde el 23 de octubre: pregúntale si lo acepta.')
            ->assertMountedActionModalDontSee('Pregúntale si lo acepta.');
    }

    public function test_a_missed_visit_is_offered_again_and_who_misses_often_is_called_to_confirm(): void
    {
        Appointment::factory()->for($this->doctor)->for($this->control)->for($this->patient)->at('2026-10-05 10:00')->create(['status' => AppointmentStatus::NoShow]);
        Appointment::factory()->for($this->doctor)->for($this->filling)->for($this->patient)->at('2026-10-19 11:00')->create(['status' => AppointmentStatus::NoShow]);
        $reschedule = TestAction::make('usePatientContext')->schemaComponent('patientContext', 'mountedActionSchema0');

        Livewire::test(Dashboard::class)
            ->mountAction('newAppointment')
            ->setActionData(['patient_id' => $this->patient->id])
            ->assertMountedActionModalSee(['Faltó a 2 de sus últimas 2 citas: confírmale por llamada.', 'Faltó a su cita del 19 de octubre', 'Curación con resina · sigue sin otra cita.', 'Reagendar eso'])
            ->callAction($reschedule)
            ->assertActionDataSet(['treatment_id' => $this->filling->id, 'doctor_id' => $this->doctor->id])
            ->assertMountedActionModalDontSee('Faltó a su cita del 19 de octubre');

        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $this->patient->id])
            ->assertMountedActionModalSee(['Faltó a su cita del 19 de octubre', '¿Viene por eso?', 'Sí, viene por eso'])
            ->callAction($reschedule)
            ->assertActionDataSet(['treatment_id' => $this->filling->id, 'doctor_id' => $this->doctor->id]);
    }

    private function plan(TreatmentPlanStatus $status, PlanPaymentMode $mode = PlanPaymentMode::PerSession): TreatmentPlan
    {
        $accepted = in_array($status, TreatmentPlan::ACCEPTED_STATUSES, true);

        return TreatmentPlan::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'title' => '',
            'status' => $status,
            'payment_mode' => $mode,
            'proposed_at' => now()->subDays(3),
            'accepted_at' => $accepted ? now()->subMonths(2) : null,
        ]);
    }
}
