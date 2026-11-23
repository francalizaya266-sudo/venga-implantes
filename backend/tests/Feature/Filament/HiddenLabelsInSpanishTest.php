<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Consultations\Pages\EditConsultation;
use App\Filament\Resources\Consultations\Pages\ViewConsultation;
use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Models\Appointment;
use App\Models\Icd10Code;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Clinical\ConsultationService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** Lo que no se ve en la pantalla pero lee el lector de pantalla (las etiquetas ocultas) también va en español. */
class HiddenLabelsInSpanishTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    /** Lo que se leía antes: el nombre interno del campo, en inglés. */
    private const ENGLISH = ['Schedule', 'Schedules', 'Access', 'Diagnoses', 'Code', 'Files', 'Supplies', 'Body', 'Implants', 'Account totals', 'No plans'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
        $this->freezeMonday();
    }

    public function test_the_doctor_page_my_profile_and_the_consultation_name_their_hidden_fields_in_spanish(): void
    {
        $treatment = Treatment::factory()->create();
        $doctor = $this->doctorOffering($treatment);
        $appointment = Appointment::factory()->for($doctor)->for($treatment)->for(Patient::factory())->at('2026-11-02 10:00')->confirmed()->create();
        $consultation = app(ConsultationService::class)->startFromAppointment($appointment, $doctor->user);
        $consultation->diagnoses()->create(['icd10_code_id' => Icd10Code::where('code', 'K02.1')->value('id'), 'tooth' => '36']);

        $this->actingAs(User::factory()->admin()->create());
        $this->assertHiddenLabels(Livewire::test(EditDoctor::class, ['record' => $doctor->getKey()])->html(), ['Acceso al panel', 'Horario de atención']);

        $this->actingAs($doctor->user);
        $this->assertHiddenLabels(Livewire::test(EditProfile::class)->html(), ['Tu horario de atención']);
        $this->assertHiddenLabels(Livewire::test(EditConsultation::class, ['record' => $consultation->getRouteKey()])->html(), ['Diagnósticos']);

        app(ConsultationService::class)->close($consultation, $doctor->user);
        $this->assertHiddenLabels(Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])->html(), ['Diagnóstico']);
    }

    /**
     * Entre las etiquetas ocultas de la pantalla están las esperadas, y ninguna quedó en inglés.
     *
     * @param  list<string>  $expected
     */
    private function assertHiddenLabels(string $html, array $expected): void
    {
        preg_match_all('/class="[^"]*\bfi-sr-only\b[^"]*"[^>]*>\s*([^<]+?)\s*</', $html, $matches);
        $labels = array_map(fn (string $label) => html_entity_decode($label, ENT_QUOTES), $matches[1]);

        foreach ($expected as $label) {
            $this->assertContains($label, $labels);
        }

        $this->assertSame([], array_values(array_intersect($labels, self::ENGLISH)));
    }
}
