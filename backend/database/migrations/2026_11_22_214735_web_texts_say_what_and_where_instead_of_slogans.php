<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los textos con que venía la web. Al guardar por primera vez la puesta a punto o los textos de las páginas,
     * quedan guardados todos los de la hoja, aunque nadie los haya tocado: el título que prometía "sin esperas", la
     * frase bajo él, los lemas sobre cada título y los cuatro valores genéricos de Equipo.
     *
     * @var array<string, string>
     */
    private const OLD_TEXTS = [
        'web.tagline' => 'Innovación y cuidado dental',
        'web.headline' => 'Atención dental sin esperas',
        'web.intro' => 'Evaluaciones, limpiezas, implantes y más. Elige tu horario aquí mismo y te confirmamos por WhatsApp.',
        'web.pages.servicios.kicker' => 'Tu sonrisa, nuestra prioridad',
        'web.pages.equipo.kicker' => 'Profesionales comprometidos con tu salud',
        'web.pages.contacto.kicker' => 'Estamos aquí para ayudarte',
        'web.pages.preguntas.kicker' => 'Resolvemos tus dudas',
        'web.pages.promociones.kicker' => 'Ofertas vigentes',
        'web.pages.casos.kicker' => 'Resultados reales',
        'web.pages.opiniones.kicker' => 'Lo que dicen nuestros pacientes',
        'web.pages.cuenta.kicker' => 'Tu espacio personal',
        'web.pages.reservar.kicker' => 'Reserva tu cita',
        'web.pages.equipo.value1_title' => 'Colegiados',
        'web.pages.equipo.value1_text' => 'Cada doctor con su número de colegiatura',
        'web.pages.equipo.value2_title' => 'Te explicamos todo',
        'web.pages.equipo.value2_text' => 'El presupuesto, antes de empezar',
        'web.pages.equipo.value3_title' => 'Compromiso',
        'web.pages.equipo.value3_text' => 'Con tu salud bucal',
        'web.pages.equipo.value4_title' => 'Trato humano',
        'web.pages.equipo.value4_text' => 'Tu bienestar es primero',
    ];

    /** Los ajustes se leen de la caché (App\Models\Setting): sin olvidarla, la web seguiría con los textos de antes. */
    private const SETTINGS_CACHE = 'settings.all';

    /**
     * Lo que quedó guardado tal como venía pasa a vacío: el título y la frase bajo él vuelven a ser los del sistema
     * (con la ciudad), y los lemas y los valores dejan de salir. Lo que el consultorio escribió se respeta.
     */
    public function up(): void
    {
        $saved = DB::table('settings')->whereIn('key', array_keys(self::OLD_TEXTS))->get(['id', 'key', 'value']);

        foreach ($saved as $setting) {
            if (json_decode((string) $setting->value, true) === self::OLD_TEXTS[$setting->key]) {
                DB::table('settings')->where('id', $setting->id)->update(['value' => null, 'updated_at' => now()]);
            }
        }

        Cache::forget(self::SETTINGS_CACHE);
    }

    /**
     * El título y la frase bajo él eran obligatorios: si están vacíos, es por esta migración y vuelven los de antes.
     * Un lema o un valor vacío pudo dejarlo así el consultorio, así que esos no se vuelven a poner.
     */
    public function down(): void
    {
        foreach (['web.headline', 'web.intro'] as $key) {
            DB::table('settings')
                ->where('key', $key)
                ->whereNull('value')
                ->update(['value' => json_encode(self::OLD_TEXTS[$key]), 'updated_at' => now()]);
        }

        Cache::forget(self::SETTINGS_CACHE);
    }
};
