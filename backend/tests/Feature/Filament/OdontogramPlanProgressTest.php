<?php

namespace Tests\Feature\Filament;

use App\Enums\ToothState;
use App\Enums\TreatmentPlanStatus;
use App\Livewire\OdontogramChart;
use App\Models\Consultation;
use App\Models\OdontogramCondition;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Services\Clinical\ConsultationService;
use App\Services\Clinical\OdontogramService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** Lo que el doctor marca en el odontograma le dice al plan qué se trabajó hoy. */
class OdontogramPlanProgressTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
        $this->freezeMonday();
    }

    public function test_a_restoration_painted_in_blue_over_the_planned_caries_treats_it_and_marks_the_plan_worked(): void
    {
        $consultation = $this->consultation();
        $this->finding($consultation, 'caries', ToothState::Bad, 36, 'O');
        $filling = $this->planned($consultation, ['treatment_id' => $this->treatment('curacion-con-resina')->id, 'description' => 'Curación con resina', 'tooth' => '36', 'surfaces' => 'O']);

        $this->chart($consultation)
            ->call('mark', 36, 'O', 'restauracion', 'good', 'R')
            ->assertDispatched('odontogram-plan-items-worked', items: [$filling->id]);

        $this->assertSame([['restauracion', 'O', ToothState::Good, 'R']], $this->marks($consultation));
    }

    public function test_a_restoration_on_one_of_two_carious_faces_does_not_mark_the_plan_yet(): void
    {
        $consultation = $this->consultation();
        $this->finding($consultation, 'caries', ToothState::Bad, 36, 'O');
        $this->finding($consultation, 'caries', ToothState::Bad, 36, 'M');
        $this->planned($consultation, ['treatment_id' => $this->treatment('curacion-con-resina')->id, 'description' => 'Curación con resina', 'tooth' => '36', 'surfaces' => 'MO']);

        $this->chart($consultation)
            ->call('mark', 36, 'O', 'restauracion', 'good', 'R')
            ->assertNotDispatched('odontogram-plan-items-worked');

        $this->assertSame([['caries', 'M', ToothState::Bad, null], ['restauracion', 'O', ToothState::Good, 'R']], $this->marks($consultation));
    }

    public function test_a_tooth_marked_absent_over_the_indicated_extraction_marks_the_plan_worked(): void
    {
        $consultation = $this->consultation();
        $this->finding($consultation, 'extraccion_indicada', ToothState::Bad, 48);
        $extraction = $this->planned($consultation, ['treatment_id' => $this->treatment('extraccion-simple')->id, 'description' => 'Extracción simple', 'tooth' => '48']);

        $this->chart($consultation)
            ->call('mark', 48, null, 'ausente', 'good')
            ->assertDispatched('odontogram-plan-items-worked', items: [$extraction->id]);

        $this->assertSame([['ausente', null, ToothState::Good, null]], $this->marks($consultation));
    }

    public function test_an_implant_painted_on_the_missing_tooth_marks_the_planned_implant_worked(): void
    {
        $consultation = $this->consultation();
        $this->finding($consultation, 'ausente', ToothState::Good, 46);
        $implant = $this->planned($consultation, ['treatment_id' => $this->treatment('implante')->id, 'description' => 'Implante', 'tooth' => '46', 'sessions_planned' => 3]);

        $this->chart($consultation)
            ->call('mark', 46, null, 'implante', 'good')
            ->assertDispatched('odontogram-plan-items-worked', items: [$implant->id]);
    }

    public function test_a_root_canal_painted_in_blue_marks_the_planned_one_worked_while_the_caries_waits_for_its_filling(): void
    {
        $consultation = $this->consultation();
        $this->finding($consultation, 'caries', ToothState::Bad, 46, 'O', 'CDP');
        $rootCanal = $this->planned($consultation, ['treatment_id' => $this->treatment('endodoncia')->id, 'description' => 'Endodoncia', 'tooth' => '46', 'sessions_planned' => 2]);

        $this->chart($consultation)
            ->call('mark', 46, null, 'tratamiento_pulpar', 'good')
            ->assertDispatched('odontogram-plan-items-worked', items: [$rootCanal->id]);
    }

    public function test_a_finding_marked_treated_marks_the_procedure_the_plan_has_under_another_catalog_name(): void
    {
        $consultation = $this->consultation();
        $caries = $this->finding($consultation, 'caries', ToothState::Bad, 36, 'O');
        $composite = Treatment::factory()->create(['name' => 'Resina compuesta', 'slug' => 'resina-compuesta']);
        $filling = $this->planned($consultation, ['treatment_id' => $composite->id, 'description' => 'Resina compuesta', 'tooth' => '36']);

        $this->chart($consultation)
            ->call('markTreated', $caries->id, 'R')
            ->assertDispatched('odontogram-plan-items-worked', items: [$filling->id]);
    }

    public function test_a_finding_marked_treated_marks_the_procedure_written_by_hand_in_the_plan(): void
    {
        $consultation = $this->consultation();
        $caries = $this->finding($consultation, 'caries', ToothState::Bad, 36, 'O');
        $filling = $this->planned($consultation, ['description' => 'Obturación con ionómero', 'tooth' => ' 36']);

        $this->chart($consultation)
            ->call('markTreated', $caries->id, 'R')
            ->assertDispatched('odontogram-plan-items-worked', items: [$filling->id]);
    }

    public function test_two_procedures_of_the_same_kind_on_the_tooth_are_left_for_the_doctor_to_mark(): void
    {
        $consultation = $this->consultation();
        $caries = $this->finding($consultation, 'caries', ToothState::Bad, 36, 'O');
        $this->planned($consultation, ['description' => 'Obturación con ionómero', 'tooth' => '36']);
        $this->planned($consultation, ['description' => 'Incrustación de resina', 'tooth' => '36']);

        $this->chart($consultation)
            ->call('markTreated', $caries->id, 'R')
            ->assertNotDispatched('odontogram-plan-items-worked');
    }

    public function test_a_control_visit_planned_on_the_tooth_is_not_taken_for_the_work_itself(): void
    {
        $consultation = $this->consultation();
        $extraction = $this->finding($consultation, 'extraccion_indicada', ToothState::Bad, 48);
        $this->planned($consultation, ['description' => 'Control post extracción', 'tooth' => '48']);

        $this->chart($consultation)
            ->call('markTreated', $extraction->id)
            ->assertNotDispatched('odontogram-plan-items-worked');
    }

    public function test_the_same_procedure_planned_on_another_tooth_is_not_marked(): void
    {
        $consultation = $this->consultation();
        $caries = $this->finding($consultation, 'caries', ToothState::Bad, 36, 'O');
        $this->planned($consultation, ['treatment_id' => $this->treatment('curacion-con-resina')->id, 'description' => 'Curación con resina', 'tooth' => '46']);

        $this->chart($consultation)
            ->call('markTreated', $caries->id, 'R')
            ->assertNotDispatched('odontogram-plan-items-worked');
    }

    public function test_a_dictated_restoration_over_the_planned_caries_treats_it_and_marks_the_plan_worked(): void
    {
        $consultation = $this->consultation();
        $this->finding($consultation, 'caries', ToothState::Bad, 36, 'O');
        $filling = $this->planned($consultation, ['treatment_id' => $this->treatment('curacion-con-resina')->id, 'description' => 'Curación con resina', 'tooth' => '36', 'surfaces' => 'O']);

        $this->chart($consultation)
            ->call('addDictated', '36 O restauración')
            ->assertDispatched('odontogram-plan-items-worked', items: [$filling->id]);

        $this->assertSame([['restauracion', 'O', ToothState::Good, null]], $this->marks($consultation));
    }

    public function test_an_old_restoration_recorded_on_a_tooth_without_plan_tells_the_plan_nothing(): void
    {
        $consultation = $this->consultation();
        $this->planned($consultation, ['treatment_id' => $this->treatment('curacion-con-resina')->id, 'description' => 'Curación con resina', 'tooth' => '36']);

        $this->chart($consultation)
            ->call('mark', 26, 'O', 'restauracion', 'good', 'AM')
            ->assertNotDispatched('odontogram-plan-items-worked');
    }

    /** La atención de hoy de un paciente, con su odontograma. */
    private function consultation(): Consultation
    {
        $doctor = $this->doctorOffering(Treatment::factory()->create());

        return app(ConsultationService::class)->startWithoutAppointment(Patient::factory()->create(), $doctor->user);
    }

    private function treatment(string $slug): Treatment
    {
        return Treatment::where('slug', $slug)->firstOrFail();
    }

    /** Un hallazgo que ya estaba marcado en el odontograma. */
    private function finding(Consultation $consultation, string $code, ToothState $state, int $tooth, ?string $surface = null, ?string $abbreviation = null): mixed
    {
        return app(OdontogramService::class)->toggle($consultation->odontogram, OdontogramCondition::where('code', $code)->firstOrFail(), $state, $tooth, $surface, abbreviation: $abbreviation);
    }

    /**
     * Un procedimiento de un plan que el paciente ya aceptó.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function planned(Consultation $consultation, array $attributes): TreatmentPlanItem
    {
        $plan = TreatmentPlan::create(['patient_id' => $consultation->patient_id, 'doctor_id' => $consultation->doctor_id, 'status' => TreatmentPlanStatus::Accepted]);

        return $plan->items()->create(['unit_price' => 150, ...$attributes]);
    }

    /** El odontograma de la atención, como lo usa su doctor. */
    private function chart(Consultation $consultation): Testable
    {
        $this->actingAs($consultation->doctor->user);

        return Livewire::test(OdontogramChart::class, ['record' => $consultation, 'editable' => true]);
    }

    /** @return list<array{0: string, 1: string|null, 2: ToothState, 3: string|null}> Las marcas del odontograma: hallazgo, cara, color y sigla. */
    private function marks(Consultation $consultation): array
    {
        return $consultation->odontogram->entries()->with('condition')->orderBy('surface')->get()
            ->map(fn ($entry) => [$entry->condition->code, $entry->surface, $entry->state, $entry->abbreviation])
            ->all();
    }
}
