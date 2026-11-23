<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Concerns\ReadsReportFilters;
use App\Services\Reports\EarningsTables as Tables;
use App\Support\Money;
use Filament\Widgets\Widget;

/**
 * Las tablas por día, doctor, tratamiento y medio de pago (las mismas que salen al imprimir y en Excel).
 * Sobre la de los días, cuál fue el mejor: lo que antes decía el gráfico que repetía esa tabla.
 */
class EarningsTables extends Widget
{
    use ReadsReportFilters;

    protected string $view = 'filament.widgets.earnings-tables';

    protected int|string|array $columnSpan = 'full';

    /** @return array{tables: list<array<string, mixed>>, best: string|null, fullWidth: list<string>} */
    protected function getViewData(): array
    {
        $tables = Tables::for($this->earnings());

        return ['tables' => $tables, 'best' => $this->best(), 'fullWidth' => self::fullWidth($tables, ['period'])];
    }

    /**
     * Las tablas que van a todo el ancho: las anchas de por sí y la angosta que quedaría sola en su fila (las angostas
     * van de a dos), para no dejar media fila vacía. Lo usan Ingresos y el cierre del día.
     *
     * @param  list<array<string, mixed>>  $tables
     * @param  list<string>  $wide
     * @return list<string>
     */
    public static function fullWidth(array $tables, array $wide): array
    {
        $fullWidth = [];
        $narrow = [];

        foreach ([...array_column($tables, 'key'), null] as $key) {
            if ($key !== null && ! in_array($key, $wide, true)) {
                $narrow[] = $key;

                continue;
            }

            if (count($narrow) % 2 === 1) {
                $fullWidth[] = end($narrow);
            }

            $narrow = [];

            if ($key !== null) {
                $fullWidth[] = $key;
            }
        }

        return $fullWidth;
    }

    private function best(): ?string
    {
        $best = collect($this->earnings()->byPeriod())->sortByDesc('collected')->first();

        if (! $best || $best['collected'] <= 0) {
            return null;
        }

        return ($this->reportPeriod()->groupsByMonth() ? 'El mejor mes: ' : 'El mejor día: ').$best['label'].' ('.Money::format($best['collected']).')';
    }
}
