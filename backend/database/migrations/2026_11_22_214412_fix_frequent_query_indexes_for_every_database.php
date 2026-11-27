<?php

use App\Services\Database\DatabaseObjects;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los índices que deben quedar, con su tabla y sus columnas. Los tres primeros son los de 2026_11_18_210452; el
     * de starts_at reemplaza al de date(starts_at): la agenda y Hoy buscan un día como "desde las 00:00 hasta antes
     * de las 00:00 del día siguiente". Para la caja ya había uno sobre paid_at (payments_paid_at_index).
     *
     * @var array<string, array<string, list<string>>>
     */
    private const WANTED = [
        'payments' => ['payments_patient_balance_index' => ['patient_id', 'voided_at', 'amount']],
        'charges' => [
            'charges_charged_at_index' => ['charged_at'],
            'charges_patient_balance_index' => ['patient_id', 'voided_at', 'amount', 'discount'],
        ],
        'appointments' => ['appointments_starts_at_index' => ['starts_at']],
    ];

    /**
     * Los índices sobre date(…) que la versión vieja de 2026_11_18_210452 creaba en MySQL 8. MariaDB no los entiende
     * y ninguna consulta los usa ya.
     *
     * @var array<string, string>
     */
    private const UNWANTED = [
        'appointments' => 'appointments_starts_on_index',
        'payments' => 'payments_paid_on_index',
    ];

    /**
     * Deja la base como debe quedar, sin importar lo que hizo antes 2026_11_18_210452: en MySQL 8 creó los cinco, en
     * MariaDB declarada como "mariadb" no creó ninguno y desde su arreglo crea los tres normales. Se revisa cada índice
     * antes de tocarlo, así que se puede correr sobre cualquiera de esas bases. Al final vuelve a crear los objetos de
     * database/sql/mysql: la vista v_agenda_del_dia ahora también busca el día como un rango de horas, con el índice de
     * starts_at (en otro motor, DatabaseObjects no hace nada).
     */
    public function up(): void
    {
        foreach (self::UNWANTED as $table => $name) {
            if (Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
            }
        }

        foreach (self::WANTED as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (! Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
                }
            }
        }

        (new DatabaseObjects)->installIfAllowed();
    }

    /**
     * Quita solo el índice nuevo de starts_at. Los otros tres son de 2026_11_18_210452 y los de date(…) no vuelven:
     * no se pueden crear en MariaDB ni en SQLite.
     */
    public function down(): void
    {
        if (Schema::hasIndex('appointments', 'appointments_starts_at_index')) {
            Schema::table('appointments', fn (Blueprint $blueprint) => $blueprint->dropIndex('appointments_starts_at_index'));
        }
    }
};
