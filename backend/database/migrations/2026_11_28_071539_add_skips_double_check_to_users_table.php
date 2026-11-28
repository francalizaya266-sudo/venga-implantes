<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El administrador puede dejar de pedirle la doble verificación a una persona (Configuración › Usuarios): entra solo
 * con su contraseña. Nadie nace así: a todos se les pide hasta que alguien lo apague, y queda en el historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('skips_double_check')->default(false)->after('app_authentication_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('skips_double_check');
        });
    }
};
