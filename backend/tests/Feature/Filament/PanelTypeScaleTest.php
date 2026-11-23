<?php

namespace Tests\Feature\Filament;

use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La letra del panel va en seis tamaños, como dice el comienzo de panel.css. Cada pantalla se escribe ahí a mano, y un
 * tamaño suelto (un 12,8 o un 16,8 px) no se ve igual que el de al lado aunque diga lo mismo.
 */
class PanelTypeScaleTest extends TestCase
{
    /** Los seis tamaños, en px: 12, 13, 14, 16, 18 y 22. */
    private const SCALE = [12.0, 13.0, 14.0, 16.0, 18.0, 22.0];

    /**
     * Lo que va aparte a propósito: las letras dentro del dibujo de los dientes, las horas libres de la semana en Hoy
     * ("11 h 20" en el ancho de un día) y las cifras de arriba de los reportes y del cierre, que se achican para entrar.
     */
    private const APART = ['.odv-face-name', '.dash-week-free', '.fi-wi-stats-overview-stat-value'];

    public function test_every_text_of_the_panel_uses_one_of_the_six_sizes_of_the_scale(): void
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(public_path('css/panel.css')));

        preg_match_all('/([^{}]+)\{[^{}]*?font-size:\s*([^;}]+)/', $css, $rules, PREG_SET_ORDER);
        $loose = collect($rules)
            ->map(fn (array $rule): array => ['selector' => trim($rule[1]), 'size' => trim($rule[2])])
            ->reject(fn (array $rule): bool => Str::contains($rule['selector'], self::APART))
            ->reject(fn (array $rule): bool => in_array(self::pixels($rule['size']), self::SCALE, true))
            ->map(fn (array $rule): string => "{$rule['selector']}: {$rule['size']}")
            ->values()
            ->all();

        // Se leyó cada tamaño del archivo, ninguno quedó sin revisar.
        $this->assertCount(substr_count($css, 'font-size:'), $rules);
        $this->assertSame([], $loose);
    }

    /** "0.8125rem" y "13px" son 13 px; lo que no se mide en rem ni en px (min(), em, %) no es un tamaño de la escala. */
    private static function pixels(string $size): ?float
    {
        if (preg_match('/^(\d*\.?\d+)(rem|px)$/', $size, $match) !== 1) {
            return null;
        }

        return (float) $match[1] * ($match[2] === 'rem' ? 16 : 1);
    }
}
