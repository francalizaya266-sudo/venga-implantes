<?php

namespace App\Services\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Las funciones, vistas, procedimientos y triggers de MySQL que están en database/sql/mysql. Los crea una migración
 * y se vuelven a crear con `php artisan bd:objetos` y al restaurar una copia (DROP TABLE payments se lleva sus
 * triggers). Solo en MySQL: la aplicación no depende de ellos y las pruebas con SQLite no los tienen.
 * Si se cambia un archivo, hace falta una migración nueva que llame otra vez a installIfAllowed().
 */
final class DatabaseObjects
{
    /** En este orden: las vistas usan funciones, los procedimientos usan vistas y los triggers usan funciones. */
    public const FILES = ['funciones.sql', 'vistas.sql', 'procedimientos.sql', 'triggers.sql'];

    public const FUNCTIONS = ['fn_ahora_lima', 'fn_hoy_lima', 'fn_saldo_paciente', 'fn_estado_cita', 'fn_medio_pago', 'fn_categoria_material'];

    public const VIEWS = ['v_saldo_paciente', 'v_agenda_del_dia', 'v_cargos_pendientes', 'v_deuda_vencida', 'v_consumo_materiales'];

    public const PROCEDURES = ['sp_cierre_caja', 'sp_reporte_mensual_ingresos'];

    public const TRIGGERS = ['trg_pagos_auditar_cambio', 'trg_pagos_auditar_borrado'];

    /**
     * Lo que responde MySQL cuando el usuario de la base no puede crear funciones o triggers: en un hosting compartido
     * (Hostinger) el usuario no tiene SUPER y el registro binario está activo (1419, 1418), o le faltan permisos (1044,
     * 1142, 1227, 1370).
     */
    private const PERMISSION_ERRORS = [1044, 1142, 1227, 1370, 1418, 1419];

    /** Donde los triggers anotan lo que se cambió o borró de un pago por fuera de la aplicación. */
    public const AUDIT_TABLE = 'payment_audit_logs';

    private Connection $connection;

    public function __construct()
    {
        $this->connection = DB::connection();
    }

    public function isSupported(): bool
    {
        return $this->connection->getDriverName() === 'mysql';
    }

    /**
     * Crea (o vuelve a crear) todos los objetos. En otro motor no hace nada.
     *
     * @return int Sentencias ejecutadas
     */
    public function install(): int
    {
        if (! $this->isSupported()) {
            return 0;
        }

        $executed = 0;

        foreach (self::FILES as $file) {
            foreach (self::statements(self::path($file)) as $sql) {
                $this->connection->unprepared($sql);
                $executed++;
            }
        }

        return $executed;
    }

    /**
     * Como install(), pero si la base no deja crearlos por permisos no detiene la migración ni la restauración de una
     * copia: la aplicación no depende de ellos (son para consultar la base por fuera y para la bitácora de pagos).
     *
     * @return string|null Por qué no se crearon, para avisarlo; null si se crearon o si la base no es MySQL.
     */
    public function installIfAllowed(): ?string
    {
        try {
            $this->install();

            return null;
        } catch (QueryException $e) {
            if (! self::isPermissionError($e)) {
                throw $e;
            }

            $reason = 'La base de datos no deja crear las vistas, funciones, procedimientos y triggers de MySQL '
                .'(pasa en los hostings compartidos). El sistema funciona igual; solo faltan esos reportes y la '
                .'bitácora de pagos hechos por fuera del sistema. MySQL dijo: '.($e->errorInfo[2] ?? $e->getMessage());
            Log::warning($reason);

            return $reason;
        }
    }

    public static function isPermissionError(QueryException $e): bool
    {
        return in_array((int) ($e->errorInfo[1] ?? 0), self::PERMISSION_ERRORS, true);
    }

