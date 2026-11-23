<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\RefreshesWithToday;
use App\Filament\Widgets\Reports\ReportChart;
use App\Models\User;
use App\Services\Dashboard\MonthFigures;
use App\Services\Reports\ReportAccess;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Lo cobrado día por día en los últimos 30 días, en Reportes debajo de los ingresos por mes: para ver la forma del
 * mes (los días flojos, los buenos). Antes iba al final de "Hoy", que ahora dice el mes en una línea: el inicio no
 * es un panel de estadísticas. Como los ingresos por mes, no depende del período elegido, sí del doctor. Solo para
 * el administrador. Usa las mismas cifras del mes, así que lo cobrado hoy sube al cobrar. Hoy va más claro: el día
 * sigue.
 */
class MonthTrendChart extends ReportChart
{
    use RefreshesWithToday;

    protected ?string $heading = 'Cobrado por día · últimos 30 días';

    protected string $unit = 'money';

    protected string $tone = 'money';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '220px';

    protected bool $lastIsInProgress = true;

    /** Mientras carga, un recuadro del mismo alto: la página no salta. */
    protected ?string $placeholderHeight = '20rem';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return ReportAccess::canView($user) && $user->isAdmin();
    }

    protected function chartData(): array
    {
        $daily = (new MonthFigures($this->reportDoctorId()))->figures()['daily_collected'];

        return [
            'labels' => array_map(fn (int $offset): string => ucfirst($this->day($offset)->translatedFormat('D j M')), array_keys($daily)),
            'datasets' => [['label' => 'Cobrado', 'data' => $daily]],
        ];
    }

    /** Lo de los 30 días y el mejor de ellos, sin contar hoy, que sigue. */
    protected function finding(): ?string
    {
        $values = array_map(fn ($value): float => (float) $value, $this->values());
        $best = collect(array_slice($values, 0, -1))->filter(fn (float $value) => $value > 0)->sortDesc()->keys()->first();

        return collect([
            'En total, '.Money::format(array_sum($values)).'.',
            $best === null ? null : 'El mejor día fue el '.$this->day((int) $best)->translatedFormat('l j \d\e F').', con '.Money::format($values[(int) $best]).'.',
        ])->filter()->implode(' ');
    }

    /** El día del gráfico en esa posición: el primero es de hace 29 días y el último, hoy. */
    private function day(int $offset): CarbonImmutable
    {
        return CarbonImmutable::today()->subDays(MonthFigures::TREND_DAYS - 1 - $offset);
    }
}
