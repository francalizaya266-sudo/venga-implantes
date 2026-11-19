<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que los triggers de MySQL anotan cuando un pago se cambia o se borra por fuera de la aplicación (desde
     * phpMyAdmin, la consola o un script). La aplicación nunca hace eso: solo anula, y eso ya queda en audit_logs.
     * Sin llave foránea al pago: el registro tiene que quedar aunque el pago se haya borrado.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::create('payment_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->index();
            $table->string('event', 10);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            // Quién lo hizo, como lo ve MySQL: "usuario@equipo" de la conexión (USER(), no el dueño del trigger).
            $table->string('database_user', 300);
            $table->dateTime('changed_at')->index();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::dropIfExists('payment_audit_logs');
    }
};
