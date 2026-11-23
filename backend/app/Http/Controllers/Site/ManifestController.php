<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Web\ClinicInfo;
use Illuminate\Http\JsonResponse;

/**
 * La web como app: con esto el celular la instala con el nombre y el ícono del consultorio, y la abre en Mi cuenta a
 * pantalla completa. En iPhone, además, es lo que permite los avisos en la pantalla.
 */
class ManifestController extends Controller
{
    public function __invoke(ClinicInfo $clinic): JsonResponse
    {
        $name = $clinic->name();
        $start = route('site.account', absolute: false);

        return response()->json([
            'id' => $start,
            'name' => $name,
            // Debajo del ícono entra poco: "Vega Implant" en vez de "Clínica Vega Implant".
            'short_name' => (string) preg_replace('/^(cl[ií]nica|consultorio)(\s+dental)?\s+/iu', '', $name),
            'description' => 'Tus citas, avisos y pagos en '.$name.'.',
            'lang' => 'es-PE',
            'dir' => 'ltr',
            'start_url' => $start,
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#ffffff',
            'icons' => [
                ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icon-maskable-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => '/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
