<?php

namespace Tests\Feature\Services\Clinical;

use App\Enums\PlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\Booking\AppointmentService;
use App\Services\Clinical\ConsultationService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** El avance del plan cuando la sesión la cierra recepción ("Atendido") y no el doctor con su atención. */
class PlanProgressTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
        $this->freezeMonday();
    }

    public function test_a_plan_session_closed_by_reception_counts_as_done(): void
    {
        [$doctor, $root] = $this->acceptedRootCanal(sessions: 2);

        $this->closedByReception($this->sessionOf($root, $doctor, '2026-11-02 10:00'));

        $this->assertSame(1, $root->fresh()->sessions_done);
        $this->assertSame(PlanItemStatus::InProgress, $root->fresh()->status);
        $this->assertSame(TreatmentPlanStatus::InProgress, $root->plan->fresh()->status);
        $this->assertSame(['Endodoncia · pieza 36 · sesión 1 de 2'], Charge::pluck('description')->all());
    }

    public function test_the_last_session_closed_by_reception_finishes_the_procedure_and_the_plan(): void
    {
        [$doctor, $root] = $this->acceptedRootCanal(sessions: 2);
        $this->closedByReception($this->sessionOf($root, $doctor, '2026-11-02 10:00'));

        $this->closedByReception($this->sessionOf($root, $doctor, '2026-11-02 11:00'));

        $this->assertSame(2, $root->fresh()->sessions_done);
        $this->assertSame(PlanItemStatus::Done, $root->fresh()->status);
        $this->assertSame(TreatmentPlanStatus::Completed, $root->plan->fresh()->status);
    }

    public function test_a_session_the_doctor_wrote_up_counts_once(): void
    {
        [$doctor, $root] = $this->acceptedRootCanal(sessions: 2);
        $consultations = app(ConsultationService::class);

        $consultations->close($consultations->startFromAppointment($this->sessionOf($root, $doctor, '2026-11-02 10:00'), $doctor->user), $doctor->user);

        $this->assertSame(1, $root->fresh()->sessions_done);
        $this->assertSame(PlanItemStatus::InProgress, $root->fresh()->status);
    }

    public function test_a_session_the_doctor_left_unmarked_does_not_count(): void
    {
        [$doctor, $root] = $this->acceptedRootCanal(sessions: 2);
        $consultations = app(ConsultationService::class);
        $consultation = $consultations->startFromAppointment($this->sessionOf($root, $doctor, '2026-11-02 10:00'), $doctor->user);
        $consultation->planItems()->detach();

        $consultations->close($consultation, $doctor->user);

        $this->assertSame(0, $root->fresh()->sessions_done);
        $this->assertSame(PlanItemStatus::Pending, $root->fresh()->status);
    }

    public function test_sessions_closed_by_reception_and_by_the_doctor_add_up(): void
    {
        [$doctor, $root] = $this->acceptedRootCanal(sessions: 3);
        $consultations = app(ConsultationService::class);
        $this->closedByReception($this->sessionOf($root, $doctor, '2026-11-02 10:00'));

        $consultations->close($consultations->startFromAppointment($this->sessionOf($root, $doctor, '2026-11-02 11:00'), $doctor->user), $doctor->user);

        $this->assertSame(2, $root->fresh()->sessions_done);
        $this->assertSame(PlanItemStatus::InProgress, $root->fresh()->status);
    }

    public function test_a_visit_outside_the_plan_closed_by_reception_leaves_the_plan_as_it_was(): void
    {
        [$doctor, $root] = $this->acceptedRootCanal(sessions: 2);
        $loose = Appointment::factory()->for($doctor)->for($root->treatment)->for($root->plan->patient)->at('2026-11-02 10:00')->confirmed()->create();

        $this->closedByReception($loose);

        $this->assertSame(0, $root->fresh()->sessions_done);
        $this->assertSame(TreatmentPlanStatus::Accepted, $root->plan->fresh()->status);
    }

    /**
     * Un plan aceptado con una endodoncia en la 36.
     *
     * @return array{0: Doctor, 1: TreatmentPlanItem}
     */
    private function acceptedRootCanal(int $sessions): array
    {
        $rootCanal = Treatment::where('slug', 'endodoncia')->firstOrFail();
        $doctor = $this->doctorOffering($rootCanal);
        $plan = TreatmentPlan::create(['patient_id' => Patient::factory()->create()->id, 'doctor_id' => $doctor->id, 'status' => TreatmentPlanStatus::Accepted]);

        return [$doctor, $plan->items()->create(['treatment_id' => $rootCanal->id, 'description' => 'Endodoncia', 'tooth' => '36', 'unit_price' => 600, 'sessions_planned' => $sessions])];
    }

    /** La cita de una sesión del procedimiento. */
    private function sessionOf(TreatmentPlanItem $item, Doctor $doctor, string $at): Appointment
    {
        return Appointment::factory()->for($doctor)->for($item->treatment)->for($item->plan->patient)->at($at)->confirmed()
            ->create(['treatment_plan_item_id' => $item->id]);
    }

    /** Recepción anota que llegó y, sin atención escrita del doctor, la cierra con "Atendido". */
    private function closedByReception(Appointment $appointment): void
    {
        $reception = User::factory()->reception()->create();

        app(AppointmentService::class)->markArrived($appointment, $reception);
        app(AppointmentService::class)->markCompleted($appointment->fresh(), $reception);
    }
}
