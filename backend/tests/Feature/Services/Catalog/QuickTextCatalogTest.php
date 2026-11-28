<?php

namespace Tests\Feature\Services\Catalog;

use App\Enums\SnippetField;
use App\Models\TextSnippet;
use App\Models\User;
use App\Services\Catalog\QuickTextCatalog;
use App\Services\Clinical\PrescriptionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class QuickTextCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_new_install_brings_options_for_every_part_of_the_visit(): void
    {
        $total = count(QuickTextCatalog::ORIGINAL) + count(QuickTextCatalog::ADDED);

        $this->assertSame($total, app(QuickTextCatalog::class)->install());

        $this->assertSame($total, TextSnippet::whereNull('user_id')->count());
        foreach ([SnippetField::ChiefComplaint, SnippetField::ClinicalExam, SnippetField::Diagnosis, SnippetField::Procedures, SnippetField::Indications, SnippetField::Prescription] as $field) {
            $this->assertGreaterThanOrEqual(4, TextSnippet::where('field', $field)->count(), $field->value);
        }

        // Un atajo repetido solo escribiría una de las dos opciones.
        $shortcuts = TextSnippet::whereNotNull('shortcut')->pluck('shortcut');
        $this->assertSame($shortcuts->count(), $shortcuts->unique()->count());

        // Instalar de nuevo no repite ninguna.
        $this->assertSame(0, app(QuickTextCatalog::class)->install());
        $this->assertSame($total, TextSnippet::count());
    }

    public function test_every_medication_fits_in_the_prescription_as_a_name_and_how_to_take_it(): void
    {
        app(QuickTextCatalog::class)->install();

        foreach (TextSnippet::where('field', SnippetField::Prescription)->get() as $snippet) {
            $item = app(PrescriptionService::class)->fromSnippet($snippet);

            $this->assertStringStartsWith(mb_substr($snippet->label, 0, 8), $item['medication'], $snippet->label);
            $this->assertNotEmpty($item['instructions'], $snippet->label);
            $this->assertLessThanOrEqual(255, mb_strlen($item['instructions']), $snippet->label);
        }
    }

    public function test_a_clinic_already_working_gets_only_the_newer_options_and_keeps_its_own(): void
    {
        $doctor = User::factory()->doctor()->create();
        // Lo que el consultorio ya tiene: una opción con el mismo nombre que una nueva, y dos atajos en uso.
        $theirs = TextSnippet::create(['field' => SnippetField::Indications, 'label' => 'Higiene oral', 'body' => 'Como lo decimos aquí.']);
        TextSnippet::create(['field' => SnippetField::Procedures, 'label' => 'Evaluación de la casa', 'body' => '...', 'shortcut' => 'eval']);
        TextSnippet::create(['user_id' => $doctor->id, 'field' => SnippetField::Prescription, 'label' => 'Mi amoxicilina', 'body' => '...', 'shortcut' => 'amoxi']);

        $added = app(QuickTextCatalog::class)->install(onlyAdded: true);

        $this->assertSame(count(QuickTextCatalog::ADDED) - 1, $added);
        $this->assertSame('Como lo decimos aquí.', $theirs->refresh()->body);
        $this->assertSame(1, TextSnippet::where('label', 'Higiene oral')->count());
        // Una de las de siempre que el consultorio borró no vuelve.
        $this->assertFalse(TextSnippet::where('label', 'Profilaxis completa')->exists());
        // El atajo que ya usa otra opción del equipo no se repite; el personal de un doctor no estorba.
        $this->assertNull(TextSnippet::where('label', 'Evaluación')->sole()->shortcut);
        $this->assertSame('amoxi', TextSnippet::whereNull('user_id')->where('label', 'Amoxicilina 500 mg')->sole()->shortcut);
    }
}
