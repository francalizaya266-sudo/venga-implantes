<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices para las consultas que más se repiten, medidos con EXPLAIN ANALYZE sobre los datos del seeder
     * (docs/entregables/E2-plataforma-de-datos). Solo en MySQL: dos son índices sobre una expresión, que SQLite
     * escribe de otra forma, y en las pruebas con SQLite un índice nuevo podría cambiar el orden de las filas.
     *
     * - La agenda de hoy y el cobro del día filtran con whereDate(), que en MySQL es date(columna) = ?: sin un
     *   índice sobre date(...) se recorre la tabla entera.
     * - Los ingresos del mes filtran los cargos por charged_at, que no tenía índice propio.
     * - El saldo de cada paciente suma cargos y pagos válidos: con estos índices se resuelve sin leer las filas.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->rawIndex('(date(`starts_at`))', 'appointments_starts_on_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->rawIndex('(date(`paid_at`))', 'payments_paid_on_index');
            $table->index(['patient_id', 'voided_at', 'amount'], 'payments_patient_balance_index');
        });

        Schema::table('charges', function (Blueprint $table) {
            $table->index('charged_at');
            $table->index(['patient_id', 'voided_at', 'amount', 'discount'], 'charges_patient_balance_index');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_starts_on_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_paid_on_index');
            $table->dropIndex('payments_patient_balance_index');
        });

        Schema::table('charges', function (Blueprint $table) {
            $table->dropIndex(['charged_at']);
            $table->dropIndex('charges_patient_balance_index');
        });
    }
};
