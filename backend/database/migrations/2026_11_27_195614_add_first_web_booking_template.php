<?php

use App\Enums\MessageTemplateKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** El WhatsApp de la primera reserva web, con su enlace de Mi cuenta, editable en el panel como los demás avisos. */
    public function up(): void
    {
        DB::table('message_templates')->insertOrIgnore([
            'key' => MessageTemplateKey::FirstWebBooking->value,
            'name' => MessageTemplateKey::FirstWebBooking->getLabel(),
            'body' => MessageTemplateKey::FirstWebBooking->defaultBody(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('message_templates')->where('key', MessageTemplateKey::FirstWebBooking->value)->delete();
    }
};
