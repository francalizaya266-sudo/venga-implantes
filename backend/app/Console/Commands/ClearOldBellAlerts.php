<?php

namespace App\Console\Commands;

use App\Services\StaffAlerts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('campana:limpiar')]
#[Description('Saca de la campana lo de días anteriores y lo que ya se hizo, y borra lo leído hace más de un mes')]
class ClearOldBellAlerts extends Command
{
    public function handle(StaffAlerts $alerts): int
    {
        $read = $alerts->readEarlierDays();
        $done = $alerts->closeDone();
        $forgotten = $alerts->forgetOldRead();

        $this->info($read === 1 ? '1 aviso de días anteriores quedó leído.' : "{$read} avisos de días anteriores quedaron leídos.");
        $this->info($done === 1 ? '1 aviso ya hecho quedó leído.' : "{$done} avisos ya hechos quedaron leídos.");
        $this->info($forgotten === 1 ? '1 aviso leído hace más de un mes se borró.' : "{$forgotten} avisos leídos hace más de un mes se borraron.");

        return self::SUCCESS;
    }
}
