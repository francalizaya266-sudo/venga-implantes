<?php

namespace Tests\Feature\Console\Commands;

use App\Services\Database\DatabaseObjects;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstallDatabaseObjectsTest extends TestCase
{
    public function test_each_sql_file_splits_into_whole_statements(): void
    {
        $statements = collect(DatabaseObjects::FILES)->mapWithKeys(fn (string $file) => [$file => DatabaseObjects::statements(DatabaseObjects::path($file))]);

        // Cada función, procedimiento y trigger va con su DROP … IF EXISTS antes; las vistas usan CREATE OR REPLACE.
        $this->assertSame(
            ['funciones.sql' => 12, 'vistas.sql' => 5, 'procedimientos.sql' => 4, 'triggers.sql' => 4],
            $statements->map(fn (array $sqls) => count($sqls))->all(),
        );

        $created = $statements->flatten()
            ->map(fn (string $sql) => preg_match('/^CREATE (?:OR REPLACE SQL SECURITY DEFINER )?(?:FUNCTION|VIEW|PROCEDURE|TRIGGER) (\w+)/', $sql, $match) === 1 ? $match[1] : null)
            ->filter()
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing([...DatabaseObjects::FUNCTIONS, ...DatabaseObjects::VIEWS, ...DatabaseObjects::PROCEDURES, ...DatabaseObjects::TRIGGERS], $created);

        foreach ($statements->flatten() as $sql) {
            $this->assertStringNotContainsString('DELIMITER', $sql);
            $this->assertStringEndsNotWith(';', $sql);
        }
    }

    public function test_outside_mysql_nothing_is_created_and_nothing_breaks(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->markTestSkipped('Prueba para SQLite: en MySQL los objetos sí se crean (MysqlDatabaseObjectsTest).');
        }

        $this->assertFalse((new DatabaseObjects)->isSupported());

        $this->artisan('bd:objetos')
            ->expectsOutputToContain('Solo para MySQL')
            ->assertSuccessful();
    }
}
