<?php

namespace Tests\Feature\Services\Catalog;

use App\Enums\MessageTemplateKey;
use App\Enums\SnippetField;
use App\Models\MessageTemplate;
use App\Models\TextSnippet;
use App\Models\Treatment;
use App\Services\Catalog\QuickTextCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReadyContentForWorkingClinicsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Lo que el recordatorio decía antes: sin el tratamiento ni la dirección. */
    private const OLD_REMINDER = "Hola {nombre}, te recordamos tu cita {cuando} a las {hora} con {doctor} en {consultorio}.\n{indicaciones}\n\nConfirma tu asistencia aquí:\n{enlace}";

    public function test_a_clinic_already_working_gets_the_new_content_without_losing_what_it_wrote(): void
    {
        // El consultorio dejó el recordatorio como venía y escribió a su modo el aviso de cuidados.
        $this->template(MessageTemplateKey::Reminder, self::OLD_REMINDER);
        $this->template(MessageTemplateKey::Aftercare, 'Hola {nombre}, cuídate mucho: {cuidados}');
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'slug' => 'limpieza-dental', 'price_from' => 90, 'duration_minutes' => 40, 'before_instructions' => null, 'aftercare' => 'Nuestros cuidados.', 'description' => null]);
        $theirs = TextSnippet::create(['field' => SnippetField::Procedures, 'label' => 'Profilaxis de la casa', 'body' => 'Como la hacemos aquí.', 'shortcut' => 'limp']);

        $this->migration()->up();

        $this->assertSame(MessageTemplateKey::Reminder->defaultBody(), MessageTemplate::where('key', MessageTemplateKey::Reminder)->value('body'));
        $this->assertSame('Hola {nombre}, cuídate mucho: {cuidados}', MessageTemplate::where('key', MessageTemplateKey::Aftercare)->value('body'));

        $cleaning->refresh();
        $this->assertStringContainsString('Ven con los dientes cepillados', $cleaning->before_instructions);
        $this->assertStringContainsString('## Cómo es la sesión', $cleaning->description);
        $this->assertSame('Nuestros cuidados.', $cleaning->aftercare);
        $this->assertSame(90.0, (float) $cleaning->price_from);
        $this->assertSame(40, $cleaning->duration_minutes);
        // Los tratamientos de más no se agregan solos: el consultorio los elige en Tratamientos.
        $this->assertSame(1, Treatment::count());

        $this->assertSame('Como la hacemos aquí.', $theirs->refresh()->body);
        $this->assertSame(1 + count(QuickTextCatalog::ADDED), TextSnippet::count());
        $this->assertTrue(TextSnippet::where('label', 'Indicaciones post-endodoncia')->exists());

        // Correrla otra vez no repite ni cambia nada.
        $this->migration()->up();
        $this->assertSame(1 + count(QuickTextCatalog::ADDED), TextSnippet::count());
        $this->assertSame('Nuestros cuidados.', $cleaning->refresh()->aftercare);
    }

    public function test_an_empty_database_is_left_for_the_seeder_to_fill(): void
    {
        $this->migration()->up();

        $this->assertSame(0, TextSnippet::count());
        $this->assertSame(0, Treatment::count());
    }

    public function test_going_back_restores_only_the_notice_texts_nobody_changed(): void
    {
        $this->template(MessageTemplateKey::Reminder, self::OLD_REMINDER);
        $this->template(MessageTemplateKey::Prescription, 'Hola {nombre}, tu receta: {receta}');
        $this->migration()->up();

        $this->migration()->down();

        $this->assertSame(self::OLD_REMINDER, MessageTemplate::where('key', MessageTemplateKey::Reminder)->value('body'));
        $this->assertSame('Hola {nombre}, tu receta: {receta}', MessageTemplate::where('key', MessageTemplateKey::Prescription)->value('body'));
    }

    private function template(MessageTemplateKey $key, string $body): MessageTemplate
    {
        return MessageTemplate::updateOrCreate(['key' => $key], ['name' => $key->getLabel(), 'body' => $body]);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_28_111457_bring_ready_to_use_content_to_clinics_already_working.php');
    }
}
