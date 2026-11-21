<?php

namespace App\Console\Commands;

use App\Services\StaffAlerts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('campana:limpiar')]
#[Description('Da por leídos los avisos de la campana de días anteriores: lo que pedían sigue en "Hoy" y en sus listas')]
class ClearOldBellAlerts extends Command
{
    public function handle(StaffAlerts $alerts): int
    {
        $read = $alerts->readEarlierDays();

        $this->info($read === 1 ? '1 aviso de días anteriores quedó leído.' : "{$read} avisos de días anteriores quedaron leídos.");

        return self::SUCCESS;
    }
}
