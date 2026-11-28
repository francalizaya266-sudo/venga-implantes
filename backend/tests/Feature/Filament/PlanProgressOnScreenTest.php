<?php

namespace Tests\Feature\Filament;

use App\Enums\PlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Consultations\Pages\EditConsultation;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Filament\Support\PatientSummary;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\Clinical\ConsultationService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** El avance del plan se lee donde se mira al paciente: en su historia, en su ficha y al atenderlo. */
class PlanProgressOnScreenTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
        $this->freezeMonday();
    }

    public function test_the_clinical_record_shows_the_plan_in_course_with_each_procedure_and_how_far_it_is(): void
    {
        [$doctor, $plan] = $this->planInCourse();
        Appointment::factory()->for($doctor)->for($this->treatment('endodoncia'))->for($plan->patient)->at('2026-11-09 10:00')->confirmed()->create();

        $this->actingAs($doctor->user)
            ->get(PatientResource::getUrl('clinical', ['record' => $plan->patient]))
            ->assertOk()
            ->assertSeeInOrder([
                'Cómo va: lo hecho y lo que falta.',
                'Endodoncia · pieza 36', '1 de 2 sesiones hechas',
                'Curación con resina · pieza 36', 'Hecho',
                'Extracción simple · pieza 38', 'Por hacer',
                '1 de 3 hechos · 1 en curso',
                'Ver presupuestos',
            ]);
    }

    public function test_a_procedure_of_several_sessions_not_yet_started_says_how_many_it_takes(): void
    {
        [$doctor, $plan] = $this->planInCourse();
        $plan->items()->create(['treatment_id' => $this->treatment('endodoncia')->id, 'description' => 'Endodoncia', 'tooth' => '46', 'unit_price' => 600, 'sessions_planned' => 3, 'sort_order' => 4]);
        Appointment::factory()->for($doctor)->for($this->treatment('endodoncia'))->for($plan->patient)->at('2026-11-09 10:00')->confirmed()->create();

        // "Sesión 1 de 3" se leía como una sesión hecha: de la que no empezó se dice que falta y cuántas son.
        $this->actingAs($doctor->user)
            ->get(PatientResource::getUrl('clinical', ['record' => $plan->patient]))
            ->assertOk()
            ->assertSeeInOrder(['Endodoncia · pieza 46', 'Por hacer · 3 sesiones', '1 de 4 hechos · 1 en curso'])
            ->assertDontSee('Sesión 1 de 3');
    }

    public function test_a_cancelled_procedure_does_not_count_in_how_the_plan_is_going(): void
    {
        [$doctor, $plan] = $this->planInCourse();
        $plan->items()->create(['treatment_id' => $this->treatment('limpieza-dental')->id, 'description' => 'Limpieza dental', 'unit_price' => 80, 'status' => PlanItemStatus::Cancelled, 'sort_order' => 4]);
        Appointment::factory()->for($doctor)->for($this->treatment('endodoncia'))->for($plan->patient)->at('2026-11-09 10:00')->confirmed()->create();

        // Lo que se canceló ya no es parte del plan: no entra en la cuenta de lo hecho ni de lo que falta.
        $this->actingAs($doctor->user)
            ->get(PatientResource::getUrl('clinical', ['record' => $plan->patient]))
            ->assertOk()
            ->assertSee('1 de 3 hechos · 1 en curso')
            ->assertDontSee('1 de 4 hechos');
    }

    public function test_the_clinical_record_has_no_plan_card_when_no_plan_is_in_force(): void
    {
        [$doctor, $plan] = $this->planInCourse();
        Appointment::factory()->for($doctor)->for($this->treatment('endodoncia'))->for($plan->patient)->at('2026-11-09 10:00')->confirmed()->create();
        $plan->update(['status' => TreatmentPlanStatus::Completed]);

        $this->actingAs($doctor->user)
            ->get(PatientResource::getUrl('clinical', ['record' => $plan->patient]))
            ->assertOk()
            ->assertDontSee('Cómo va: lo hecho y lo que falta.');
    }

    public function test_the_patient_page_says_how_the_plan_is_going_and_leads_to_it(): void
    {
        [$doctor, $plan] = $this->planInCourse();
        Appointment::factory()->for($doctor)->for($this->treatment('endodoncia'))->for($plan->patient)->at('2026-11-09 10:00')->confirmed()->create();
        $reception = User::factory()->reception()->create();
        $this->actingAs($reception);

        $chip = collect(PatientSummary::chips($plan->patient, $reception))->firstWhere('icon', 'heroicon-m-clipboard-document-list');

        $this->assertSame('Plan: 1 de 3 hechos · 1 en curso', $chip['label']);
        $this->assertSame('gray', $chip['color']);
        $this->assertSame(TreatmentPlanResource::getUrl('view', ['record' => $plan]), $chip['url']);
    }

    public function test_the_patient_page_warns_when_the_plan_has_work_left_and_no_visit_ahead(): void
    {
        [, $plan] = $this->planInCourse();
        $reception = User::factory()->reception()->create();
        $this->actingAs($reception);

        $chip = collect(PatientSummary::chips($plan->patient, $reception))->firstWhere('icon', 'heroicon-m-clipboard-document-list');

        $this->assertSame('Plan: 1 de 3 hechos · 1 en curso · falta agendar', $chip['label']);
        $this->assertSame('warning', $chip['color']);
        $this->assertSame(AppointmentResource::newUrl(['patient' => $plan->patient_id]), $chip['url']);
    }

    public function test_the_visit_warns_when_what_was_done_apart_is_also_waiting_in_the_plan(): void
    {
        $filling = $this->treatment('curacion-con-resina');
        $doctor = $this->doctorOffering($filling);
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'status' => TreatmentPlanStatus::Accepted]);
        $in36 = $plan->items()->create(['treatment_id' => $filling->id, 'description' => 'Curación con resina', 'tooth' => '36', 'unit_price' => 150]);
        $plan->items()->create(['treatment_id' => $filling->id, 'description' => 'Curación con resina', 'tooth' => '46', 'unit_price' => 150]);
        // La cita no se agendó como sesión del plan y hay dos piezas: no se sabe cuál es.
        $visit = Appointment::factory()->for($doctor)->for($filling)->for($patient)->at('2026-11-02 10:00')->confirmed()->create();
        $consultation = app(ConsultationService::class)->startFromAppointment($visit, $doctor->user);
        $this->actingAs($doctor->user);

        Livewire::test(EditConsultation::class, ['record' => $consultation->getRouteKey()])
            ->assertSee(['Lo marcaste aparte y también está en su plan', 'Curación con resina · pieza 36 y Curación con resina · pieza 46'])
            // Al marcar la del plan, lo de aparte se quita solo y ya no hay nada que avisar.
            ->fillForm(['planItems' => [$in36->id]])
            ->assertDontSee('Lo marcaste aparte y también está en su plan');
    }

    public function test_the_visit_does_not_warn_about_work_that_is_not_in_the_plan(): void
    {
        $cleaning = $this->treatment('limpieza-dental');
        $doctor = $this->doctorOffering($cleaning);
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'status' => TreatmentPlanStatus::Accepted]);
        $plan->items()->create(['treatment_id' => $this->treatment('curacion-con-resina')->id, 'description' => 'Curación con resina', 'tooth' => '36', 'unit_price' => 150]);
        $visit = Appointment::factory()->for($doctor)->for($cleaning)->for($patient)->at('2026-11-02 10:00')->confirmed()->create();
        $consultation = app(ConsultationService::class)->startFromAppointment($visit, $doctor->user);
        $this->actingAs($doctor->user);

        Livewire::test(EditConsultation::class, ['record' => $consultation->getRouteKey()])
            ->assertDontSee('Lo marcaste aparte y también está en su plan');
    }

    /**
     * Un plan en tratamiento: la endodoncia va por la mitad, la curación está hecha y la extracción espera.
     *
     * @return array{0: Doctor, 1: TreatmentPlan}
     */
    private function planInCourse(): array
    {
        $doctor = $this->doctorOffering($this->treatment('endodoncia'));
        $plan = TreatmentPlan::create(['patient_id' => Patient::factory()->create()->id, 'doctor_id' => $doctor->id, 'status' => TreatmentPlanStatus::InProgress, 'accepted_at' => now()->subWeek()]);
        $plan->items()->create(['treatment_id' => $this->treatment('endodoncia')->id, 'description' => 'Endodoncia', 'tooth' => '36', 'unit_price' => 600, 'sessions_planned' => 2, 'sessions_done' => 1, 'status' => PlanItemStatus::InProgress, 'sort_order' => 1]);
        $plan->items()->create(['treatment_id' => $this->treatment('curacion-con-resina')->id, 'description' => 'Curación con resina', 'tooth' => '36', 'unit_price' => 150, 'sessions_done' => 1, 'status' => PlanItemStatus::Done, 'sort_order' => 2]);
        $plan->items()->create(['treatment_id' => $this->treatment('extraccion-simple')->id, 'description' => 'Extracción simple', 'tooth' => '38', 'unit_price' => 120, 'sort_order' => 3]);

        return [$doctor, $plan];
    }

    private function treatment(string $slug): Treatment
    {
        return Treatment::where('slug', $slug)->firstOrFail();
    }
}
