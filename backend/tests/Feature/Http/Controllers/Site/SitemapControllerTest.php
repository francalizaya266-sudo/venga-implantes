<?php

namespace Tests\Feature\Http\Controllers\Site;

use App\Models\Campaign;
use App\Models\Treatment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SitemapControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_sitemap_lists_public_pages_and_robots_keeps_private_ones_out(): void
    {
        Treatment::factory()->create(['slug' => 'limpieza']);
        Treatment::factory()->create(['slug' => 'interno', 'is_public' => false]);
        Campaign::query()->create([
            'name' => 'Limpieza de verano',
            'slug' => 'limpieza-verano',
            'summary' => 'Limpieza con 20% de descuento.',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->get(route('site.sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.route('site.treatment', 'limpieza').'</loc>', false)
            ->assertDontSee(route('site.treatment', 'interno'), false)
            ->assertSee('<loc>'.route('site.promotion', 'limpieza-verano').'</loc>', false)
            ->assertSee('<loc>'.route('site.contact').'</loc>', false)
            // Sin preguntas publicadas, no se anuncia una página vacía.
            ->assertDontSee(route('site.faq'), false);

        $this->get(route('site.robots'))
            ->assertOk()
            ->assertSee('Disallow: /panel')
            ->assertSee('Disallow: /mi-cuenta')
            ->assertSee('Sitemap: '.route('site.sitemap'));
    }
}
