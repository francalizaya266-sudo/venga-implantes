<?php

use App\Models\Patient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El nombre completo sin tildes y en minúsculas ("maria quispe"), para que el buscador encuentre a "María Quispe"
     * aunque se escriba "Maria". MySQL ya ignora las tildes al comparar, pero SQLite (la laptop) no. Se llena para los
     * pacientes que ya están; los nuevos lo guardan solos.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('search_name')->nullable()->after('last_name');
        });

        DB::table('patients')->select(['id', 'first_name', 'last_name'])->orderBy('id')->chunkById(500, function ($patients) {
            foreach ($patients as $patient) {
                DB::table('patients')->where('id', $patient->id)->update(['search_name' => Patient::searchable($patient->first_name.' '.$patient->last_name)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('search_name');
        });
    }
};
