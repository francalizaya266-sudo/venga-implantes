<?php

namespace App\Support;

/**
 * En Windows, el PHP descargado de php.net trae la configuración de OpenSSL en extras\ssl\openssl.cnf, pero no la
 * busca ahí: sin ella no se pueden crear las claves de cifrado de cada aviso en el celular, y ninguno sale. Si nadie
 * la indicó (OPENSSL_CONF), se usa esa.
 *
 * PHP la lee una sola vez, al arrancar: sirve para los procesos que se abren desde aquí, como los comandos del
 * programador de tareas (avisos:enviar-al-celular) o el que atiende las páginas con artisan serve. Por eso
 * iniciar-consultorio.bat ya la indica antes de arrancar PHP, y avisos:crear-claves crea las claves en un proceso
 * aparte si hace falta (PushKeys).
 */
class OpenSslConfig
{
    /** @return string|null La configuración que quedó en uso, o null si no hizo falta o no se encontró. */
    public static function useBundled(string $osFamily = PHP_OS_FAMILY, string $phpBinary = PHP_BINARY): ?string
    {
        if ($osFamily !== 'Windows' || (string) getenv('OPENSSL_CONF') !== '') {
            return null;
        }

        $config = dirname($phpBinary).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';

        if (! is_file($config)) {
            return null;
        }

        // En $_SERVER y no en $_ENV: artisan serve quita del proceso que atiende las páginas lo que esté en $_ENV.
        putenv('OPENSSL_CONF='.$config);
        $_SERVER['OPENSSL_CONF'] = $config;

        return $config;
    }
}
