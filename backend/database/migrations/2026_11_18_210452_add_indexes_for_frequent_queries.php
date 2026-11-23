<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices para las consultas que más se repiten, medidos con EXPLAIN ANALYZE sobre los datos del seeder
     * (docs/entregables/CE02-ingenieria-de-software/E2-plataforma-de-datos). Son índices normales: valen en MySQL 8,
     * MariaDB 10.6+ y SQLite.
     *
     * - Los ingresos del mes filtran los cargos por charged_at, que no tenía índice propio.
     * - El saldo de cada paciente suma cargos y pagos válidos: con estos índices se resuelve sin leer las filas.
     *
     * Al principio también creaba dos índices sobre date(starts_at) y date(paid_at), que solo existen en MySQL 8: en
     * MariaDB la instalación se detenía con un error o se saltaba los cinco. Ahora la agenda y la caja buscan un día
     * como un rango de horas, que usa un índice normal, y la migración 2026_11_22_214412 deja cualquier base como
     * debe quedar, haya corrido antes esta versión o la vieja.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['patient_id', 'voided_at', 'amount'], 'payments_patient_balance_index');
        });

        Schema::table('charges', function (Blueprint $table) {
            $table->index('charged_at');
            $table->index(['patient_id', 'voided_at', 'amount', 'discount'], 'charges_patient_balance_index');
        });
    }

    /** Quita lo que haya: en una base de MySQL 8 que corrió la versión vieja también están los dos de date(…). */
    public function down(): void
    {
        $indexes = [
            'appointments' => ['appointments_starts_on_index'],
            'payments' => ['payments_paid_on_index', 'payments_patient_balance_index'],
            'charges' => ['charges_charged_at_index', 'charges_patient_balance_index'],
        ];

        foreach ($indexes as $table => $names) {
            foreach ($names as $name) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }
};
