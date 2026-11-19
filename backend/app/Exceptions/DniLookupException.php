<?php

namespace App\Exceptions;

use RuntimeException;

/** La consulta de DNI no se pudo hacer, con un mensaje en español simple, listo para mostrar. */
class DniLookupException extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self('La consulta de DNI no respondió. Escribe los nombres a mano o inténtalo en un rato.');
    }

    public static function limitReached(): self
    {
        return new self('Se acabaron las consultas de DNI del plan. Escribe los nombres a mano.');
    }
}
