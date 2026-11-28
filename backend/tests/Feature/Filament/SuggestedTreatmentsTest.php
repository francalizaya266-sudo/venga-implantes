<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Treatments\Pages\ManageTreatments;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Catalog\TreatmentCatalog;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuggestedTreatmentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_admin_adds_the_suggested_treatments_the_clinic_offers(): void
    {
        $doctor = Doctor::factory()->create();
        Treatment::factory()->create(['name' => 'Limpieza dental', 'slug' => 'limpieza-dental']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageTreatments::class)
            ->assertActionVisible('suggested')
            ->mountAction('suggested')
            ->assertMountedActionModalSee(['Urgencia dental', 'Corona dental', '60 min. Funda de porcelana o zirconio'])
            // El que ya tiene no se ofrece de nuevo.
            ->assertMountedActionModalDontSee('Quita el sarro')
            ->callMountedAction()
            ->assertHasActionErrors(['treatments' => 'Marca al menos un tratamiento.'])
            ->fillForm(['treatments' => ['urgencia-dental', 'corona-dental']])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Se agregaron 2 tratamientos.')
            ->assertCanSeeTableRecords(Treatment::whereIn('slug', ['urgencia-dental', 'corona-dental'])->get());

        $crown = Treatment::where('slug', 'corona-dental')->sole();
        $this->assertNull($crown->price_from);
        $this->assertFalse($crown->is_public);
        $this->assertSame([$doctor->id], $crown->doctors()->pluck('doctors.id')->all());
        $this->assertSame(3, Treatment::count());
    }

    public function test_the_list_is_only_for_the_admin_and_goes_away_when_nothing_is_left_to_add(): void
    {
        $this->actingAs(User::factory()->doctor()->create());

        Livewire::test(ManageTreatments::class)->assertActionHidden('suggested');

        $catalog = app(TreatmentCatalog::class);
        $catalog->add(array_keys($catalog->missing()));
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageTreatments::class)->assertActionHidden('suggested');
    }

    public function test_a_treatment_named_like_one_of_the_catalog_gets_its_own_suggested_texts(): void
    {
        // Por su tipo ("Otro") no habría nada que sugerir: el texto sale de lo que el catálogo trae con ese nombre.
        $crown = Treatment::factory()->create(['name' => 'Corona dental', 'before_instructions' => null, 'aftercare' => null]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageTreatments::class)
            ->mountAction(TestAction::make('edit')->table($crown))
            ->assertMountedActionModalSee('Usar texto sugerido')
            ->callAction(TestAction::make('suggest_aftercare')->schemaComponent('aftercare'))
            ->callAction(TestAction::make('suggest_before_instructions')->schemaComponent('before_instructions'))
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $crown->refresh();
        $this->assertSame(TreatmentCatalog::find('Corona dental')['aftercare'], $crown->aftercare);
        $this->assertSame(TreatmentCatalog::find('Corona dental')['before'], $crown->before_instructions);
    }
}
