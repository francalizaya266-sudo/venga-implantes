<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Avísame si se libera un horario": el paciente se anotó solo desde la web, sin pasar por recepción.
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->boolean('from_web')->default(false)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropColumn('from_web');
        });
    }
};
