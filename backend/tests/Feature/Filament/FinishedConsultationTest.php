<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Consultations\ConsultationResource;
use App\Filament\Resources\Consultations\Pages\EditConsultation;
use App\Filament\Resources\Consultations\Pages\ViewConsultation;
use App\Models\Charge;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Supply;
use App\Models\TreatmentPlan;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** Terminar la atención y la atención terminada: lo que se ve arriba y lo que pasa por detrás al terminar. */
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

        // Si igual llega algo de esa pantalla (un segundo toque mientras carga, el guardado automático), no se hace
        // ni rebota: la pantalla pasa a la atención terminada.
        $page->callMountedAction()
            ->assertRedirect($finished);
        $this->assertSame(19.0, (float) $gloves->fresh()->stock);

        $page->call('autosave')
            ->assertRedirect($finished);
    }

    public function test_a_visit_finished_in_another_tab_leads_there_instead_of_failing(): void
    {
        $this->freezeMonday();
        $doctor = Doctor::factory()->create();
        $consultation = Consultation::factory()->for($doctor)->create(['closed_at' => null]);
        $this->actingAs($doctor->user);

        $page = Livewire::test(EditConsultation::class, ['record' => $consultation->getRouteKey()]);
        $consultation->update(['closed_at' => now()]);

        // Lo que se toque en la pestaña que quedó atrás no abre nada: la pantalla pasa a la atención terminada.
        $page->call('mountAction', 'template')
            ->assertRedirect(ConsultationResource::getUrl('view', ['record' => $consultation]))
            ->assertActionNotMounted();
    }

    public function test_the_finished_visit_offers_collecting_and_booking_once_next_to_their_explanation(): void
    {
        $this->freezeMonday();
        $admin = User::factory()->admin(isDentist: true)->create();
        $doctor = Doctor::factory()->create(['user_id' => $admin->id]);
        $consultation = Consultation::factory()->for($doctor)->create(['closed_at' => now()]);
        Charge::factory()->for($consultation->patient)->create(['consultation_id' => $consultation->id, 'description' => 'Limpieza dental', 'amount' => 120]);
        $this->actingAs($admin);

        // El día en que se terminó, "Cobrar ahora" y "Agendar" están en las franjas: arriba no se repiten.
        $page = Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
            ->assertSeeInOrder(['Cobro', 'Debe S/ 120.00', 'Cobrar ahora', 'Próxima cita', 'Agendar']);
        $this->assertSame(['Reabrir para corregir', 'Laboratorio', 'Historia clínica'], $this->headerButtons($page));

        // "Cobrar ahora" sigue abriendo el cobro.
        $page->call('mountAction', 'collect')
            ->assertActionMounted('collect')
            ->assertMountedActionModalSee('Cobrar a '.$consultation->patient->full_name);

        // Días después ya no están las franjas: cobrar y programar la cita quedan arriba.
        $this->travelTo(now()->addDays(2));
        $page = Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
            ->assertDontSee('Cobrar ahora');
        $this->assertSame(['Cobrar', 'Programar cita', 'Laboratorio', 'Historia clínica'], $this->headerButtons($page));
    }

    public function test_the_plan_goes_under_the_visit_when_the_side_is_narrow(): void
    {
        $this->freezeMonday();
        $doctor = Doctor::factory()->create();
        $consultation = Consultation::factory()->for($doctor)->create(['closed_at' => now()]);
        TreatmentPlan::create(['patient_id' => $consultation->patient_id, 'doctor_id' => $doctor->id, 'consultation_id' => $consultation->id, 'title' => 'Plan']);
        $this->actingAs($doctor->user);

        // Con tres columnas, el plan ocupa el ancho de la atención, justo debajo: no queda un hueco bajo ella. Con
        // cinco, sigue al lado de los datos.
        Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
            ->assertSeeInOrder(['Atención', 'Datos', 'Plan de tratamiento', 'Odontograma de esta atención'])
            ->assertSeeHtml('--col-span-c3xl: span 2 / span 2; --col-span-c6xl: span 1 / span 1');
    }

    /** @return list<string> */
    private function headerButtons(Testable $page): array
    {
        return collect($page->instance()->getCachedHeaderActions())
            ->filter(fn (Action $action) => $action->isVisible())
            ->map(fn (Action $action) => (string) $action->getLabel())
            ->values()
            ->all();
    }
}
