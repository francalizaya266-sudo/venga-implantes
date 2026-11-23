<?php

namespace Tests\Feature\Filament;

use App\Enums\Dentition;
use App\Enums\ToothState;
use App\Livewire\OdontogramChart;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Odontogram;
use App\Models\OdontogramCondition;
use App\Services\Clinical\OdontogramService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OdontogramChartTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_the_tooth_card_names_the_tooth_and_groups_the_faces_of_a_finding(): void
    {
        [$doctor, $consultation] = $this->consultation();
        $this->actingAs($doctor->user);

        $state = Livewire::test(OdontogramChart::class, ['record' => $consultation, 'editable' => true])
            ->call('mark', 36, 'O', 'caries', 'bad', 'CD')
            ->call('mark', 36, 'M', 'caries', 'bad', 'CD')
            ->call('mark', 14, null, 'protesis_fija', 'good', null, 16)
            ->viewData('state');

        $this->assertSame('Primer molar inferior izquierdo', $state['teeth'][36]['name']);
        $this->assertTrue($state['teeth'][36]['posterior']);
        $this->assertFalse($state['teeth'][11]['posterior']);
        $this->assertSame([], $state['teeth'][11]['entries']);

        [$caries] = $state['teeth'][36]['entries'];
        $this->assertCount(2, $caries['ids']);
        $this->assertSame(['Caries (CD · dentina)', 'mesial y oclusal', 'bad', true], [$caries['name'], $caries['where'], $caries['state'], $caries['treatable']]);

        // El puente aparece en cada pieza que cubre.
        foreach ([14, 15, 16] as $tooth) {
            $this->assertSame('de la 14 a la 16', $state['teeth'][$tooth]['entries'][0]['where']);
        }
    }

    public function test_a_finding_on_several_faces_is_treated_or_removed_at_once(): void
    {
        [$doctor, $consultation] = $this->consultation();
        $this->actingAs($doctor->user);

        $chart = Livewire::test(OdontogramChart::class, ['record' => $consultation, 'editable' => true])
            ->call('mark', 36, 'O', 'caries', 'bad', 'CD')
            ->call('mark', 36, 'M', 'caries', 'bad', 'CD');

        // Una sigla por recuadro aunque esté en dos caras.
        $this->assertSame(['CD'], array_column($chart->viewData('teeth')[36]['labels'], 'text'));

        $ids = $consultation->odontogram->entries()->pluck('id')->all();
        $chart->call('markTreated', $ids, 'R');

        $this->assertSame(
            [['restauracion', 'M', ToothState::Good, 'R'], ['restauracion', 'O', ToothState::Good, 'R']],
            $consultation->odontogram->entries()->with('condition')->orderBy('surface')->get()->map(fn ($e) => [$e->condition->code, $e->surface, $e->state, $e->abbreviation])->all(),
        );

        $chart->call('remove', $consultation->odontogram->entries()->pluck('id')->all())
            ->assertSee('Sin hallazgos marcados.');

        $this->assertSame(0, $consultation->odontogram->entries()->count());
    }

    public function test_each_change_is_undone_one_step_at_a_time(): void
    {
        [$doctor, $consultation] = $this->consultation();
        $this->actingAs($doctor->user);
        $marks = fn (): array => $consultation->odontogram->entries()->with('condition')->orderBy('surface')->get()
            ->map(fn ($entry) => [$entry->condition->code, $entry->surface, $entry->abbreviation])->all();

        $chart = Livewire::test(OdontogramChart::class, ['record' => $consultation, 'editable' => true])
            ->assertDontSeeHtml('wire:click="undoLast"')
            ->call('mark', 36, 'O', 'caries', 'bad', 'CD')
            ->call('mark', 36, 'M', 'caries', 'bad', 'CD')
            ->assertSeeHtml('wire:click="undoLast"');
        $chart->call('markTreated', $consultation->odontogram->entries()->pluck('id')->all(), 'R')
            ->call('remove', $consultation->odontogram->entries()->pluck('id')->all());
        $this->assertSame([], $marks());

        $chart->call('undoLast')->assertNotified('Se deshizo: quitar la marca de la 36');
        $this->assertSame([['restauracion', 'M', 'R'], ['restauracion', 'O', 'R']], $marks());

        $chart->call('undoLast')->assertNotified('Se deshizo: "Tratado" en la 36');
        $this->assertSame([['caries', 'M', 'CD'], ['caries', 'O', 'CD']], $marks());

        $chart->call('undoLast')->call('undoLast');
        $this->assertSame([], $marks());

        // Ya no queda nada por deshacer: el botón se va y tocar de nuevo no hace nada.
        $chart->assertDontSeeHtml('wire:click="undoLast"')->call('undoLast');
        $this->assertSame([], $marks());
    }

    public function test_a_dentition_change_is_undone_too_and_only_by_who_edits(): void
    {
        [$doctor, $consultation] = $this->consultation();
        $this->actingAs($doctor->user);
        $consultation->odontogram->update(['dentition' => Dentition::Permanent]);

        Livewire::test(OdontogramChart::class, ['record' => $consultation->fresh(), 'editable' => true])
            ->call('mark', 36, 'O', 'caries', 'bad', 'CD')
            ->call('setDentition', Dentition::Mixed->value)
            ->call('undoLast')
            ->assertNotified('Se deshizo: pasar a mixta')
            ->assertSet('dentitionRevision', 2);
        $this->assertSame(Dentition::Permanent, $consultation->odontogram->fresh()->dentition);
        $this->assertSame([36], $consultation->odontogram->entries()->pluck('tooth')->all());

        Livewire::test(OdontogramChart::class, ['record' => $consultation])
            ->assertDontSeeHtml('wire:click="undoLast"')
            ->assertDontSee('Deshacer')
            ->call('setDentition', Dentition::Primary->value);
        $this->assertSame(Dentition::Permanent, $consultation->odontogram->fresh()->dentition);
    }

    public function test_marks_on_a_milk_tooth_go_to_the_adult_tooth_when_changing_to_adult(): void
    {
        [$doctor, $consultation] = $this->consultation();
        $this->actingAs($doctor->user);
        $consultation->odontogram->update(['dentition' => Dentition::Mixed]);
        $marks = fn (): array => $consultation->odontogram->entries()->orderBy('tooth')->orderBy('surface')->get()->map(fn ($entry) => $entry->tooth.$entry->surface)->all();

        $chart = Livewire::test(OdontogramChart::class, ['record' => $consultation->fresh(), 'editable' => true])
            ->call('mark', 63, 'M', 'caries', 'bad', 'CD')
            ->call('mark', 63, 'D', 'caries', 'bad', 'CD')
            ->call('setDentition', Dentition::Permanent->value)
            ->assertSee(['2 marcas en la pieza 63, que no existe en adulto. ¿Qué hago con ellas?', 'Pasarlas a la 23', 'Quitarlas y pasar a adulto'])
            ->call('confirmDentition', 'move')
            ->assertDontSee('Pasarlas a la 23');
        $this->assertSame(Dentition::Permanent, $consultation->odontogram->fresh()->dentition);
        $this->assertSame(['23D', '23M'], $marks());

        // Deshacer lo devuelve todo: mixta y la caries en la 63.
        $chart->call('undoLast');
        $this->assertSame(Dentition::Mixed, $consultation->odontogram->fresh()->dentition);
        $this->assertSame(['63D', '63M'], $marks());
    }

    public function test_the_filters_count_teeth_and_today_shows_only_what_changed_since_the_last_visit(): void
    {
        [$doctor, $first] = $this->consultation();
        $this->actingAs($doctor->user);
        $odontograms = app(OdontogramService::class);

        $odontograms->toggle($first->odontogram, $this->condition('caries'), ToothState::Bad, 36, 'O', abbreviation: 'CD');
        $odontograms->toggle($first->odontogram, $this->condition('ausente'), ToothState::Good, 18);

        // En el primer odontograma todo es nuevo: no se resalta nada.
        $summary = Livewire::test(OdontogramChart::class, ['record' => $first, 'editable' => true])->viewData('summary');
        $this->assertSame(['bad' => 1, 'good' => 0, 'missing' => 1, 'today' => 0], $summary);

        // La atención siguiente parte de ese odontograma; hoy se marca la 46.
        $second = Consultation::factory()->for($first->patient)->for($doctor)->create();
        $odontograms->forConsultation($second);
        $odontograms->toggle($second->odontogram, $this->condition('restauracion'), ToothState::Good, 46, 'O', abbreviation: 'R');

        $chart = Livewire::test(OdontogramChart::class, ['record' => $second->fresh(), 'editable' => true]);

        $this->assertSame(['bad' => 1, 'good' => 1, 'missing' => 1, 'today' => 1], $chart->viewData('summary'));
        $this->assertSame([46 => true, 36 => false, 18 => false], collect($chart->viewData('groups'))->mapWithKeys(fn ($g) => [$g['tooth'] => $g['new']])->sortKeysDesc()->all());
        $this->assertTrue($chart->viewData('teeth')[46]['flags']['today']);
        $chart->assertSeeHtml('data-today');
    }

    public function test_what_is_written_is_previewed_on_the_drawing(): void
    {
        [$doctor, $consultation] = $this->consultation();
        $this->actingAs($doctor->user);

        $preview = Livewire::test(OdontogramChart::class, ['record' => $consultation, 'editable' => true])
            ->set('dictation', '36 MO caries, puente 14 a 16')
            ->viewData('preview');

        $this->assertEqualsCanonicalizing([14, 15, 16, 36], $preview);
    }

    public function test_the_findings_fit_one_row_and_a_child_gets_the_sealant_before_the_implant(): void
    {
        [$doctor, $consultation] = $this->consultation();
        $this->actingAs($doctor->user);
        $odontogram = Odontogram::where('consultation_id', $consultation->id)->sole();
        $inView = fn () => Livewire::test(OdontogramChart::class, ['record' => $consultation, 'editable' => true])
            ->assertSeeHtml(['x-data="odontogramChips"', 'x-text="more ? \'Menos\' : \'Más\'"'])
            ->viewData('frequent')->take(7)->pluck('code')->all();

        app(OdontogramService::class)->changeDentition($odontogram, Dentition::Permanent);
        $this->assertSame(['caries', 'restauracion', 'extraccion_indicada', 'ausente', 'implante', 'corona', 'tratamiento_pulpar'], $inView());

        app(OdontogramService::class)->changeDentition($odontogram->fresh(), Dentition::Mixed);
        $this->assertSame(['caries', 'restauracion', 'extraccion_indicada', 'ausente', 'sellante', 'corona', 'tratamiento_pulpar'], $inView());
    }

    public function test_on_a_phone_the_drawing_opens_enlarged_by_the_teeth_of_its_longest_row(): void
    {
        [$doctor, $consultation] = $this->consultation();
        $this->actingAs($doctor->user);
        $odontogram = Odontogram::where('consultation_id', $consultation->id)->sole();

        // En el celular cada pieza mide lo mismo (panel.css): el dibujo sabe cuántas van en su fila más larga.
        app(OdontogramService::class)->changeDentition($odontogram, Dentition::Permanent);
        Livewire::test(OdontogramChart::class, ['record' => $consultation, 'editable' => true])
            ->assertSeeHtml('--odv-teeth: 16"');

        app(OdontogramService::class)->changeDentition($odontogram->fresh(), Dentition::Primary);
        Livewire::test(OdontogramChart::class, ['record' => $consultation, 'editable' => true])
            ->assertSeeHtml('--odv-teeth: 10"');
    }

    /** @return array{0: Doctor, 1: Consultation} */
    private function consultation(): array
    {
        $doctor = Doctor::factory()->create();
        $consultation = Consultation::factory()->for($doctor)->create();
        app(OdontogramService::class)->forConsultation($consultation);

        return [$doctor, $consultation->fresh()];
    }

    private function condition(string $code): OdontogramCondition
    {
        return OdontogramCondition::where('code', $code)->firstOrFail();
    }
}
