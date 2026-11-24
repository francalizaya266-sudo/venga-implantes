<?php

namespace Tests\Unit\Services\Database;

use App\Services\Database\DatabaseObjects;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use PDOException;
use Tests\TestCase;

/** En un hosting compartido, crear funciones y triggers puede estar prohibido: eso no detiene la instalación. */
class DatabaseObjectsPermissionsTest extends TestCase
{
    public function test_without_permission_it_says_why_and_lets_the_installation_go_on(): void
    {
        $this->mysqlThatAnswers(1419, 'You do not have the SUPER privilege and binary logging is enabled');
        Log::spy();

        $reason = (new DatabaseObjects)->installIfAllowed();

        $this->assertStringContainsString('El sistema funciona igual', (string) $reason);
        $this->assertStringContainsString('SUPER privilege', (string) $reason);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_any_other_error_still_stops_it(): void
    {
        $this->mysqlThatAnswers(1064, 'You have an error in your SQL syntax');

        $this->expectException(QueryException::class);

        (new DatabaseObjects)->installIfAllowed();
    }

    public function test_on_another_database_there_is_nothing_to_install(): void
    {
        $this->assertNull((new DatabaseObjects)->installIfAllowed());
    }

    private function mysqlThatAnswers(int $code, string $message): void
    {
        $pdo = new PDOException($message);
        $pdo->errorInfo = ['HY000', $code, $message];

        $connection = Mockery::mock(Connection::class);
        $connection->allows('getDriverName')->andReturn('mysql');
        $connection->allows('unprepared')->andThrow(new QueryException('mysql', 'CREATE FUNCTION fn_hoy_lima()', [], $pdo));
        DB::shouldReceive('connection')->andReturn($connection);
    }
}
