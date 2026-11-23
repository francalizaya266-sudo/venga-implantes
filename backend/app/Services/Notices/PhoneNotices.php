<?php

namespace App\Services\Notices;

use App\Enums\PushOutcome;
use App\Models\Account;
use App\Models\PushSubscription;
use App\Models\RememberedDevice;
use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Builder;

/**
 * Avisos en el celular: los de las citas y cuotas también salen en la pantalla del celular donde el paciente los
 * activó, desde Mi cuenta. Cada familia elige si además le llegan por WhatsApp; los enlaces para entrar, las recetas
 * y los planes siguen siempre por WhatsApp.
 */
class PhoneNotices
{
    public function __construct(private readonly PushSender $sender, private readonly PhoneNoticeText $text) {}

    /** Con las claves del consultorio (php artisan avisos:crear-claves); sin ellas, Mi cuenta no ofrece los avisos. */
    public function isAvailable(): bool
    {
        return $this->sender->isConfigured();
    }

    /**
     * Cómo recibe los avisos esta familia, para Mi cuenta. Solo en un celular verificado: elegir cambia lo de toda
     * la familia.
     *
     * @return array{key: string, this_phone: bool, phones: int, whatsapp: bool}|null
     */
    public function stateFor(RememberedDevice $device): ?array
    {
        if (! $this->isAvailable() || ! $device->isVerified()) {
            return null;
        }

        /** @var Account $account */
        $account = $device->account;
        $phones = $this->phones($account)->count();

        return [
            'key' => (string) config('consultorio.push.public_key'),
            'this_phone' => PushSubscription::query()->where('remembered_device_id', $device->getKey())->exists(),
            'phones' => $phones,
            // Sin un celular con avisos, todo va por WhatsApp, diga lo que diga la preferencia.
            'whatsapp' => $account->whatsapp_notices || $phones === 0,
        ];
    }

    /**
     * El navegador de este celular aceptó mostrar los avisos.
     *
     * @param  array{endpoint: string, public_key: string, auth_token: string, content_encoding: string}  $subscription
     */
    public function subscribe(RememberedDevice $device, array $subscription): void
    {
        /** @var Account $account */
        $account = $device->account;
        $hash = hash('sha256', $subscription['endpoint']);

        // El primer celular con avisos empieza recibiéndolos también por WhatsApp: dejarlo es una decisión aparte.
        if ($this->phones($account)->doesntExist()) {
            $account->update(['whatsapp_notices' => true]);
        }

        // Un permiso anterior de este celular ya no sirve (el navegador da uno nuevo al volver a activarlos).
        PushSubscription::query()->where('remembered_device_id', $device->getKey())->where('endpoint_hash', '!=', $hash)->delete();

        PushSubscription::query()->updateOrCreate(['endpoint_hash' => $hash], $subscription + [
            'account_id' => $account->getKey(),
            'remembered_device_id' => $device->getKey(),
        ]);
    }

    /** "Dejar de recibirlos aquí", o el celular salió de Mi cuenta. */
    public function unsubscribe(RememberedDevice $device): void
    {
        PushSubscription::query()->where('remembered_device_id', $device->getKey())->delete();
    }

    /** Si los avisos también van por WhatsApp. Dejar WhatsApp pide un celular con los avisos activos: false si no hay. */
    public function receiveByWhatsApp(Account $account, bool $whatsapp): bool
    {
        if (! $whatsapp && $this->phones($account)->doesntExist()) {
            return false;
        }

        $account->update(['whatsapp_notices' => $whatsapp]);

        return true;
    }

    /**
     * Muestra el aviso en los celulares de la familia. True si llegó al menos a uno. Un celular cuyo navegador ya no
     * acepta avisos se quita; si no queda ninguno, los avisos vuelven a ir por WhatsApp.
     */
    public function send(WhatsappMessage $message): bool
    {
        /** @var Account|null $account */
        $account = $message->account;

        if ($account === null) {
            return false;
        }

        $notice = $this->text->for($message);
        $delivered = false;

        foreach ($this->phones($account)->get() as $phone) {
            $outcome = $this->sender->send($phone, $notice);

            match ($outcome) {
                PushOutcome::Delivered => $phone->update(['last_sent_at' => now()]),
                PushOutcome::Gone => $phone->delete(),
                PushOutcome::Failed => null,
            };

            $delivered = $delivered || $outcome === PushOutcome::Delivered;
        }

        return $delivered;
    }

    /** @return Builder<PushSubscription> */
    private function phones(Account $account): Builder
    {
        return PushSubscription::query()->whereBelongsTo($account)->active();
    }
}
