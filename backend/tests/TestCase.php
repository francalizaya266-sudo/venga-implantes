<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Las pruebas del backend no necesitan el frontend compilado: el documento de la web sale sin sus archivos.
     * Tampoco salen a internet: cada servicio de afuera (WhatsApp, RENIEC, Nager.Date) se simula con Http::fake().
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
    }
}
