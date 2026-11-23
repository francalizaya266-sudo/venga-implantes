<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackup;
use App\Services\Backup\ExtraCopy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('respaldo:crear')]
#[Description('Guarda una copia de seguridad de la base de datos y borra las que pasaron el plazo')]
class CreateBackup extends Command
{
    public function handle(DatabaseBackup $backup, ExtraCopy $extra): int
    {
        $name = $backup->create();

        $this->info("Copia guardada: storage/app/private/{$name}");

        // La copia extra en otra carpeta, si se eligió una. Si falla, la del día igual quedó: se avisa en Hoy.
        if ($extra->folder() !== null && $extra->copy($name)) {
            $this->info("También en {$extra->folder()}");
        } elseif ($extra->folder() !== null) {
            $this->warn("No se pudo guardar también en {$extra->folder()}: {$extra->problem()}");
        }

        return self::SUCCESS;
    }
}
