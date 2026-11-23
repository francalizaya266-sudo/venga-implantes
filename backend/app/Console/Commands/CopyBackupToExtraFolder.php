<?php

namespace App\Console\Commands;

use App\Services\Backup\ExtraCopy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('respaldo:copia-extra')]
#[Description('Guarda la copia de seguridad más reciente también en la carpeta extra elegida en Copias de seguridad')]
class CopyBackupToExtraFolder extends Command
{
    public function handle(ExtraCopy $extra): int
    {
        $folder = $extra->folder();

        if ($folder === null) {
            $this->info('No hay una carpeta elegida para la copia extra.');

            return self::SUCCESS;
        }

        $saved = $extra->copyLatest();

        if ($saved === null) {
            $this->info('Todavía no hay copias que guardar.');

            return self::SUCCESS;
        }

        if (! $saved) {
            $this->error("No se pudo guardar la copia en {$folder}: {$extra->problem()}");

            return self::FAILURE;
        }

        $this->info("Copia guardada también en {$folder}");

        return self::SUCCESS;
    }
}
