<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Pages\Earnings;
use App\Filament\Support\StatComparison;
use App\Filament\Widgets\Concerns\ReadsReportFilters;
use App\Services\Dashboard\MonthFigures;
use App\Services\Reports\ReportPeriod;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * El período en cuatro números, cada uno comparado con el período anterior. En "Este mes", además, a cuánto
 * cerraría el mes si sigue al mismo ritmo y, con todos los doctores, el avance de la meta que se pone en Ajustes.
 * Antes estaban en "Hoy", que ahora dice el mes en una línea y lleva aquí.
 */
class SummaryStats extends StatsOverviewWidget
{
    use ReadsReportFilters;

    protected ?string $pollingInterval = null;

    /** Lo cobrado en el período, pedido una vez para el número, la meta y la proyección. */
    private ?float $collected = null;

    /** Con pocos días, las comparaciones van sin verde ni rojo: se dice por qué. */
    protected function getDescription(): ?string
    {
        return collect([
            $this->projection(),
            $this->reportPeriod()->isTooShortToJudge() ? 'Con tan pocos días, las diferencias todavía no dicen mucho.' : null,
        ])->filter()->implode(' ') ?: null;
    }

    protected function getStats(): array
    {
        $activity = $this->activity();
        $previousPeriod = $activity->period->previous();
        $before = $this->activity($previousPeriod);
        $count = fn (float $n) => number_format($n);

        return array_values(array_filter([
            StatComparison::make('Citas atendidas', $activity->attended(), $before->attended(), $previousPeriod, $count)
                ->icon('heroicon-o-check-badge'),
            StatComparison::make('Pacientes nuevos', $activity->newPatients(), $before->newPatients(), $previousPeriod, $count)
                ->icon('heroicon-o-user-plus'),
            // El detalle (por día, doctor, tratamiento y medio de pago) está en Ingresos, con el mismo período.
            StatComparison::make('Cobrado', $this->collected(), $this->earnings($previousPeriod)->collected(), $previousPeriod, Money::format(...))
                ->icon('heroicon-o-banknotes')
                ->url(Earnings::getUrl(['filters' => $this->pageFilters])),
            StatComparison::points('Inasistencias', $activity->noShowRate(), $before->noShowRate(), $previousPeriod, higherIsBetter: false)
                ->icon('heroicon-o-user-minus'),
            $this->goal(),
        ]));
    }

    private function collected(): float
    {
        return $this->collected ??= $this->earnings()->collected();
    }

    /** "Este mes": del 1 a hoy. La meta y la proyección son del mes en curso, no de otro período. */
    private function isThisMonth(): bool
    {
        $period = $this->reportPeriod();

        return $period->from->isSameDay(today()->startOfMonth()) && $period->to->isSameDay(today());
    }

    /**
     * A cuánto cerraría el mes si sigue así, contra la meta (con todos los doctores) o, si no hay, contra el mes
     * pasado. Nada los primeros días: un solo pago lo cambia todo.
     */
    private function projection(): ?string
    {
        if (! $this->isThisMonth()) {
            return null;
        }

        $projection = MonthFigures::projection($this->collected());

        if ($projection === null) {
            return null;
        }

        $goal = $this->reportDoctorId() === null ? MonthFigures::goalProgress($this->collected())['goal'] ?? null : null;
        $lastMonth = $this->earnings(ReportPeriod::fromFilters(['period' => 'last_month']))->collected();
        $against = match (true) {
            $goal !== null => ($projection >= $goal ? ', por encima' : ', por debajo').' de la meta de '.Money::short($goal),
            $lastMonth > 0 => '; '.today()->subMonthNoOverflow()->translatedFormat('F').' cerró en '.Money::format($lastMonth),
            default => '',
        };

        return 'A este ritmo, '.today()->translatedFormat('F').' cerraría en '.Money::format($projection).$against.'.';
    }

    /** La meta de cobro del mes, si se puso en Ajustes: es del consultorio entero, así que va con todos los doctores. */
    private function goal(): ?Stat
    {
        if (! $this->isThisMonth() || $this->reportDoctorId() !== null) {
            return null;
        }

        $progress = MonthFigures::goalProgress($this->collected());

        if ($progress === null) {
            return null;
        }

        $goal = Money::short($progress['goal']);
        $description = match (true) {
            $progress['remaining'] <= 0 => 'Cumplida la meta de '.$goal,
            $progress['days_left'] === 0 => 'De '.$goal.' · faltan '.Money::short($progress['remaining']).' y hoy cierra el mes',
            default => 'De '.$goal.' · faltan '.Money::short($progress['remaining']).' en '.$progress['days_left'].($progress['days_left'] === 1 ? ' día' : ' días')
                .($progress['on_track'] ? ', a buen ritmo' : ', por debajo del ritmo'),
        };

        return Stat::make('Meta del mes', $progress['percent'].' %')
            ->description($description)
            ->descriptionIcon($progress['on_track'] ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->color($progress['on_track'] ? 'success' : 'warning')
            ->icon('heroicon-o-flag');
    }
}
