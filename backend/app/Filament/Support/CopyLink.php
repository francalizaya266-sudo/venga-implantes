<?php

namespace App\Filament\Support;

use Illuminate\Support\Js;

/**
 * El botón "Copiar enlace": copia el enlace y avisa junto al botón. Si el navegador no deja copiar (pasa cuando el
 * panel no se abre con una dirección segura), lo muestra en una ventanita para copiarlo a mano.
 */
final class CopyLink
{
    /** Lo que hace el botón al tocarlo, para el alpineClickHandler() de la acción. */
    public static function js(string $url): string
    {
        $text = Js::from($url);
        $copied = Js::from('Enlace copiado');
        $manual = Js::from('Copia el enlace');

        return "(window.navigator.clipboard ? window.navigator.clipboard.writeText({$text}) : Promise.reject())"
            ."  .then(() => \$tooltip({$copied}, { theme: \$store.theme, timeout: 2000 }), () => window.prompt({$manual}, {$text}))";
    }
}
