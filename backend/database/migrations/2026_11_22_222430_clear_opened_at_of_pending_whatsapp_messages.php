<?php

use App\Enums\MessageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Un aviso "Por enviar" que dice cuándo se abrió en WhatsApp ahora espera que alguien diga si se envió. Hasta hoy
     * eso solo quedaba en los que se marcaron "No se envió": se borra, para que no pregunten por algo ya respondido.
     */
    public function up(): void
    {
        DB::table('whatsapp_messages')
            ->where('status', MessageStatus::Pending->value)
            ->whereNotNull('opened_at')
            ->update(['opened_at' => null, 'opened_by_user_id' => null]);
    }

    public function down(): void
    {
        // No hay nada que devolver: cuándo se abrieron ya no servía.
    }
};
