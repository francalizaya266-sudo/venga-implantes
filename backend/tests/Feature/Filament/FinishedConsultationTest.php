<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Consultations\ConsultationResource;
use App\Filament\Resources\Consultations\Pages\EditConsultation;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Supply;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** Terminar la atención: lo que pasa por detrás mientras se abre la atención terminada. */
class FinishedConsultationTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    public function test_finishing_keeps_the_window_as_it_is_until_the_finished_visit_opens(): void
    {
        $this->freezeMonday();
        $gloves = Supply::factory()->create(['name' => 'Guantes de nitrilo', 'stock' => 20]);
        $doctor = Doctor::factory()->create();
        $consultation = Consultation::factory()->for($doctor)->create(['closed_at' => null]);
        $finished = ConsultationResource::getUrl('view', ['record' => $consultation]);
        $this->actingAs($doctor->user);

        // Antes la ventana se cerraba al terminar, sus listas de materiales se vaciaban y volvían a pedir sus
        // nombres a una atención ya cerrada: el servidor los rechazaba y, con internet lento, salía un cuadro de error.
        $page = Livewire::test(EditConsultation::class, ['record' => $consultation->getRouteKey()])
            ->mountAction('close')
            ->setActionData(['supplies_used' => [['supply_id' => $gloves->id, 'quantity' => 1, 'batch_id' => null]]])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertRedirect($finished)
            ->assertActionMounted('close');

        $this->assertNotNull($consultation->fresh()->closed_at);
        $this->assertSame(19.0, (float) $gloves->fresh()->stock);

        // Si igual llega algo de esa pantalla (un segundo toque mientras carga, otra pestaña), pasa a la atención
        // terminada en vez de rebotar.
        $page->call('autosave')
            ->assertStatus(302)
            ->assertHeader('Location', $finished);
    }

    public function test_a_visit_finished_in_another_tab_leads_there_instead_of_failing(): void
    {
        $this->freezeMonday();
        $doctor = Doctor::factory()->create();
        $consultation = Consultation::factory()->for($doctor)->create(['closed_at' => null]);
        $this->actingAs($doctor->user);

        $page = Livewire::test(EditConsultation::class, ['record' => $consultation->getRouteKey()]);
        $consultation->update(['closed_at' => now()]);

        $page->call('mountAction', 'template')
            ->assertStatus(302)
            ->assertHeader('Location', ConsultationResource::getUrl('view', ['record' => $consultation]));
    }
}
