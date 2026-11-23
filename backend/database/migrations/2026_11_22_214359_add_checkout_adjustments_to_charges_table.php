<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lo que se cambió al cobrar (otro precio, un descuento, un concepto agregado o quitado a mano): con qué precio y
        // descuento llegó a caja, por qué se cambió, quién y cuándo. Sale en el cierre del día y en la constancia.
        Schema::table('charges', function (Blueprint $table) {
            $table->decimal('original_amount', 10, 2)->nullable()->after('suggested_amount');
            $table->decimal('original_discount', 10, 2)->nullable()->after('original_amount');
            $table->string('adjustment_reason')->nullable()->after('original_discount');
            $table->foreignId('adjusted_by_user_id')->nullable()->after('adjustment_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('adjusted_at')->nullable()->after('adjusted_by_user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adjusted_by_user_id');
            $table->dropIndex(['adjusted_at']);
            $table->dropColumn(['original_amount', 'original_discount', 'adjustment_reason', 'adjusted_at']);
        });
    }
};
