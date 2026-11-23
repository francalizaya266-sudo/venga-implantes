<?php

namespace App\Services\Billing;

use App\Models\Charge;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Lo que se cambió al cobrar: un precio distinto al que traía el cargo (el del tratamiento, el del plan o el que fijó
 * el doctor), un descuento, un concepto agregado o quitado a mano. También los cargos que se anularon a mano desde la
 * cuenta del paciente, que bajan lo que se cobra igual que un precio cambiado. Cada cambio queda con su motivo, quién
 * y cuándo. Lo usan el cierre del día (en pantalla y en la hoja), la alerta a quien administra y la constancia de pago.
 */
final class PriceAdjustments
{
    public const PRICE = 'price';

    public const DISCOUNT = 'discount';

    public const ADDED = 'added';

    public const REMOVED = 'removed';

    /**
     * Los cobros de un día que tuvieron un precio cambiado, un descuento o un concepto agregado o quitado al cobrar, y
     * los cargos que se anularon a mano ese día, en el orden en que se hicieron. Con $by, solo los que hizo esa persona.
     * Lo que anula el sistema al corregirse una atención no cuenta: se vuelve a cargar.
     *
     * @return Collection<int, array{charge: Charge, patient: string, concept: string, kind: string, original: float|null, price: float, discount: float, charged: float, lowered: float, reason: string|null, by: string, at: CarbonInterface}>
     */
    public function ofDay(CarbonInterface $date, ?int $by = null): Collection
    {
        $day = [$date->copy()->startOfDay(), $date->copy()->endOfDay()];

        return Charge::query()
            ->with(['patient', 'adjustedBy', 'voidedBy'])
            ->where(fn (Builder $query) => $query
                ->whereBetween('adjusted_at', $day)
                ->orWhere(fn (Builder $voided) => self::voidedByHand($voided)->whereBetween('voided_at', $day)))
            ->when($by, fn (Builder $query, int $id) => $query->where(fn (Builder $mine) => $mine
                ->where('adjusted_by_user_id', $id)
                ->orWhere(fn (Builder $voided) => self::voidedByHand($voided)->where('voided_by_user_id', $id))))
            ->get()
            ->map(fn (Charge $charge) => self::row($charge))
            ->sortBy(fn (array $row) => [$row['at']->getTimestamp(), $row['charge']->getKey()])
            ->values();
    }

    /** Anulado por una persona desde la cuenta del paciente, sin pasar por la ventana Cobrar. */
    private static function voidedByHand(Builder $query): Builder
    {
        return $query
            ->whereNull('adjusted_at')
            ->whereNotNull('voided_by_user_id')
            ->where('void_reason', '!=', BillingService::CORRECTED);
    }

    /**
     * Cuántos cambios hubo y cuánto se cobró de menos (y de más, si se subió algún precio) frente a lo que traían.
     *
     * @param  Collection<int, array{lowered: float}>  $rows
     * @return array{count: int, lowered: float, raised: float}
     */
    public static function totals(Collection $rows): array
    {
        return [
            'count' => $rows->count(),
            'lowered' => round((float) $rows->sum(fn (array $row) => max(0, $row['lowered'])), 2),
            'raised' => round((float) $rows->sum(fn (array $row) => max(0, -$row['lowered'])), 2),
        ];
    }

    /**
     * Un cambio, listo para mostrar.
     *
     * @return array{charge: Charge, patient: string, concept: string, kind: string, original: float|null, price: float, discount: float, charged: float, lowered: float, reason: string|null, by: string, at: CarbonInterface}
     */
    public static function row(Charge $charge): array
    {
        $charged = $charge->voided_at ? 0.0 : $charge->net();
        $atCheckout = $charge->adjusted_at !== null;

        return [
            'charge' => $charge,
            'patient' => $charge->patient?->full_name ?? '—',
            'concept' => $charge->description,
            'kind' => self::kind($charge),
            'original' => $charge->original_amount === null ? null : (float) $charge->original_amount,
            'price' => (float) $charge->amount,
            'discount' => (float) $charge->discount,
            'charged' => $charged,
            'lowered' => round($charge->originalNet() - $charged, 2),
            'reason' => $atCheckout ? $charge->adjustment_reason : $charge->void_reason,
            'by' => ($atCheckout ? $charge->adjustedBy : $charge->voidedBy)?->name ?? 'Alguien que ya no está',
            'at' => $atCheckout ? $charge->adjusted_at : $charge->voided_at,
        ];
    }

    /** Qué se hizo: se quitó, se agregó a mano, se cambió el precio o solo se puso (o cambió) el descuento. */
    public static function kind(Charge $charge): string
    {
        return match (true) {
            $charge->voided_at !== null => self::REMOVED,
            $charge->addedAtCheckout() => self::ADDED,
            abs((float) $charge->original_amount - (float) $charge->amount) >= 0.005 => self::PRICE,
            default => self::DISCOUNT,
        };
    }

    /**
     * El cambio en palabras: "Precio de S/ 120.00 a S/ 70.00", "Descuento de S/ 20.00 sobre S/ 120.00", "Agregado a
     * mano: S/ 30.00", "Se quitó al cobrar: era S/ 50.00" o "Se anuló el cargo: era S/ 120.00". Si además lleva
     * descuento, se dice.
     */
    public static function change(Charge $charge): string
    {
        $discount = (float) $charge->discount;
        $withDiscount = $discount > 0 ? ', con descuento de '.Money::format($discount) : '';

        return match (self::kind($charge)) {
            self::REMOVED => ($charge->void_reason === BillingService::REMOVED_AT_CHECKOUT ? 'Se quitó al cobrar' : 'Se anuló el cargo').': era '.Money::format($charge->originalNet()),
            self::ADDED => 'Agregado a mano: '.Money::format($charge->amount).$withDiscount,
            self::PRICE => 'Precio de '.Money::format($charge->original_amount).' a '.Money::format($charge->amount).$withDiscount,
            default => (float) $charge->original_discount > 0
                ? 'Descuento de '.Money::format($charge->original_discount).' a '.Money::format($discount)
                : 'Descuento de '.Money::format($discount).' sobre '.Money::format($charge->amount),
        };
    }

    /**
     * Para la constancia: el precio y el descuento de lo que se pagó, si no se cobró a su precio, con el motivo.
     * "Precio S/ 120.00, se cobró a S/ 70.00 (paciente frecuente)". Null si se cobró a su precio y sin descuento.
     */
    public static function receiptNote(Charge $charge): ?string
    {
        $discount = (float) $charge->discount;
        $priceChanged = $charge->original_amount !== null && abs((float) $charge->original_amount - (float) $charge->amount) >= 0.005;

        if (! $priceChanged && $discount <= 0) {
            return null;
        }

        $parts = $priceChanged
            ? ['Precio '.Money::format($charge->original_amount).', se cobró a '.Money::format($charge->amount)]
            : ['Precio '.Money::format($charge->amount)];

        if ($discount > 0) {
            $parts[] = 'descuento '.Money::format($discount);
        }

        $reason = $charge->adjustment_reason ?? ($discount > 0 ? $charge->discount_reason : null);

        return implode(' · ', $parts).(filled($reason) ? ' ('.$reason.')' : '');
    }
}
