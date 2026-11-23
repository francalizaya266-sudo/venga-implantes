<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aviso en la bandeja de WhatsApp: recepción lo envía con un clic o, con la API, sale solo. */
#[Fillable(['account_id', 'patient_id', 'appointment_id', 'template_key', 'phone', 'params', 'status', 'channel', 'due_at', 'opened_at', 'opened_by_user_id', 'sent_at', 'pushed_at', 'sent_by_user_id', 'provider_message_id', 'error'])]
class WhatsappMessage extends Model
{
    /** Cuánto espera un aviso a mostrarse en el celular de quien eligió solo el celular, antes de ir por WhatsApp. */
    public const PHONE_GRACE_MINUTES = 30;

    protected function casts(): array
    {
        return [
            'template_key' => MessageTemplateKey::class,
            'params' => 'array',
            'status' => MessageStatus::class,
            'channel' => MessageChannel::class,
            'due_at' => 'datetime',
            'opened_at' => 'datetime',
            'sent_at' => 'datetime',
            'pushed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Salió solo en la pantalla del celular del paciente, que pidió no recibir los avisos por WhatsApp. */
    public function wasShownOnPhone(): bool
    {
        /** @var MessageChannel $channel */
        $channel = $this->channel;

        return $channel === MessageChannel::WebPush;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::Pending);
    }

    /**
     * Avisos que ya toca enviar (los recordatorios esperan a su día). Las promociones van aparte: no compiten con
     * lo de las citas ni inflan el contador, y la API de WhatsApp no las envía sola.
     */
    #[Scope]
    protected function due(Builder $query): Builder
    {
        return $query->pending()
            ->where('template_key', '!=', MessageTemplateKey::CampaignAnnouncement)
            ->where(fn (Builder $q) => $q->whereNull('due_at')->orWhere('due_at', '<=', now()))
            ->whereNot(fn (Builder $q) => $this->onlyOnPhone($q));
    }

    /**
     * Avisos que el paciente pidió recibir solo en la pantalla de su celular: no van por WhatsApp. Si deja de tener
     * un celular con los avisos activos (salió, lo cambió), o el aviso no llegó a mostrarse en su celular en media
     * hora, vuelve a "Por enviar" para que salga por WhatsApp.
     */
    #[Scope]
    protected function onlyOnPhone(Builder $query): Builder
    {
        $since = now()->subMinutes(self::PHONE_GRACE_MINUTES);

        return $query
            ->whereIn('template_key', MessageTemplateKey::PHONE_NOTICES)
            // Sin NULL en la comparación: dentro del NOT de due(), un NULL dejaría fuera el aviso de todas formas.
            ->where(fn (Builder $fresh) => $fresh
                ->where(fn (Builder $later) => $later->whereNotNull('due_at')->where('due_at', '>', $since))
                ->orWhere(fn (Builder $now) => $now->whereNull('due_at')->where('created_at', '>', $since)))
            ->whereIn('account_id', Account::query()
                ->where('whatsapp_notices', false)
                ->whereIn('id', PushSubscription::query()->active()->select('account_id'))
                ->select('id'));
    }

    /** Promociones listas para enviar a quien aceptó recibirlas. */
    #[Scope]
    protected function promotions(Builder $query): Builder
    {
        return $query->pending()->where('template_key', MessageTemplateKey::CampaignAnnouncement);
    }

    /** Avisos que la API de WhatsApp no pudo enviar en los últimos días. */
    #[Scope]
    protected function failedRecently(Builder $query, int $days = 7): Builder
    {
        return $query->where('status', MessageStatus::Failed)->where('updated_at', '>=', now()->subDays($days));
    }
}
