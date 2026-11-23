<?php

namespace Tests\Feature\Services\Database;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;

/**
 * Las mismas pruebas de los índices, en el MySQL 8 del job "mysql" de CI: ahí los índices sobre date(…) de la versión
 * vieja de 2026_11_18_210452 son de verdad, como en una instalación que ya la había corrido, y la migración que los
 * arregla tiene que quitarlos. `php artisan test --group=mysql`.
 */
#[Group('mysql')]
class MysqlFrequentQueryIndexesTest extends FrequentQueryIndexesTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Los índices sobre date(…) de la versión vieja solo existen en MySQL 8.');
        }
    }
}
