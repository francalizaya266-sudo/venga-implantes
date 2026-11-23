<?php

namespace App\Models;

use App\Enums\PatientRelationship;
use App\Models\Concerns\Auditable;
use App\Support\Phone;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Cuenta de paciente: un número de celular con uno o varios pacientes a cargo. */
#[Fillable(['phone', 'phone_verified_at', 'accepts_promotions', 'promotions_consent_at', 'whatsapp_notices', 'privacy_accepted_at', 'privacy_version', 'blocked_at', 'blocked_reason', 'blocked_by_user_id', 'web_booking_limit', 'last_seen_at'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'accepts_promotions' => 'boolean',
            'promotions_consent_at' => 'datetime',
            'whatsapp_notices' => 'boolean',
            'privacy_accepted_at' => 'datetime',
            'blocked_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'web_booking_limit' => 'integer',
        ];
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function rememberedDevices(): HasMany
    {
        return $this->hasMany(RememberedDevice::class);
    }

    /** Los celulares donde el paciente activó los avisos en la pantalla. */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function magicLinks(): HasMany
    {
        return $this->hasMany(MagicLink::class);
    }

    public function appointments(): HasManyThrough
    {
        return $this->hasManyThrough(Appointment::class, Patient::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by_user_id');
    }

    /** El paciente titular del celular ("para mí"). */
    public function holder(): HasOne
    {
        return $this->hasOne(Patient::class)->where('relationship', PatientRelationship::Self);
    }

    /**
     * Quienes están "por confirmar" en este celular: todas sus citas son reservas de la web hechas sin entrar desde
     * el celular de la familia, y nadie confirmó ninguna; o, sin citas, solo se anotaron así en la lista de espera
     * ("Avísame si se libera un horario"). No cuentan como familia hasta que el dueño del número confirme una cita
     * suya. Mientras nadie del celular tenga una cita reconocida (su primera reserva), no hay familia que cuidar: no
     * hay nadie por confirmar.
     *
     * @return list<int> Los id de esos pacientes.
     */
    public function guestIds(): array
    {
        $recognized = fn (Builder $appointments) => $appointments->recognized();
        $askedFromAnotherPhone = fn (Builder $entries) => $entries->where('from_web', true)->where('from_verified_device', false);

        if ($this->patients()->whereHas('appointments', $recognized)->doesntExist()) {
            return [];
        }

        return $this->patients()
            ->where(fn (Builder $guests) => $guests
                ->where(fn (Builder $booked) => $booked->whereHas('appointments')->whereDoesntHave('appointments', $recognized))
                // Lo que anotó el consultorio, o la familia desde su celular, ya lo hace familia.
                ->orWhere(fn (Builder $waiting) => $waiting
                    ->whereDoesntHave('appointments')
                    ->whereHas('waitlistEntries', $askedFromAnotherPhone)
                    ->whereDoesntHave('waitlistEntries', fn (Builder $entries) => $entries->whereNot($askedFromAnotherPhone))))
            ->pluck('id')
            ->all();
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function isVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    /** Tope de citas web futuras para todo el celular. */
    public function webBookingLimit(): int
    {
        return $this->web_booking_limit ?? (int) Setting::get('booking.max_web_appointments_per_phone', 6);
    }

    public static function findByPhone(string $phone): ?self
    {
        return static::where('phone', Phone::normalize($phone))->first();
    }
}
