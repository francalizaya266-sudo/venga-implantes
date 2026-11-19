<?php

namespace Tests\Feature\Http\Middleware;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * En Azure el sistema queda detrás del proxy de la DMZ (infra/nginx/proxy.conf). Sin confiar en él, todos los
 * pacientes compartirían la IP del proxy en los límites por IP y Laravel creería que la conexión es HTTP.
 */
class TrustedProxiesTest extends TestCase
{
    private const PROXY_IP = '10.20.3.4';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_prueba/conexion', fn (Request $request) => [
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
        ]);
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    public function test_behind_the_configured_proxy_it_sees_the_patients_ip_and_https(): void
    {
        config(['consultorio.trusted_proxies' => self::PROXY_IP]);
        AppServiceProvider::trustConfiguredProxies();

        $this->fromProxy(self::PROXY_IP)
            ->getJson('/_prueba/conexion')
            ->assertExactJson(['ip' => '190.42.10.7', 'secure' => true]);
    }

    public function test_without_configured_proxies_forwarded_headers_are_ignored(): void
    {
        config(['consultorio.trusted_proxies' => null]);
        AppServiceProvider::trustConfiguredProxies();

        $this->fromProxy(self::PROXY_IP)
            ->getJson('/_prueba/conexion')
            ->assertExactJson(['ip' => self::PROXY_IP, 'secure' => false]);
    }

    public function test_a_server_that_is_not_the_proxy_cannot_fake_the_patients_ip(): void
    {
        config(['consultorio.trusted_proxies' => self::PROXY_IP]);
        AppServiceProvider::trustConfiguredProxies();

        $this->fromProxy('203.0.113.9')
            ->getJson('/_prueba/conexion')
            ->assertExactJson(['ip' => '203.0.113.9', 'secure' => false]);
    }

    private function fromProxy(string $remoteAddress): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
            ->withHeaders([
                'X-Forwarded-For' => '190.42.10.7',
                'X-Forwarded-Proto' => 'https',
            ]);
    }
}
