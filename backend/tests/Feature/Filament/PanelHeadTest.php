<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PanelHeadTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** En el iPhone la página llega a los bordes (así valen las franjas "safe-area-inset") y la barra del navegador toma el color de la de arriba. */
    public function test_the_panel_reaches_the_phone_edges_and_paints_the_browser_bar(): void
    {
        $this->get(Filament::getLoginUrl())
            ->assertOk()
            ->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">', false)
            ->assertSee('<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">', false)
            ->assertSee('<meta name="theme-color" content="#0b1327" media="(prefers-color-scheme: dark)">', false);

        $this->actingAs(User::factory()->admin()->create());

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('viewport-fit=cover', false)
            ->assertSee('name="theme-color"', false);
    }
}