    /** Quita todos los objetos, del último al primero. */
    public function drop(): void
    {
        if (! $this->isSupported()) {
            return;
        }

        foreach (self::TRIGGERS as $trigger) {
            $this->connection->unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
        }

        foreach (self::PROCEDURES as $procedure) {
            $this->connection->unprepared("DROP PROCEDURE IF EXISTS `{$procedure}`");
        }

        foreach (array_reverse(self::VIEWS) as $view) {
            $this->connection->unprepared("DROP VIEW IF EXISTS `{$view}`");
        }

        foreach (self::FUNCTIONS as $function) {
            $this->connection->unprepared("DROP FUNCTION IF EXISTS `{$function}`");
        }
    }

    /**
     * Los objetos que deberían estar en esta base y no están.
     *
     * @return list<string>
     */
    public function missing(): array
    {
        if (! $this->isSupported()) {
            return [];
        }

        $schema = $this->connection->getDatabaseName();
        // Con alias: MySQL 8 devuelve en mayúsculas los nombres de las columnas de information_schema.
        $names = fn (string $table, string $schemaColumn, string $nameColumn) => $this->connection->table("information_schema.{$table}")
            ->where($schemaColumn, $schema)
            ->selectRaw("{$nameColumn} as name")
            ->pluck('name')
            ->all();

        $routines = $names('routines', 'routine_schema', 'routine_name');
        $views = $names('views', 'table_schema', 'table_name');
        $triggers = $names('triggers', 'trigger_schema', 'trigger_name');

        return array_values([
            ...array_diff([...self::FUNCTIONS, ...self::PROCEDURES], $routines),
            ...array_diff(self::VIEWS, $views),
            ...array_diff(self::TRIGGERS, $triggers),
        ]);
    }

    /**
     * Lo que anotaron los triggers, para guardarlo antes de restaurar una copia.
     *
     * @return list<array<string, mixed>>
     */
    public function auditTrail(): array
    {
        if (! $this->isSupported() || ! $this->connection->getSchemaBuilder()->hasTable(self::AUDIT_TABLE)) {
            return [];
        }

        return $this->connection->table(self::AUDIT_TABLE)->orderBy('id')->get()->map(fn (object $row) => (array) $row)->all();
    }

    /**
     * Después de restaurar, vuelve a poner lo anotado que la copia no tenía: si no, restaurar borraría el rastro de
     * lo que pasó después de la copia (por ejemplo, el pago que alguien borró y que motivó la restauración).
     *
     * @param  list<array<string, mixed>>  $rows  Lo que devolvió auditTrail() antes de restaurar.
     * @return int Anotaciones que se volvieron a poner
     */
    public function restoreAuditTrail(array $rows): int
    {
        $restored = 0;

        foreach (array_chunk($rows, 100) as $chunk) {
            $restored += $this->connection->table(self::AUDIT_TABLE)->insertOrIgnore($chunk);
        }

        return $restored;
    }

    /**
     * Las sentencias de un archivo escrito para la consola de mysql: respeta DELIMITER y salta los comentarios que
     * van solos entre una sentencia y otra. Una sentencia termina en la línea que acaba con el delimitador.
     *
     * @return list<string>
     */
    public static function statements(string $path): array
    {
        $delimiter = ';';
        $statements = [];
        $buffer = '';

        foreach (file($path) ?: [] as $line) {
            $trimmed = trim($line);

            if ($buffer === '' && ($trimmed === '' || str_starts_with($trimmed, '--'))) {
                continue;
            }

            if ($buffer === '' && preg_match('/^DELIMITER\s+(\S+)$/i', $trimmed, $match) === 1) {
                $delimiter = $match[1];

                continue;
            }

            $buffer .= $line;

            if (str_ends_with($trimmed, $delimiter)) {
                $statements[] = trim(substr(rtrim($buffer), 0, -strlen($delimiter)));
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            throw new RuntimeException(basename($path).": la última sentencia no termina en {$delimiter}");
        }

        return $statements;
    }

    public static function path(string $file): string
    {
        return database_path('sql/mysql/'.$file);
    }
}
