<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\RefreshesWithToday;
use App\Filament\Pages\Summary;
use App\Models\User;
use App\Services\Dashboard\MonthFigures;
use App\Services\Reports\ReportAccess;
use App\Support\Money;
use Filament\Widgets\Widget;

/**
 * Cómo va el mes, en una sola línea al final de "Hoy": "Este mes: S/ 8,639.00 cobrados · 64 % de la meta · Ver
 * reportes". Lo demás (lo realizado, las citas, las inasistencias, a cuánto cerraría el mes, la meta al detalle y
 * lo cobrado día por día) está en Reportes, con el filtro "Este mes": aquí se repetía y alargaba la pantalla. Lo ve
 * quien ve los reportes; un doctor, lo de sus citas, y la meta es del consultorio entero. Se actualiza con "Hoy":
 * al cobrar, la cifra sube aquí también.
 */
class MonthOverview extends Widget
{
    use RefreshesWithToday;

    protected string $view = 'filament.widgets.month-overview';

    protected int|string|array $columnSpan = 'full';

    /** Mientras carga, un recuadro del mismo alto: la página no salta. */
    protected ?string $placeholderHeight = '3rem';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return ReportAccess::canView($user);
    }

    /** @return array{label: string, collected: string, goal: int|null, url: string} */
    protected function getViewData(): array
    {
        $doctorId = $this->doctorId();
        $collected = (new MonthFigures($doctorId))->figures()['collected'];

        return [
            'label' => $doctorId === null ? 'Este mes' : 'Este mes, de tus citas',
            'collected' => Money::format($collected),
            'goal' => $doctorId === null ? (MonthFigures::goalProgress($collected)['percent'] ?? null) : null,
            // Reportes en "Este mes" y con lo mismo que se ve aquí: si no, abre el último período que se miró allí.
            'url' => Summary::getUrl(['filters' => array_filter(['period' => 'month', 'doctor' => $doctorId])]),
        ];
    }

    /** Un doctor ve lo suyo; la administración, todo el consultorio. */
    private function doctorId(): ?int
    {
        /** @var User $user */
        $user = auth()->user();

        return ReportAccess::doctorFor($user, null);
    }
}
