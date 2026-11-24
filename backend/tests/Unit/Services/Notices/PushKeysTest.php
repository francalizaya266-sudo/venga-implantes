<?php

namespace Tests\Unit\Services\Notices;

use App\Services\Notices\PushKeys;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

/** En Windows, PHP no puede crear las claves en el mismo proceso que arrancó sin la configuración de OpenSSL. */
class PushKeysTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('CONSULTORIO_CLAVES_EN_PROCESO_APARTE');

        parent::tearDown();
    }

    public function test_creates_them_here_when_openssl_can(): void
    {
        Process::fake();

        $keys = (new PushKeys)->create();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{87}$/', $keys['publicKey']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $keys['privateKey']);
        Process::assertNothingRan();
    }

    public function test_when_openssl_fails_here_it_creates_them_in_a_new_process(): void
    {
        Process::fake(['*' => Process::result("VAPID_PUBLIC_KEY=publica\nVAPID_PRIVATE_KEY=privada\n")]);

        $this->assertSame(['publicKey' => 'publica', 'privateKey' => 'privada'], $this->failingHere()->create());

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'avisos:crear-claves', '--show']
            && $process->environment === ['CONSULTORIO_CLAVES_EN_PROCESO_APARTE' => '1']);
    }

    public function test_if_the_new_process_also_fails_it_says_the_real_error(): void
    {
        Process::fake(['*' => Process::result('', 'Unable to create the key', 1)]);

        $this->expectExceptionMessage('Unable to create the key');

        $this->failingHere()->create();
    }

    public function test_the_new_process_does_not_open_another(): void
    {
        Process::fake();
        putenv('CONSULTORIO_CLAVES_EN_PROCESO_APARTE=1');

        try {
            $this->failingHere()->create();
            $this->fail('Tenía que fallar.');
        } catch (RuntimeException) {
            Process::assertNothingRan();
        }
    }

    private function failingHere(): PushKeys
    {
        return new class extends PushKeys
        {
            protected function createHere(): array
            {
                throw new RuntimeException('Unable to create the key');
            }
        };
    }
}
