<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\RefreshesWithToday;
use App\Filament\Pages\Earnings;
use App\Filament\Pages\OwingBalances;
use App\Filament\Pages\Summary;
use App\Filament\Support\StatComparison;
use App\Models\User;
use App\Services\Dashboard\MonthFigures;
use App\Services\Reports\ReportAccess;
use App\Support\Money;
use Filament\Schemas\Components\Component;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Cómo va el mes, sin entrar a Reportes: lo mismo que Resumen e Ingresos con el filtro "Este mes",
 * y a cuánto cerraría el mes si sigue al mismo ritmo. Va al final de "Hoy": primero lo del día.
 * Lo ve quien ve los reportes; un doctor, solo lo suyo ("Mis números de noviembre"). El administrador ve lo de
 * todos y, si además atiende, una línea con lo de sus citas. La meta y lo que se debe son del consultorio entero.
 * Con lo de todos, lo cobrado día por día va en su gráfico, debajo (MonthTrendChart). Se actualiza con "Hoy" (al
 * cobrar, "Cobrado" sube aquí también) y sus enlaces abren Ingresos y Resumen en este mes, no en el último
 * período que se miró allí.
 */
class MonthOverview extends StatsOverviewWidget
{
    use RefreshesWithToday;

    /** Mientras carga, un recuadro del mismo alto: la página no salta. */
    protected ?string $placeholderHeight = '18rem';

    protected ?string $pollingInterval = null;

    /** De a dos en el celular (las cifras se achican), de a tres en pantallas anchas. */
    protected int|array|null $columns = ['default' => 2, '@xl' => 3, '!@lg' => 3];

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return ReportAccess::canView($user);
    }

    public function getSectionContentComponent(): Component
    {
        return parent::getSectionContentComponent()->extraAttributes(['class' => 'dash-month']);
    }

    protected function getHeading(): ?string
    {
        return $this->doctorId() !== null
            ? 'Mis números de '.today()->translatedFormat('F')
            : ucfirst(today()->translatedFormat('F')).' hasta hoy';
    }

    protected function getDescription(): ?string
    {
        return collect([
            $this->doctorId() !== null ? 'Solo tus citas y lo que se cobró de ellas.' : null,
            $this->projection(),
            $this->ownShare(),
            'Comparado con los mismos días del mes pasado.',
            $this->isEarly() ? 'Con tan pocos días, las diferencias todavía no dicen mucho.' : null,
        ])->filter()->implode(' ');
    }

    /**
     * A cuánto cerraría el mes si sigue así, contra la meta o, si no hay, contra el mes pasado.
     * Nada los primeros días: un solo pago lo cambia todo.
     */
    private function projection(): ?string
    {
        $figures = (new MonthFigures($this->doctorId()))->figures();
        $projection = MonthFigures::projection($figures['collected']);

        if ($projection === null) {
            return null;
        }

        $goal = $this->doctorId() === null ? MonthFigures::goalProgress($figures['collected'])['goal'] ?? null : null;
        $against = match (true) {
            $goal !== null => ($projection >= $goal ? ', por encima' : ', por debajo').' de la meta de '.Money::short($goal),
            $figures['collected_last_month'] > 0 => '; '.today()->subMonthNoOverflow()->translatedFormat('F').' cerró en '.Money::format($figures['collected_last_month']),
            default => '',
        };

        return 'A este ritmo, '.today()->translatedFormat('F').' cerraría en '.Money::format($projection).$against.'.';
    }

    /** Al administrador que además atiende, lo suyo dentro de lo de todos: "De tus citas: S/ 300.00 cobrados y 4 atendidas." */
    private function ownShare(): ?string
    {
        /** @var User $user */
        $user = auth()->user();

        if ($this->doctorId() !== null || $user->doctorId() === null) {
            return null;
        }

        $own = (new MonthFigures($user->doctorId()))->figures();

        return 'De tus citas: '.Money::format($own['collected']).' cobrados y '.$own['attended'].($own['attended'] === 1 ? ' atendida.' : ' atendidas.');
    }

    protected function getStats(): array
    {
        $month = new MonthFigures($this->doctorId());
        $figures = $month->figures();
        $before = $month->previousPeriod;
        $count = fn (float $n) => number_format($n);

        $comparisons = [
            StatComparison::make('Cobrado', $figures['collected'], $figures['collected_before'], $before, Money::format(...))
                ->icon('heroicon-o-banknotes')
                ->chart(MonthTrendChart::canView() ? null : $figures['daily_collected'])
                ->url($this->reportUrl(Earnings::class)),
            StatComparison::make('Realizado', $figures['produced'], $figures['produced_before'], $before, Money::format(...))
                ->icon('heroicon-o-clipboard-document-check')
                ->url($this->reportUrl(Earnings::class)),
            StatComparison::make('Citas atendidas', $figures['attended'], $figures['attended_before'], $before, $count)
                ->icon('heroicon-o-check-badge')
                ->url($this->reportUrl(Summary::class)),
            StatComparison::points('Inasistencias', $figures['no_show_rate'], $figures['no_show_rate_before'], $before, higherIsBetter: false)
                ->icon('heroicon-o-user-minus')
                ->url($this->reportUrl(Summary::class)),
        ];

        return array_values(array_filter([
            ...$comparisons,
            $this->doctorId() === null ? $this->outstanding() : null,
            $this->doctorId() === null ? $this->goal($figures['collected']) : null,
        ]));
    }

    /**
     * Ingresos o Resumen en "Este mes" y con lo mismo que se ve aquí (todos o un doctor): si no, abren el último
     * período y doctor que se eligieron allí y los números no coinciden.
     *
     * @param  class-string<Earnings|Summary>  $page
     */
    private function reportUrl(string $page): string
    {
        return $page::getUrl(['filters' => array_filter(['period' => 'month', 'doctor' => $this->doctorId()])]);
    }

    private function isEarly(): bool
    {
        return today()->day < MonthFigures::SETTLED_FROM_DAY;
    }

    /** Un doctor ve lo suyo; la administración, todo el consultorio. */
    private function doctorId(): ?int
    {
        /** @var User $user */
        $user = auth()->user();

        return ReportAccess::doctorFor($user, null);
    }

    private function outstanding(): Stat
    {
        return Stat::make('Por cobrar (a hoy)', Money::format(MonthFigures::outstanding()))
            ->description('Saldos pendientes de todos los pacientes')
            ->icon('heroicon-o-scale')
            ->url(OwingBalances::getUrl());
    }

    /** La meta de cobro del mes, si se puso en Ajustes. */
    private function goal(float $collected): ?Stat
    {
        $progress = MonthFigures::goalProgress($collected);

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
