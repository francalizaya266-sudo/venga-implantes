<?php

namespace Tests\Unit\Support;

use App\Support\OpenSslConfig;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OpenSslConfigTest extends TestCase
{
    private string $php;

    protected function setUp(): void
    {
        parent::setUp();

        $this->php = storage_path('framework/testing/php-windows');
        File::ensureDirectoryExists($this->php.'/extras/ssl');
        File::put($this->php.'/extras/ssl/openssl.cnf', '# openssl');
        putenv('OPENSSL_CONF');
    }

    protected function tearDown(): void
    {
        putenv('OPENSSL_CONF');
        unset($_ENV['OPENSSL_CONF'], $_SERVER['OPENSSL_CONF']);
        File::deleteDirectory($this->php);

        parent::tearDown();
    }

    public function test_on_windows_it_uses_the_configuration_that_comes_with_php(): void
    {
        $config = OpenSslConfig::useBundled('Windows', $this->php.'/php.exe');

        $this->assertSame($this->php.DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf', $config);
        $this->assertSame($config, getenv('OPENSSL_CONF'));
        // Los procesos nuevos la heredan, y artisan serve no la quita: solo quita lo que está en $_ENV.
        $this->assertSame($config, $_SERVER['OPENSSL_CONF']);
        $this->assertArrayNotHasKey('OPENSSL_CONF', $_ENV);
    }

    public function test_it_leaves_alone_a_configuration_already_given_or_another_system(): void
    {
        putenv('OPENSSL_CONF=C:\\otra\\openssl.cnf');
        $this->assertNull(OpenSslConfig::useBundled('Windows', $this->php.'/php.exe'));
        $this->assertSame('C:\\otra\\openssl.cnf', getenv('OPENSSL_CONF'));

        putenv('OPENSSL_CONF');
        $this->assertNull(OpenSslConfig::useBundled('Linux', $this->php.'/php'));
        $this->assertNull(OpenSslConfig::useBundled('Windows', storage_path('framework/testing/sin-php/php.exe')));
        $this->assertFalse(getenv('OPENSSL_CONF'));
    }
}
