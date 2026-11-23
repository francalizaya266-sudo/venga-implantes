<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Settings;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * El aviso de un campo vacío o mal escrito sale en español aunque el navegador esté en inglés (antes decía "Please
 * fill out this field"): lo pone validation-messages.js, que va en todas las pantallas del panel, también al entrar.
 */
class FieldMessagesInSpanishTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_every_panel_screen_brings_the_spanish_field_messages(): void
    {
        $this->get(Filament::getLoginUrl())
            ->assertOk()
            ->assertSee('js/validation-messages.js');

        $this->actingAs(User::factory()->admin()->create())
            ->get(Settings::getUrl())
            ->assertOk()
            ->assertSee('js/validation-messages.js');

        $script = (string) file_get_contents(public_path('js/validation-messages.js'));

        foreach (['Completa este campo.', 'Elige una opción de la lista.', 'Escribe un correo completo', 'Pon un múltiplo de'] as $message) {
            $this->assertStringContainsString($message, $script);
        }
    }
}
