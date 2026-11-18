<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Las pruebas del backend no necesitan el frontend compilado: el documento de la web sale sin sus archivos.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
