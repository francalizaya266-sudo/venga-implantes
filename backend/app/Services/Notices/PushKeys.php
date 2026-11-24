<?php

namespace App\Services\Notices;

use Illuminate\Support\Facades\Process;
use Minishlink\WebPush\VAPID;
use Throwable;

/**
 * Crea el par de claves con que el consultorio firma los avisos en el celular (VAPID).
 *
 * En Windows, PHP lee la configuración de OpenSSL una sola vez, al arrancar: si no la tenía (ver OpenSslConfig), en
 * este mismo proceso no puede crear claves, pero un proceso nuevo ya la hereda. Por eso, si falla aquí, se crean en
 * un proceso aparte.
 */
class PushKeys
{
    /** Marca el proceso aparte, para que no vuelva a abrir otro si también falla. */
    private const CHILD = 'CONSULTORIO_CLAVES_EN_PROCESO_APARTE';

    /** @return array{publicKey: string, privateKey: string} */
    public function create(): array
    {
        try {
            return $this->createHere();
        } catch (Throwable $e) {
            if ((string) getenv(self::CHILD) !== '') {
                throw $e;
            }

            return $this->createApart() ?? throw $e;
        }
    }

    /** @return array{publicKey: string, privateKey: string} */
    protected function createHere(): array
    {
        return VAPID::createVapidKeys();
    }

    /** @return array{publicKey: string, privateKey: string}|null */
    private function createApart(): ?array
    {
        $result = Process::env([self::CHILD => '1'])
            ->path(base_path())
            ->run([PHP_BINARY, 'artisan', 'avisos:crear-claves', '--show']);

        if ($result->failed()
            || ! preg_match('/^VAPID_PUBLIC_KEY=(\S+)$/m', $result->output(), $public)
            || ! preg_match('/^VAPID_PRIVATE_KEY=(\S+)$/m', $result->output(), $private)) {
            return null;
        }

        return ['publicKey' => $public[1], 'privateKey' => $private[1]];
    }
}
