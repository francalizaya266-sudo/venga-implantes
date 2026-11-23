<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada paciente elige si sus avisos llegan por WhatsApp, en su celular o por los dos (whatsapp_notices: si
     * también van por WhatsApp). pushed_at: cuándo un aviso ya se mostró en el celular, para no repetirlo.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('whatsapp_notices')->default(true)->after('promotions_consent_at');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->timestamp('pushed_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn('pushed_at');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('whatsapp_notices');
        });
    }
};
