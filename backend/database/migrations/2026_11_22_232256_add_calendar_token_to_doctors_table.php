<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La clave del enlace secreto con el que el celular del doctor se suscribe a su agenda (.ics). Se guarda cifrada
     * para poder mostrarle el enlace otra vez en "Mi perfil"; sin clave, no hay enlace.
     */
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->text('calendar_token')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('calendar_token');
        });
    }
};
