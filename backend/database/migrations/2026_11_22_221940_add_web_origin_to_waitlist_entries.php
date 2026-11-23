<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table) {
            // "Avísame si se libera un horario": el paciente se anotó solo desde la web, sin pasar por recepción.
            $table->boolean('from_web')->default(false)->after('notes');
            // Como en las citas: si se anotó desde el celular de la familia. Si no, no cuenta como familia del número.
            $table->boolean('from_verified_device')->default(false)->after('from_web');
        });
    }

    public function down(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropColumn(['from_web', 'from_verified_device']);
        });
    }
};
