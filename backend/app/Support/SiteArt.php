<?php

namespace App\Support;

use App\Models\Treatment;

/**
 * Qué imagen 3D de la web (public/img/3d) le toca a cada tratamiento. Se elige sola por su nombre, igual que su
 * ícono: no hay nada que configurar. La API la entrega por su nombre ("implante", "limpieza"…) y el frontend arma
 * la dirección y los tamaños (frontend/src/lib/art.ts).
 */
final class SiteArt
{
    /** @var array<string, string> Ícono del tratamiento (TreatmentIcon) → su imagen. */
    private const BY_ICON = [
        'tooth' => 'diente',
        'clipboard' => 'evaluacion',
        'sparkle' => 'limpieza',
        'filling' => 'resina',
        'extraction' => 'extraccion',
        'braces' => 'brackets',
        'implant' => 'implante',
        'root' => 'endodoncia',
        'crown' => 'corona',
        'gum' => 'encia',
        'child' => 'ninos',
    ];

    /** @var list<string> Imágenes que dicen una especialidad (no lo de todos los días: evaluar, limpiar, curar). */
    private const SPECIALTIES = ['implante', 'brackets', 'endodoncia', 'corona', 'encia', 'ninos'];

    /**
     * Qué imagen le toca a un tratamiento, por su nombre. Donde dos compartían ícono, cada uno tiene la suya:
     * el blanqueamiento no es una limpieza, y el control de ortodoncia no es la colocación de brackets.
     */
    public static function key(string $text): string
    {
        $image = self::BY_ICON[TreatmentIcon::key($text)];
        $words = TreatmentIcon::words($text);

        return match (true) {
            $image === 'limpieza' && str_contains($words, ' blanquea') => 'blanqueamiento',
            $image === 'brackets' && (str_contains($words, ' control') || str_contains($words, ' ajuste')) => 'control',
            default => $image,
        };
    }

    public static function keyFor(Treatment $treatment): string
    {
        return self::key($treatment->name.' '.$treatment->slug);
    }

    /**
     * La imagen que corresponde a la especialidad que declara un doctor ("Implantólogo" → "implante"), o null
     * si su especialidad no es una de las que tienen imagen propia.
     */
    public static function specialty(?string $specialty): ?string
    {
        $image = self::key((string) $specialty);

        return in_array($image, self::SPECIALTIES, true) ? $image : null;
    }
}
