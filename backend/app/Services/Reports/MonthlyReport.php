<?php

namespace App\Services\Reports;

use App\Enums\UserRole;
use App\Filament\Pages\Summary;
use App\Models\Setting;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;

/**
 * El reporte del mes: el día 1 queda listo el del mes anterior y quien administra recibe el aviso en la campana,
 * con los enlaces al resumen, a la hoja para imprimir o guardar en PDF y al Excel (los mismos de Reportes, con las
 * fechas de ese mes). Se anota de qué mes ya se avisó: si el día 1 la laptop estuvo apagada, el aviso sale en
 * cuanto se prende, y una sola vez.
 */
final class MonthlyReport
{
    /** Desde esta hora del día 1, como la carga de cuotas: de madrugada nadie lo lee. */
    public const FROM = '06:00';

    /** El último mes del que ya se avisó ("2026-10"). Lo anota el sistema: no va al historial de cambios. */
    public const SETTING = 'reports.monthly_ready';

    /** El mes que toca avisar (su primer día), o null si ya se avisó o el día 1 todavía no llega a la hora. */
    public function due(): ?CarbonImmutable
    {
        $now = CarbonImmutable::now();

        if ($now->day === 1 && $now->lt($now->setTimeFromTimeString(self::FROM))) {
            return null;
        }

        $month = $now->startOfMonth()->subMonthNoOverflow();

        return Setting::get(self::SETTING) === $month->format('Y-m') ? null : $month;
    }

    public function isDue(): bool
    {
        return $this->due() !== null;
    }

    /**
     * Deja listo el reporte del mes anterior y avisa a quien administra. Un mes sin cobros ni atenciones (el primero,
     * con el sistema recién instalado) no tiene nada que contar: se da por hecho sin aviso.
     *
     * @return int A cuántas personas se avisó.
     */
    public function announce(): int
    {
        $month = $this->due();

        if ($month === null) {
            return 0;
        }

        $report = new EarningsReport(new ReportPeriod($month, $month->endOfMonth()));
        $admins = $report->paymentsCount() > 0 || $report->produced() > 0
            ? User::query()->where('is_active', true)->where('role', UserRole::Admin)->get()
            : collect();

        foreach ($admins as $admin) {
            $admin->notifyNow($this->notification($report)->toDatabase());
        }

        Setting::set(self::SETTING, $month->format('Y-m'));

        return $admins->count();
    }

    /** "El reporte de octubre está listo", con lo cobrado y los tres enlaces. Espera en la campana hasta que se abra. */
    private function notification(EarningsReport $report): Notification
    {
        $period = $report->period;
        $filters = ['period' => 'custom', 'from' => $period->from->toDateString(), 'to' => $period->to->toDateString()];
        $payments = $report->paymentsCount();

        return Notification::make()
            ->title('El reporte de '.$period->from->translatedFormat('F').' está listo')
            ->body($period->label().': '.($payments > 0
                ? 'se cobró '.Money::format($report->collected()).' en '.($payments === 1 ? '1 pago' : number_format($payments).' pagos').'.'
                : 'no se registraron cobros.'))
            ->icon(Heroicon::OutlinedChartBar)
            ->iconColor('primary')
            ->viewData(['monthlyReport' => $period->from->format('Y-m')])
            ->actions([
                Action::make('summary')
                    ->label('Ver el resumen')
                    ->url(Summary::getUrl(['filters' => $filters], panel: 'admin'))
                    ->button()
                    ->size(Size::Small)
                    ->markAsRead(),
                Action::make('print')
                    ->label('Imprimir o PDF')
                    ->url(Filament::getPanel('admin')->route('reports.earnings.print', $filters), shouldOpenInNewTab: true)
                    ->link()
                    ->size(Size::Small)
                    ->markAsRead(),
                Action::make('excel')
                    ->label('Descargar Excel')
                    ->url(Filament::getPanel('admin')->route('reports.earnings.excel', $filters))
                    ->link()
                    ->size(Size::Small)
                    ->markAsRead(),
            ]);
    }
}
