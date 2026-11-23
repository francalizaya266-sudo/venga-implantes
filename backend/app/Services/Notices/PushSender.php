<?php

namespace App\Services\Notices;

use App\Enums\PushOutcome;
use App\Models\PushSubscription;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Entrega un aviso al servicio de avisos del navegador del paciente (Google, Apple, Mozilla), que lo muestra en la
 * pantalla de su celular. Va firmado con las claves del consultorio (VAPID) y cifrado para ese navegador.
 */
class PushSender
{
    /** Un día: si el celular estuvo apagado más tiempo, el aviso ya no le sirve. */
    private const KEEP_SECONDS = 86400;

    private ?WebPush $webPush = null;

    /** @param  ClientInterface|null  $client  Para las pruebas; si no, uno con tiempo de espera. */
    public function __construct(private readonly ?ClientInterface $client = null) {}

    public function isConfigured(): bool
    {
        return filled(config('consultorio.push.public_key')) && filled(config('consultorio.push.private_key'));
    }

    /** @param  array{title: string, body: string, url: string, tag: string}  $notice */
    public function send(PushSubscription $subscription, array $notice): PushOutcome
    {
        try {
            $report = $this->webPush()->sendOneNotification(
                new Subscription($subscription->endpoint, $subscription->public_key, $subscription->auth_token, $subscription->content_encoding),
                json_encode($notice, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ['TTL' => self::KEEP_SECONDS, 'urgency' => 'high'],
            );
        } catch (Throwable $e) {
            report($e);

            return PushOutcome::Failed;
        }

        if ($report->isSuccess()) {
            return PushOutcome::Delivered;
        }

        if ($report->isSubscriptionExpired()) {
            return PushOutcome::Gone;
        }

        Log::warning('Aviso en el celular no entregado', ['reason' => $report->getReason()]);

        return PushOutcome::Failed;
    }

    private function webPush(): WebPush
    {
        return $this->webPush ??= new WebPush(
            ['VAPID' => [
                'subject' => $this->subject(),
                'publicKey' => (string) config('consultorio.push.public_key'),
                'privateKey' => (string) config('consultorio.push.private_key'),
            ]],
            // Un servicio de avisos que no responde no detiene a los demás: a los 10 segundos se deja para la próxima.
            client: $this->client ?? new Client(['timeout' => 10, 'connect_timeout' => 5]),
            // Sin un registro propio, la librería avisa con un error de PHP si al servidor le falta GMP o BCMath (solo
            // es más lento sin ellas), y Laravel lo trata como una falla: ningún aviso saldría. Los errores reales se
            // reportan igual desde send().
            logger: new NullLogger,
        );
    }

    /** La web del consultorio (o el correo de VAPID_SUBJECT): Apple la pide en https o como mailto:. */
    private function subject(): string
    {
        return (string) (config('consultorio.push.subject') ?: config('app.url'));
    }
}
