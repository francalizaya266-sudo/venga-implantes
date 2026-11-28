<?php

namespace Tests\Feature\Services\Catalog;

use App\Enums\TreatmentKind;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Services\Catalog\TreatmentCatalog;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TreatmentCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_new_install_brings_the_everyday_treatments_with_their_texts_and_without_price(): void
    {
        app(TreatmentCatalog::class)->installBase();

        $this->assertSame(count(TreatmentCatalog::BASE), Treatment::count());
        $this->assertSame(0, Treatment::whereNotNull('price_from')->count());

        $cleaning = Treatment::where('slug', 'limpieza-dental')->sole();
        $this->assertSame(45, $cleaning->duration_minutes);
        $this->assertTrue($cleaning->is_public);
        $this->assertTrue($cleaning->online_bookable);
        $this->assertStringContainsString('Ven con los dientes cepillados', $cleaning->before_instructions);
        $this->assertStringContainsString('sensibilidad', $cleaning->aftercare);
        $this->assertStringContainsString('## Cómo es la sesión', $cleaning->description);

        $evaluation = Treatment::where('is_evaluation', true)->sole();
        $this->assertSame('Evaluación o primera consulta', $evaluation->name);
        $this->assertNull($evaluation->aftercare);
    }

    public function test_installing_again_keeps_what_the_clinic_changed(): void
    {
        $catalog = app(TreatmentCatalog::class);
        $catalog->installBase();
        Treatment::where('slug', 'limpieza-dental')->sole()->update(['aftercare' => 'Nuestros cuidados.', 'duration_minutes' => 30]);

        $catalog->installBase();

        $cleaning = Treatment::where('slug', 'limpieza-dental')->sole();
        $this->assertSame('Nuestros cuidados.', $cleaning->aftercare);
        $this->assertSame(30, $cleaning->duration_minutes);
        $this->assertSame(count(TreatmentCatalog::BASE), Treatment::count());
    }

    public function test_every_text_fits_in_the_field_where_the_clinic_edits_it(): void
    {
        foreach ([...TreatmentCatalog::BASE, ...TreatmentCatalog::MORE] as $definition) {
            $this->assertLessThanOrEqual(100, mb_strlen($definition['name']), $definition['name']);
            $this->assertLessThanOrEqual(300, mb_strlen($definition['before'] ?? ''), $definition['name']);
            $this->assertLessThanOrEqual(1500, mb_strlen($definition['aftercare'] ?? ''), $definition['name']);
        }
    }

    public function test_it_offers_only_the_treatments_the_clinic_does_not_have(): void
    {
        // Con otra dirección pero el mismo nombre, apagado o borrado: el consultorio ya lo tiene.
        Treatment::factory()->create(['name' => 'Corona dental', 'slug' => 'coronas']);
        Treatment::factory()->create(['name' => 'Sellantes', 'slug' => 'sellantes', 'is_active' => false]);
        Treatment::factory()->create(['name' => 'Pulpotomía', 'slug' => 'pulpotomia'])->delete();

        $missing = app(TreatmentCatalog::class)->missing();

        $this->assertArrayNotHasKey('corona-dental', $missing);
        $this->assertArrayNotHasKey('sellantes', $missing);
        $this->assertArrayNotHasKey('pulpotomia', $missing);
        $this->assertSame('Urgencia dental', $missing['urgencia-dental']['name']);
        $this->assertCount(count(TreatmentCatalog::BASE) + count(TreatmentCatalog::MORE) - 3, $missing);
    }

    public function test_added_treatments_stay_in_the_clinic_without_price_and_with_every_active_doctor(): void
    {
        $existing = Treatment::factory()->create(['name' => 'Limpieza dental', 'slug' => 'limpieza-dental', 'sort_order' => 7]);
        $active = Doctor::factory()->create();
        Doctor::factory()->create(['is_active' => false]);

        $added = app(TreatmentCatalog::class)->add(['urgencia-dental', 'corona-sobre-implante', 'limpieza-dental', 'no-existe']);

        $this->assertSame(['Urgencia dental', 'Corona sobre implante'], $added->pluck('name')->all());
        $this->assertSame(3, Treatment::count());

        $crown = Treatment::where('slug', 'corona-sobre-implante')->sole();
        $this->assertFalse($crown->is_public);
        $this->assertFalse($crown->online_bookable);
        $this->assertTrue($crown->is_active);
        $this->assertNull($crown->price_from);
        $this->assertSame(60, $crown->duration_minutes);
        $this->assertSame(9, $crown->sort_order);
        // No es una cirugía de implante: ni sus riesgos ni sus controles, aunque el nombre lo diga.
        $this->assertSame(TreatmentKind::Other, TreatmentKind::for($crown));
        $this->assertSame(12, $crown->checkupMonths());
        $this->assertNotEmpty($crown->before_instructions);
        $this->assertNotEmpty($crown->aftercare);
        $this->assertSame([$active->id], $crown->doctors()->pluck('doctors.id')->all());
        $this->assertSame(7, $existing->refresh()->sort_order);
    }

    public function test_filling_the_missing_texts_never_replaces_what_the_clinic_wrote(): void
    {
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza', 'slug' => 'limpieza-dental', 'before_instructions' => null, 'aftercare' => 'Nuestros cuidados.', 'description' => '']);
        $own = Treatment::factory()->create(['name' => 'Férula de descarga', 'slug' => 'ferula-de-descarga']);

        $filled = app(TreatmentCatalog::class)->fillEmptyTexts();

        $cleaning->refresh();
        $this->assertSame(2, $filled);
        $this->assertStringContainsString('Ven con los dientes cepillados', $cleaning->before_instructions);
        $this->assertSame('Nuestros cuidados.', $cleaning->aftercare);
        $this->assertStringContainsString('## Cómo es la sesión', $cleaning->description);
        $this->assertSame('Limpieza', $cleaning->name);
        $this->assertNull($own->refresh()->before_instructions);
        $this->assertNull($own->aftercare);

        $this->assertSame(0, app(TreatmentCatalog::class)->fillEmptyTexts());
    }

    public function test_it_finds_what_it_brings_for_a_treatment_by_its_name(): void
    {
        $this->assertSame('Corona dental', TreatmentCatalog::find('corona dental')['name']);
        $this->assertNull(TreatmentCatalog::find('Férula de descarga'));
        $this->assertNull(TreatmentCatalog::find(null));
    }
}
