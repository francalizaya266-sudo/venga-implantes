<?php

use App\Services\Database\DatabaseObjects;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Las funciones, vistas, procedimientos y triggers de database/sql/mysql: saldo por paciente, agenda del día,
     * deuda vencida, consumo de materiales, cierre de caja, reporte mensual de ingresos y la auditoría de pagos en
     * la base. Solo en MySQL (DatabaseObjects no hace nada en otro motor), para no romper las pruebas en SQLite.
     */
    public function up(): void
    {
        (new DatabaseObjects)->install();
    }

    public function down(): void
    {
        (new DatabaseObjects)->drop();
    }
};
