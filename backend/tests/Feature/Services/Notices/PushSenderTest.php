<?php

namespace Tests\Feature\Services\Notices;

use App\Enums\PushOutcome;
use App\Models\PushSubscription;
use App\Services\Notices\PushSender;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Minishlink\WebPush\VAPID;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

/** El aviso sale firmado con las claves del consultorio y cifrado para el navegador del paciente. */
class PushSenderTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<array{request: RequestInterface}> */
    private array $sent = [];

    private function sender(Response $answer): PushSender
    {
        $keys = VAPID::createVapidKeys();
        config(['consultorio.push.public_key' => $keys['publicKey'], 'consultorio.push.private_key' => $keys['privateKey'], 'consultorio.push.subject' => 'https://consultorio.test']);
        $stack = HandlerStack::create(new MockHandler([$answer]));
        $stack->push(Middleware::history($this->sent));

        return new PushSender(new Client(['handler' => $stack]));
    }

    /** Un navegador de verdad: su clave pública P-256 y su secreto. */
    private function browser(): PushSubscription
    {
        $browser = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $details = openssl_pkey_get_details($browser);
        $point = "\x04".str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
        $base64url = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return PushSubscription::factory()->create(['public_key' => $base64url($point), 'auth_token' => $base64url(random_bytes(16))]);
    }

    public function test_a_notice_reaches_the_browser_service_signed_and_encrypted(): void
    {
        $outcome = $this->sender(new Response(201))->send($phone = $this->browser(), ['title' => 'Recordatorio de cita', 'body' => 'Rosa: mañana a las 3:00 p. m.', 'url' => '/mi-cuenta', 'tag' => 'cita-1']);

        $this->assertSame(PushOutcome::Delivered, $outcome);
        $request = $this->sent[0]['request'];
        $this->assertSame($phone->endpoint, (string) $request->getUri());
        $this->assertSame('aes128gcm', $request->getHeaderLine('Content-Encoding'));
        $this->assertStringStartsWith('vapid t=', $request->getHeaderLine('Authorization'));
        $this->assertSame('86400', $request->getHeaderLine('TTL'));
        // Cifrado: el texto no viaja legible.
        $this->assertStringNotContainsString('Rosa', (string) $request->getBody());
    }

    public function test_a_browser_that_no_longer_accepts_notices_is_told_apart_from_a_service_that_failed(): void
    {
        $this->assertSame(PushOutcome::Gone, $this->sender(new Response(410))->send($this->browser(), ['title' => 'a', 'body' => 'b', 'url' => '/', 'tag' => 't']));
        $this->assertSame(PushOutcome::Failed, $this->sender(new Response(503))->send($this->browser(), ['title' => 'a', 'body' => 'b', 'url' => '/', 'tag' => 't']));
    }
}
