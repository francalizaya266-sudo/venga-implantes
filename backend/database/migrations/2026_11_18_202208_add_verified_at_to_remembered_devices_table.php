<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un celular también queda recordado al reservar la primera cita con un número que el consultorio no tenía, sin
     * haber tocado todavía un enlace: queda sin verificar hasta que toque uno. Hasta ahora solo se recordaba al tocar
     * el enlace, así que los que ya están quedan verificados desde que se recordaron.
     */
    public function up(): void
    {
        Schema::table('remembered_devices', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->after('revoked_at');
        });

        DB::table('remembered_devices')->update(['verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('remembered_devices', function (Blueprint $table) {
            $table->dropColumn('verified_at');
        });
    }
};
