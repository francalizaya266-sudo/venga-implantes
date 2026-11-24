<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackup;
use App\Services\Database\DatabaseObjects;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('respaldo:restaurar {archivo? : Nombre de la copia, por ejemplo respaldo_2026-10-23_030000.sql.gz (sin nombre, la más reciente)} {--force : No preguntar}')]
#[Description('Reemplaza toda la base de datos por una copia de seguridad')]
class RestoreBackup extends Command
{
    public function handle(DatabaseBackup $backup, DatabaseObjects $objects): int
    {
        $file = $this->argument('archivo') ?? ($backup->list()[0]['file'] ?? null);

        if ($file === null || ! DatabaseBackup::isBackupName(basename($file))) {
            $this->error('No hay ninguna copia con ese nombre en storage/app/private/'.DatabaseBackup::DIRECTORY.'.');

            return self::FAILURE;
        }

        $name = DatabaseBackup::DIRECTORY.'/'.basename($file);

        if (! $this->option('force') && ! $this->confirm("Se reemplazará TODA la base de datos por la copia {$file}. Lo registrado después de esa copia se pierde. ¿Seguir?")) {
            $this->line('No se cambió nada.');

            return self::FAILURE;
        }

        $auditTrail = $objects->auditTrail();
        $statements = $backup->restore($name);

        $this->info("Base de datos restaurada desde {$file} ({$statements} sentencias).");

        // La copia guarda tablas y datos; los triggers se fueron con DROP TABLE y las vistas y funciones son del código.
        $notInstalled = $objects->installIfAllowed();

        if ($notInstalled !== null) {
            $this->warn($notInstalled);
        } elseif ($objects->isSupported()) {
            $this->info('También se volvieron a crear las vistas, funciones, procedimientos y triggers de MySQL.');
        }

        if (($kept = $objects->restoreAuditTrail($auditTrail)) > 0) {
            $this->info("La bitácora de pagos conserva {$kept} anotaciones de después de la copia.");
        }

        return self::SUCCESS;
    }
}
