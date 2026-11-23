<?php

namespace App\Console\Commands;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Models\Account;
use App\Models\PushSubscription;
use App\Models\WhatsappMessage;
use App\Services\Notices\PhoneNotices;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

#[Signature('avisos:enviar-al-celular {--limit=100 : Máximo de avisos por pasada}')]
#[Description('Muestra en el celular de quien los activó en Mi cuenta los avisos de sus citas y cuotas que ya tocan')]
class SendPhoneNotices extends Command
{
    /** Un aviso que lleva más de un día esperando ya no se muestra en el celular: ya no sirve. */
    private const FRESH_HOURS = 24;

    public function handle(PhoneNotices $notices): int
    {
        if (! $notices->isAvailable()) {
            $this->line('Los avisos en el celular no están activados: faltan las claves (php artisan avisos:crear-claves).');

            return self::SUCCESS;
        }

        $shown = 0;

        foreach ($this->dueNotices() as $message) {
            if (! $notices->send($message)) {
                continue;
            }

            /** @var Account $account */
            $account = $message->account;
            $message->forceFill(['pushed_at' => now()]);

            // Quien eligió recibirlos solo en el celular ya lo tiene: no queda por enviar por WhatsApp.
            /** @var MessageStatus $status */
            $status = $message->status;

            if ($status === MessageStatus::Pending && ! $account->whatsapp_notices) {
                $message->forceFill(['status' => MessageStatus::Sent, 'sent_at' => now(), 'channel' => MessageChannel::WebPush]);
            }

            $message->save();
            $shown++;
        }

        $this->info("Avisos mostrados en el celular: {$shown}");

        return self::SUCCESS;
    }

    /**
     * Los avisos de citas y cuotas que ya tocan, de familias con un celular con avisos, que todavía no se mostraron
     * ahí. También los que recepción ya envió por WhatsApp: quien tiene los dos, los recibe por los dos. Lo de una
     * cita que ya pasó no se muestra.
     *
     * @return Collection<int, WhatsappMessage>
     */
    private function dueNotices(): Collection
    {
        $since = now()->subHours(self::FRESH_HOURS);

        return WhatsappMessage::query()
            ->whereIn('status', [MessageStatus::Pending, MessageStatus::Sent])
            ->whereIn('template_key', MessageTemplateKey::PHONE_NOTICES)
            ->whereNull('pushed_at')
            ->where(fn (Builder $q) => $q->whereNull('due_at')->orWhere('due_at', '<=', now()))
            ->where(fn (Builder $q) => $q->where('due_at', '>=', $since)->orWhere(fn (Builder $q) => $q->whereNull('due_at')->where('created_at', '>=', $since)))
            ->where(fn (Builder $q) => $q->whereNull('appointment_id')->orWhereHas('appointment', fn (Builder $a) => $a->where('starts_at', '>', now())))
            ->whereIn('account_id', PushSubscription::query()->active()->select('account_id'))
            ->with(['account', 'appointment.doctor'])
            ->oldest('id')
            ->limit((int) $this->option('limit'))
            ->get();
    }
}
