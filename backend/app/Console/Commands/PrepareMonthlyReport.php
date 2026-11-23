<?php

namespace App\Console\Commands;

use App\Services\Reports\MonthlyReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reportes:preparar-mes')]
#[Description('Deja listo el reporte del mes anterior y avisa en la campana a quien administra (una vez por mes)')]
class PrepareMonthlyReport extends Command
{
    public function handle(MonthlyReport $report): int
    {
        $month = $report->due();

        if ($month === null) {
            $this->info('Nada por avisar: el reporte del mes anterior ya se avisó (o el día 1 aún no son las '.MonthlyReport::FROM.').');

            return self::SUCCESS;
        }

        $notified = $report->announce();

        $this->info('Reporte de '.$month->translatedFormat('F \d\e Y').': '.($notified === 1 ? 'se avisó a 1 persona.' : "se avisó a {$notified} personas."));

        return self::SUCCESS;
    }
}
