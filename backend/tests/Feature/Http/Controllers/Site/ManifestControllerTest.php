<?php

namespace Tests\Feature\Http\Controllers\Site;

use App\Models\Setting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/** La web como app: el celular la instala con el nombre y el ícono del consultorio, y la abre en Mi cuenta. */
class ManifestControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_phone_installs_the_web_with_the_clinic_name_and_opens_my_account(): void
    {
        Setting::set('clinic.name', 'Clínica Vega Implant');

        $this->get(route('site.manifest'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('name', 'Clínica Vega Implant')
            ->assertJsonPath('short_name', 'Vega Implant')
            ->assertJsonPath('start_url', '/mi-cuenta')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('icons.1.sizes', '512x512')
            ->assertJsonPath('icons.3.purpose', 'maskable');

        foreach (['icon-192.png', 'icon-512.png', 'icon-maskable-192.png', 'icon-maskable-512.png', 'sw.js'] as $file) {
            $this->assertFileExists(public_path($file));
        }
    }

    public function test_every_page_says_where_the_app_description_is(): void
    {
        $this->get(route('site.home'))->assertOk()->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);
    }
}
