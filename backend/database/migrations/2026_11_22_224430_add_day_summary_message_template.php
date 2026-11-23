<?php

use App\Enums\MessageTemplateKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** El resumen del día que le llega a quien administra al cerrar la caja. */
    public function up(): void
    {
        DB::table('message_templates')->insertOrIgnore([
            'key' => MessageTemplateKey::DaySummary->value,
            'name' => MessageTemplateKey::DaySummary->getLabel(),
            'body' => MessageTemplateKey::DaySummary->defaultBody(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('message_templates')->where('key', MessageTemplateKey::DaySummary->value)->delete();
    }
};
