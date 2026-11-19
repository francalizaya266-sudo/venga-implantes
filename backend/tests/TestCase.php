<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\ChecksApiContract;

abstract class TestCase extends BaseTestCase
{
    use ChecksApiContract;

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

    /**
     * Toda respuesta de la API que pide una prueba también se compara con docs/api/openapi.json.
     *
     * @param  Response  $response
     * @param  Request  $request
     * @return TestResponse<Response>
     */
    protected function createTestResponse($response, $request)
    {
        $this->assertMatchesApiContract($request, $response);

        return parent::createTestResponse($response, $request);
    }
}
