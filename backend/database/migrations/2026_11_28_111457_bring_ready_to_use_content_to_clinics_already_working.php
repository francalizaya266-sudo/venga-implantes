<?php

use App\Enums\MessageTemplateKey;
use App\Services\Catalog\QuickTextCatalog;
use App\Services\Catalog\TreatmentCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los textos de aviso que el sistema traía hasta ahora, tal como quedaron guardados.
     *
     * @var array<string, string> Aviso => texto anterior.
     */
    private const OLD_BODIES = [
        'booking_confirmation' => "Hola {nombre} 👋, te escribimos de {consultorio}.\nTu cita de {tratamiento} quedó reservada para el {fecha} a las {hora} con {doctor}.\n{precio}\n\nConfirma tu asistencia, cambia el horario o cancela aquí:\n{enlace}",
        'first_web_booking' => "Hola {nombre} 👋, te escribimos de {consultorio}.\nTu cita de {tratamiento} quedó reservada para el {fecha} a las {hora} con {doctor}.\n{precio}\n\nYa tienes tu cuenta con nosotros. Entra a Mi cuenta para confirmar tu asistencia, cambiar el horario o cancelar:\n{enlace}\n\nNo compartas este enlace con nadie.",
        'appointment_scheduled' => "Hola {nombre}, te escribimos de {consultorio}.\nTu próxima cita de {tratamiento} es el {fecha} a las {hora} con {doctor}.\n\nPuedes confirmarla aquí:\n{enlace}",
        'appointment_reminder' => "Hola {nombre}, te recordamos tu cita {cuando} a las {hora} con {doctor} en {consultorio}.\n{indicaciones}\n\nConfirma tu asistencia aquí:\n{enlace}",
        'prescription' => "Hola {nombre}, te enviamos tu receta e indicaciones de {doctor}:\n\n{receta}",
        'review_request' => "Hola {nombre}, gracias por confiar en {consultorio}. Si te sentiste bien atendido, ¿nos dejas tu opinión en Google? Toma un minuto y ayuda a otras personas a encontrarnos:\n{opinion}",
        'aftercare' => "Hola {nombre}, gracias por venir hoy a {consultorio}. Para que tu {tratamiento} quede bien:\n\n{cuidados}\n\nSi tienes dolor fuerte, sangrado que no para o fiebre, escríbenos.",
    ];

    /**
     * El contenido que ahora trae el sistema llega también al consultorio que ya lo usa, sin tocar nada de lo que
     * escribió: los avisos que siguen con el texto anterior pasan al nuevo; a los tratamientos que trajo el sistema se
     * les llena solo lo que está vacío (antes de venir, cuidados y el texto de su página); y se suman las opciones
     * rápidas nuevas a las del equipo. En una base recién creada no hay nada que completar: eso lo carga CatalogSeeder.
     */
    public function up(): void
    {
        foreach (self::OLD_BODIES as $key => $oldBody) {
            DB::table('message_templates')
                ->where('key', $key)
                ->where('body', $oldBody)
                ->update(['body' => MessageTemplateKey::from($key)->defaultBody(), 'updated_at' => now()]);
        }

        app(TreatmentCatalog::class)->fillEmptyTexts();

        if (DB::table('text_snippets')->whereNull('user_id')->exists()) {
            app(QuickTextCatalog::class)->install(onlyAdded: true);
        }
    }

    /**
     * Vuelven los textos de aviso anteriores donde nadie los cambió. Los textos de los tratamientos y las opciones
     * rápidas se quedan: ya no se distinguen de lo que el consultorio escribió o empezó a usar.
     */
    public function down(): void
    {
        foreach (self::OLD_BODIES as $key => $oldBody) {
            DB::table('message_templates')
                ->where('key', $key)
                ->where('body', MessageTemplateKey::from($key)->defaultBody())
                ->update(['body' => $oldBody, 'updated_at' => now()]);
        }
    }
};
