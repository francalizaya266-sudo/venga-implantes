<?php

use App\Enums\MessageTemplateKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** El primer texto del resumen: no traía la línea de los precios cambiados al cobrar. */
    private const FIRST_BODY = "Resumen del {fecha} en {consultorio}\nCobrado: {cobrado}\nArqueo: {arqueo} · cerró {cerro}\nPacientes atendidos: {atendidos} · Faltas: {faltas} · Citas sin cerrar: {sin_cerrar}\nDescuentos: {descuentos}\nCitas del {siguiente} sin confirmar: {sin_confirmar}";

    /** El texto anterior: un descuento puesto al cobrar salía en «Descuentos» y otra vez en los precios cambiados al cobrar. */
    private const OLD_BODY = "Resumen del {fecha} en {consultorio}\nCobrado: {cobrado}\nArqueo: {arqueo} · cerró {cerro}\nPacientes atendidos: {atendidos} · Faltas: {faltas} · Citas sin cerrar: {sin_cerrar}\nDescuentos: {descuentos}\nPrecios cambiados y descuentos al cobrar: {precios}\nCitas del {siguiente} sin confirmar: {sin_confirmar}";

    /**
     * El resumen del día separa los descuentos puestos antes de cobrar de lo cambiado al cobrar, para que cada sol salga
     * una sola vez. Si el consultorio ya cambió el texto, se respeta.
     */
    public function up(): void
    {
        DB::table('message_templates')
            ->where('key', MessageTemplateKey::DaySummary->value)
            ->whereIn('body', [self::FIRST_BODY, self::OLD_BODY])
            ->update(['body' => MessageTemplateKey::DaySummary->defaultBody(), 'updated_at' => now()]);
    }

    /** Vuelve al texto anterior, el que ya traía los precios cambiados al cobrar. */
    public function down(): void
    {
        DB::table('message_templates')
            ->where('key', MessageTemplateKey::DaySummary->value)
            ->where('body', MessageTemplateKey::DaySummary->defaultBody())
            ->update(['body' => self::OLD_BODY, 'updated_at' => now()]);
    }
};
