<?php

namespace App\Models;

use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avisos en el celular: el navegador de un celular recordado aceptó mostrar los avisos de sus citas y cuotas en la
 * pantalla. Vale mientras el celular siga recordado.
 */
#[Fillable(['account_id', 'remembered_device_id', 'endpoint', 'endpoint_hash', 'public_key', 'auth_token', 'content_encoding', 'last_sent_at'])]
#[Hidden(['endpoint', 'public_key', 'auth_token'])]
class PushSubscription extends Model
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_sent_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(RememberedDevice::class, 'remembered_device_id');
    }

    /** Los que todavía llegan: el celular sigue recordado y la cuenta no está bloqueada. */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query
            ->whereIn('remembered_device_id', RememberedDevice::query()->usable()->select('id'))
            ->whereHas('account', fn (Builder $accounts) => $accounts->whereNull('blocked_at'));
    }
}
