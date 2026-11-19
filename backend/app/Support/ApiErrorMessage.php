<?php

namespace App\Support;

/**
 * Lo que la API le dice a la web cuando una petición falla por algo que no es un dato mal escrito: el enlace no
 * existe, no le toca verlo, pidió demasiadas veces o algo se cayó en el servidor. Laravel trae esos mensajes en
 * inglés (o vacíos, y en un 404 de un modelo dice el nombre de la clase); la web los muestra tal cual al paciente,
 * así que se cambian por uno en español que diga qué hacer.
 *
 * Los 409 y 422 no pasan por aquí: ya traen el motivo en español (la validación o la regla del consultorio).
 */
final class ApiErrorMessage
{
    public static function forStatus(int $status): ?string
    {
        return match (true) {
            $status === 403 => 'No tienes permiso para ver o cambiar esto.',
            $status === 404 => 'No encontramos lo que buscas. Puede que el enlace esté incompleto o ya no exista.',
            $status === 405 => 'Esta dirección no acepta ese tipo de pedido.',
            $status === 419 => 'La página venció. Recárgala e inténtalo de nuevo.',
            $status === 429 => 'Hiciste muchos intentos seguidos. Espera un momento y vuelve a intentarlo.',
            $status >= 500 => 'Algo falló de nuestro lado. Inténtalo de nuevo en unos minutos o escríbenos por WhatsApp.',
            default => null,
        };
    }
}
