<?php

namespace Tests\Feature\Filament;

use Tests\TestCase;

/**
 * Con el dedo, lo que se toca en el panel mide al menos 44 px de alto: con menos, en el iPhone se erraba al tocar
 * (botones de 32 a 36 px, opciones de texto rápido de 22). Las reglas van en los bloques "@media (pointer: coarse)" de
 * panel.css, así en la computadora todo queda del tamaño de siempre.
 */
class PanelTouchTargetsTest extends TestCase
{
    /**
     * Lo que se toca y, de fábrica o por su estilo, quedaba más bajo: los botones (salvo el siguiente paso de una cita en
     * la agenda, que tiene su propia zona para el dedo), la "x" que quita un tratamiento elegido, las opciones de Hoy, las
     * etiquetas y el filtro de la ficha, y lo que se toca en la barra de Atender.
     */
    private const TOUCHED = [
        '.fi-btn:not(.agenda-appt-step .fi-btn)',
        '.fi-icon-btn:not(.fi-input-wrp .fi-icon-btn)',
        '.fi-user-menu-trigger',
        '.fi-badge .fi-badge-delete-btn',
        '.dash-segmented :is(a, button)',
        ':is(a, button).pt-chip',
        '.pt-filter-chip',
        'button.cb-flag',
        '.cb-flag-ask button',
        '.cb-unsaved',
        '.cb-sections a',
        '.dictate-btn',
    ];

    public function test_with_a_finger_what_is_touched_in_the_panel_measures_44_px(): void
    {
        $tall = $this->selectorsAtLeast44Pixels((string) file_get_contents(public_path('css/panel.css')));

        $this->assertSame([], array_values(array_diff(self::TOUCHED, $tall)));
    }

    /** Las opciones del campo de textos rápidos ("Dolor dental", "Sensibilidad"…) traen su propio estilo. */
    public function test_with_a_finger_the_quick_text_options_measure_44_px_too(): void
    {
        preg_match('#<style>(.*?)</style>#s', (string) file_get_contents(app_path('Filament/Forms/QuickTextarea.php')), $style);

        $this->assertArrayHasKey(1, $style);
        $this->assertContains('.qt-chip', $this->selectorsAtLeast44Pixels($style[1]));
    }

    /** @return list<string> Los selectores que, en los bloques para el dedo, piden 44 px (2.75rem) de alto como mínimo. */
    private function selectorsAtLeast44Pixels(string $css): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $selectors = [];
        $offset = 0;

        while (($start = strpos($css, '@media (pointer: coarse)', $offset)) !== false) {
            // El bloque termina en la llave que cierra la suya: adentro puede haber otro (un @container).
            $open = (int) strpos($css, '{', $start);
            $depth = 0;
            for ($end = $open; $end < strlen($css); $end++) {
                $depth += match ($css[$end]) {
                    '{' => 1,
                    '}' => -1,
                    default => 0,
                };
                if ($depth === 0) {
                    break;
                }
            }

            preg_match_all('/([^{}]+)\{([^{}]*)\}/', substr($css, $open + 1, $end - $open - 1), $rules, PREG_SET_ORDER);
            foreach ($rules as [, $selectorList, $declarations]) {
                if (preg_match('/(^|;)\s*min-height:\s*2\.75rem/', $declarations) === 1) {
                    // Se separa por las comas de afuera: ":is(a, button)" es un solo selector.
                    foreach ((array) preg_split('/,(?![^(]*\))/', $selectorList) as $selector) {
                        $selectors[] = trim((string) $selector);
                    }
                }
            }

            $offset = $end;
        }

        return $selectors;
    }
}
