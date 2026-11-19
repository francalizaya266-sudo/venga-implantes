<?php

namespace App\Console\Commands;

use App\Services\Database\DatabaseObjects;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bd:objetos')]
#[Description('Vuelve a crear las vistas, funciones, procedimientos y triggers de MySQL (database/sql/mysql)')]
class InstallDatabaseObjects extends Command
{
    public function handle(DatabaseObjects $objects): int
    {
        if (! $objects->isSupported()) {
            $this->warn('Solo para MySQL: esta base de datos es otra, así que no se cambió nada.');

            return self::SUCCESS;
        }

        $statements = $objects->install();
        $missing = $objects->missing();

        if ($missing !== []) {
            $this->error('Faltan: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Listos: %d funciones, %d vistas, %d procedimientos y %d triggers (%d sentencias).',
            count(DatabaseObjects::FUNCTIONS),
            count(DatabaseObjects::VIEWS),
            count(DatabaseObjects::PROCEDURES),
            count(DatabaseObjects::TRIGGERS),
            $statements,
        ));

        return self::SUCCESS;
    }
}
