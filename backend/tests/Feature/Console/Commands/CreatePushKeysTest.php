<?php

namespace Tests\Feature\Console\Commands;

use App\Models\PushSubscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Las claves de los avisos en el celular se crean una vez y quedan en .env. */
class CreatePushKeysTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = storage_path('framework/testing/env-'.uniqid());
        File::ensureDirectoryExists($this->folder);
        File::put($this->folder.'/.env', "APP_NAME=Consultorio\nVAPID_PUBLIC_KEY=\nVAPID_PRIVATE_KEY=\n");
        $this->app->useEnvironmentPath($this->folder);
        config(['consultorio.push.public_key' => null, 'consultorio.push.private_key' => null]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);

        parent::tearDown();
    }

    public function test_creates_the_keys_once_and_saves_them_in_env(): void
    {
        $this->artisan('avisos:crear-claves')->assertSuccessful();

        $env = File::get($this->folder.'/.env');
        $this->assertMatchesRegularExpression('/^VAPID_PUBLIC_KEY=[A-Za-z0-9_-]{87}$/m', $env);
        $this->assertMatchesRegularExpression('/^VAPID_PRIVATE_KEY=[A-Za-z0-9_-]{43}$/m', $env);
        $this->assertStringStartsWith("APP_NAME=Consultorio\n", $env);

        // Ya hay claves: no se cambian, o los celulares que activaron los avisos dejarían de recibirlos.
        $this->artisan('avisos:crear-claves')
            ->expectsOutput('Las claves de los avisos en el celular ya están en .env. No hace falta crearlas de nuevo.')
            ->assertSuccessful();
        $this->assertSame($env, File::get($this->folder.'/.env'));
    }

    public function test_changing_them_asks_every_phone_to_turn_notices_on_again(): void
    {
        config(['consultorio.push.public_key' => 'vieja', 'consultorio.push.private_key' => 'vieja']);
        PushSubscription::factory()->count(2)->create();

        $this->artisan('avisos:crear-claves', ['--force' => true])
            ->expectsOutput('Claves cambiadas. 2 celulares tienen que volver a activar los avisos en Mi cuenta.')
            ->assertSuccessful();

        $this->assertDatabaseCount('push_subscriptions', 0);
        $this->assertNotSame('vieja', config('consultorio.push.public_key'));
    }

    public function test_show_only_prints_them(): void
    {
        $this->artisan('avisos:crear-claves', ['--show' => true])
            ->expectsOutputToContain('VAPID_PUBLIC_KEY=')
            ->assertSuccessful();

        $this->assertSame("APP_NAME=Consultorio\nVAPID_PUBLIC_KEY=\nVAPID_PRIVATE_KEY=\n", File::get($this->folder.'/.env'));
    }
}
