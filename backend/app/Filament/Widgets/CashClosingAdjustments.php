<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Services\Billing\CashClosing;
use App\Services\Billing\PriceAdjustments;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Los precios cambiados y los descuentos del día, en un bloque propio del cierre: cuántos, por cuánto y el detalle
 * de cada uno (paciente, concepto, cómo llegó y cómo se cobró, motivo y quién). Antes solo quedaban por dentro, y el
 * cierre decía "Se cobró todo" aunque una limpieza de S/ 120 se hubiera cobrado a S/ 70.
 */
class CashClosingAdjustments extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.cash-closing-adjustments';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Payment::class) ?? false;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $closing = CashClosing::fromFilters($this->pageFilters ?? []);
        $rows = app(PriceAdjustments::class)->ofDay($closing->date, $closing->receivedBy);

        return [
            'heading' => 'Precios cambiados y descuentos '.($closing->date->isToday() ? 'de hoy' : 'del día'),
            'rows' => $rows,
            'totals' => PriceAdjustments::totals($rows),
        ];
    }
}
