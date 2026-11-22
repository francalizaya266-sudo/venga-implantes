<?php

namespace App\Filament\Support;

/**
 * Las columnas de las páginas siguen el espacio que queda al lado del menú, no el ancho del monitor: al cerrar el
 * menú, la página se acomoda como en una pantalla más grande. El contenedor es el contenido de la página
 * (panel.css, .fi-page-content); "!@lg" es para un navegador sin consultas de contenedor, que sigue al monitor.
 *
 * Lo que queda para la página: 1054 px en una laptop de 1366 con el menú abierto, 1128 px en una de 1440, 1536 px
 * en un monitor de 1920; al cerrar el menú, 200 px más.
 */
final class PageLayout
{
    /**
     * Lo principal y su costado (cita, atención, presupuesto): una columna hasta 768 px; desde ahí, dos tercios y un
     * tercio; desde 1152 px (una laptop de 1366 con el menú cerrado), tres quintos y dos, con las tarjetas del costado
     * lado a lado: tres columnas a la vista.
     */
    public const COLUMNS = ['default' => 1, '@3xl' => 3, '@6xl' => 5, '!@lg' => 3];

    /** Lo principal: dos de tres o tres de cinco. */
    public const MAIN = ['default' => 'full', '@3xl' => 2, '@6xl' => 3, '!@lg' => 2];

    /** El costado: una de tres o dos de cinco, con sus tarjetas lado a lado (SIDE_COLUMNS). */
    public const SIDE = ['default' => 'full', '@3xl' => 1, '@6xl' => 2, '!@lg' => 1];

    /** Las tarjetas del costado: una debajo de la otra y, con cinco columnas, una al lado de la otra. */
    public const SIDE_COLUMNS = ['default' => 1, '@6xl' => 2];

    /** Un costado angosto a la izquierda (el perfil, los antecedentes) y el resto al lado: de tres o de cuatro columnas. */
    public const PROFILE_COLUMNS = ['default' => 1, '@3xl' => 3, '@6xl' => 4, '!@lg' => 3];

    /** El costado angosto: una columna de tres o de cuatro. */
    public const NARROW = ['default' => 'full', '@3xl' => 1, '!@lg' => 1];

    /** Lo que va al lado del angosto, con todo el resto: dos de tres o tres de cuatro. */
    public const WIDE = ['default' => 'full', '@3xl' => 2, '@6xl' => 3, '!@lg' => 2];
}
