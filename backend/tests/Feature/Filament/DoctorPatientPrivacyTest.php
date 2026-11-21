<?php

namespace Tests\Feature\Filament;

use App\Enums\TreatmentPlanStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Filament\Resources\Patients\Schemas\TreatmentPlanForm;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/**
 * Ley 29733: un doctor solo encuentra a los pacientes que atiende, con cita o atención con él. Recepción y
 * administración encuentran a todos. Antes, el buscador de "Nueva cita" le mostraba a un doctor a cualquier paciente.
 */
class DoctorPatientPrivacyTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Doctor $torres;

    private Doctor $ramos;

    /** La atiende la Dra. Torres. */
    private Patient $rosaQuispe;

    /** La atiende el Dr. Ramos. */
    private Patient $rosaMamani;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->torres = Doctor::factory()->withSchedule()->create(['name' => 'Dra. Ana Torres']);
        $this->ramos = Doctor::factory()->withSchedule()->create(['name' => 'Dr. Luis Ramos']);
        $this->rosaQuispe = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']);
        $this->rosaMamani = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Mamani']);
        Appointment::factory()->for($this->rosaQuispe)->for($this->torres)->at('2026-10-30 10:00')->create();
        Appointment::factory()->for($this->rosaMamani)->for($this->ramos)->at('2026-10-30 10:00')->create();
    }

    public function test_booking_a_doctor_finds_only_their_patients_and_reception_finds_everyone(): void
    {
        $this->actingAs($this->torres->user);
        $this->assertSame([$this->rosaQuispe->id], $this->newAppointmentSearch('Rosa'));

        // Quien vino solo a una atención con ella también es su paciente.
        $rosaFlores = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Flores']);
        Consultation::factory()->for($rosaFlores)->for($this->torres)->create();
        $this->assertEqualsCanonicalizing([$this->rosaQuispe->id, $rosaFlores->id], $this->newAppointmentSearch('Rosa'));

        $this->actingAs(User::factory()->reception()->create());
        $this->assertEqualsCanonicalizing([$this->rosaQuispe->id, $this->rosaMamani->id, $rosaFlores->id], $this->newAppointmentSearch('Rosa'));
    }

    public function test_a_doctor_does_not_see_the_plan_sessions_of_a_patient_they_do_not_treat(): void
    {
        $plan = TreatmentPlan::create(['patient_id' => $this->rosaMamani->id, 'doctor_id' => $this->ramos->id, 'status' => TreatmentPlanStatus::Accepted, 'accepted_at' => now()->subWeek()]);
        $plan->items()->create(['description' => 'Corona', 'tooth' => '46', 'unit_price' => 800, 'accepted_at' => now()->subWeek()]);

        $this->actingAs($this->ramos->user);
        $this->assertSame(['Corona · pieza 46'], array_values(AppointmentForm::planItemOptions($this->rosaMamani->id)));

        $this->actingAs($this->torres->user);
        $this->assertSame([], AppointmentForm::planItemOptions($this->rosaMamani->id));
    }

    public function test_choosing_who_shares_a_plan_a_doctor_finds_only_their_patients(): void
    {
        $patientJuan = Patient::factory()->create(['first_name' => 'Juan', 'last_name' => 'Quispe']);
        Appointment::factory()->for($patientJuan)->for($this->torres)->at('2026-10-29 10:00')->create();

        $this->actingAs($this->torres->user);
        $this->assertSame([$this->rosaQuispe->id], array_keys(TreatmentPlanForm::companionSelect($patientJuan)->getSearchResults('Rosa')));
    }

    /** @return list<int> */
    private function newAppointmentSearch(string $search): array
    {
        $found = [];

        Livewire::test(Dashboard::class)
            ->mountAction('newAppointment')
            ->assertFormFieldExists('patient_id', 'mountedActionSchema0', function (Select $field) use ($search, &$found): bool {
                $found = array_keys($field->getSearchResults($search));

                return true;
            });

        return $found;
    }
}
