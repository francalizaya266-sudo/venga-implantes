<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cargo al paciente por lo realizado. No se borra: se anula. */
#[Fillable(['patient_id', 'appointment_id', 'consultation_id', 'treatment_plan_item_id', 'treatment_id', 'doctor_id', 'description', 'amount', 'discount', 'discount_reason', 'suggested_amount', 'original_amount', 'original_discount', 'adjustment_reason', 'adjusted_by_user_id', 'adjusted_at', 'campaign_id', 'charged_at', 'created_by_user_id', 'voided_at', 'voided_by_user_id', 'void_reason'])]
class Charge extends Model
{
    /** @use HasFactory<ChargeFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'suggested_amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'original_discount' => 'decimal:2',
            'adjusted_at' => 'datetime',
            'charged_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function planItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class, 'treatment_plan_item_id');
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    /** Quien cambió el precio o el descuento al cobrar, o agregó o quitó el concepto. */
    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by_user_id');
    }

    /** Lo que cuenta para el saldo: sin los anulados. */
    #[Scope]
    protected function valid(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    /** Monto a pagar: precio menos descuento. */
    public function net(): float
    {
        return round((float) $this->amount - (float) $this->discount, 2);
    }

    /**
     * Cuánto bajó (o, negativo, subió) el dentista el precio sugerido. En una sesión del plan esa diferencia
     * queda saldada: no pasa a las sesiones que siguen.
     */
    public function priceChange(): float
    {
        return $this->suggested_amount === null ? 0.0 : round((float) $this->suggested_amount - (float) $this->amount, 2);
    }

    /** Se agregó a mano al cobrar: no llegó a caja con un precio. */
    public function addedAtCheckout(): bool
    {
        return $this->adjusted_at !== null && $this->original_amount === null;
    }

    /** Lo que había que pagar por él cuando llegó a caja, antes de cambiarlo al cobrar (de uno agregado a mano, su precio). */
    public function originalNet(): float
    {
        if ($this->adjusted_at === null) {
            return $this->net();
        }

        return $this->addedAtCheckout()
            ? round((float) $this->amount, 2)
            : round((float) $this->original_amount - (float) $this->original_discount, 2);
    }
}
