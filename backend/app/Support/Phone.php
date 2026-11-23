<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Celulares en formato E.164. Por defecto se asume Perú (+51):
 * "987 654 321", "51987654321" y "+51 987-654-321" dan "+51987654321".
 */
final class Phone
{
    public static function normalize(string $phone, string $countryCode = '51'): string
    {
        $hasPlus = str_starts_with(trim($phone), '+');
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            throw new InvalidArgumentException('El número de celular está vacío.');
        }

        if (! $hasPlus && strlen($digits) === 9 && $countryCode === '51') {
            $digits = $countryCode.$digits;
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            throw new InvalidArgumentException("El número de celular {$phone} no es válido.");
        }

        return '+'.$digits;
    }

    public static function isValid(string $phone): bool
    {
        return self::problem($phone) === null;
    }

    /**
     * Qué le falta al número para ser un celular, o null si está bien. Sin "+" se toma como peruano:
     * 9 dígitos que empiezan con 9 (con o sin el 51 delante). Un número de otro país se escribe con "+".
     */
    public static function problem(string $phone): ?string
    {
        $hasPlus = str_starts_with(trim($phone), '+');
        $digits = (string) preg_replace('/\D+/', '', $phone);

        if ($hasPlus && ! str_starts_with($digits, '51')) {
            return strlen($digits) >= 10 && strlen($digits) <= 15 ? null : 'Revisa el número: con el código de país tiene de 10 a 15 dígitos.';
        }

        $local = $hasPlus || (strlen($digits) === 11 && str_starts_with($digits, '51')) ? substr($digits, 2) : $digits;

        return match (true) {
            strlen($local) !== 9 => 'El celular tiene 9 dígitos y escribiste '.strlen($local).'.',
            ! str_starts_with($local, '9') => 'El celular empieza con 9, por ejemplo 987 654 321.',
            default => null,
        };
    }

    /** Número para enlaces wa.me (solo dígitos). */
    public static function forWhatsApp(string $phone): string
    {
        return ltrim(self::normalize($phone), '+');
    }

    /**
     * Un celular o un fijo peruano en formato internacional, para Google: "+51 987 654 321", "+51 51 321456" (fijo
     * de provincia, "051 321456") o "+51 1 4567890" (fijo de Lima). Uno de otro país, con su "+", queda como está.
     * Null si no se puede saber a qué número corresponde (un fijo sin el código de su ciudad).
     */
    public static function international(string $phone): ?string
    {
        $phone = trim($phone);
        $digits = (string) preg_replace('/\D+/', '', $phone);

        if (str_starts_with($phone, '+') && ! str_starts_with($digits, '51')) {
            return $phone;
        }

        // Con el código del país delante: "+51987654321" o "51 987 654 321".
        if (preg_match('/^51(9\d{8})$/', $digits, $match) === 1) {
            $digits = $match[1];
        }

        if (preg_match('/^(9\d{2})(\d{3})(\d{3})$/', $digits, $match) === 1) {
            return "+51 {$match[1]} {$match[2]} {$match[3]}";
        }

        // Fijos: el 0 de la llamada nacional no va; Lima es el 1 y cada provincia tiene su código de dos cifras.
        if (preg_match('/^0?1(\d{7})$/', $digits, $match) === 1) {
            return "+51 1 {$match[1]}";
        }

        if (preg_match('/^0?([4-8]\d)(\d{6})$/', $digits, $match) === 1) {
            return "+51 {$match[1]} {$match[2]}";
        }

        // Un fijo que ya viene con el +51 ("+51 51 321456") queda como lo escribieron.
        return str_starts_with($phone, '+') ? $phone : null;
    }

    /** "+51987654321" → "987 654 321" para mostrar en pantalla. */
    public static function display(string $phone): string
    {
        $normalized = self::normalize($phone);

        if (preg_match('/^\+51(\d{3})(\d{3})(\d{3})$/', $normalized, $m) === 1) {
            return "{$m[1]} {$m[2]} {$m[3]}";
        }

        return $normalized;
    }
}
