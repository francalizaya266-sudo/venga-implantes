<?php

use App\Enums\MessageTemplateKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** El texto anterior: le decía "No pudimos verte" también a quien canceló, aunque su cita todavía no llegaba. */
    private const OLD_BODY = "Hola {nombre}, te escribimos de {consultorio}.\nNo pudimos verte en tu cita de {tratamiento} del {fecha}. ¿Te reservamos otro horario? Respóndenos con el día y la hora que te acomodan.";

    /** El aviso a quien faltó o canceló dice qué pasó ({que_paso}). Si el consultorio ya cambió el texto, se respeta. */
    public function up(): void
    {
        DB::table('message_templates')
            ->where('key', MessageTemplateKey::MissedVisit->value)
            ->where('body', self::OLD_BODY)
            ->update(['body' => MessageTemplateKey::MissedVisit->defaultBody(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('message_templates')
            ->where('key', MessageTemplateKey::MissedVisit->value)
            ->where('body', MessageTemplateKey::MissedVisit->defaultBody())
            ->update(['body' => self::OLD_BODY, 'updated_at' => now()]);
    }
};
