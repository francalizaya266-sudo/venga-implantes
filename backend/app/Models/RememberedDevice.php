<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dispositivo recordado: al tocar el enlace personal (verificado) o al reservar la primera cita con un número que el
 * consultorio no tenía (sin verificar hasta que toque un enlace).
 */
#[Fillable(['account_id', 'token_hash', 'user_agent', 'ip_address', 'last_used_at', 'expires_at', 'revoked_at', 'verified_at'])]
#[Hidden(['token_hash'])]
class RememberedDevice extends Model
{
    use MassPrunable;

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /** ¿Tocó desde aquí un enlace que llegó al WhatsApp del número? */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Se eliminan 30 días después de vencer o ser revocados. */
    public function prunable(): Builder
    {
        return static::query()
            ->where('expires_at', '<', now()->subDays(30))
            ->orWhere('revoked_at', '<', now()->subDays(30));
    }

    #[Scope]
    protected function usable(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
