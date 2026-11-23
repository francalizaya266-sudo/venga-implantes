<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Site;

use App\Enums\DiscountType;
use App\Models\Campaign;
use App\Models\Doctor;
use App\Models\Faq;
use App\Models\Review;
use App\Models\Setting;
use App\Models\SmileCase;
use App\Models\Treatment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

class SiteContentTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private function campaign(array $attributes = []): Campaign
    {
        return Campaign::query()->create($attributes + [
            'name' => 'Limpieza de verano',
            'slug' => 'limpieza-verano',
            'summary' => 'Limpieza con 20% de descuento.',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    public function test_the_site_gives_the_clinic_data_the_sections_with_content_and_the_texts_of_each_page(): void
    {
        Setting::set('clinic.name', 'Clínica Vega');
        Setting::set('clinic.district', 'Juliaca');
        Setting::set('clinic.whatsapp', '912 345 678');
        Setting::set('web.pages.equipo.title', 'Quienes te *cuidan*');
        Setting::set('web.pages.equipo.kicker', null);
        Faq::factory()->create();

        $this->getJson(route('api.v1.site'))
            ->assertOk()
            ->assertJsonPath('data.clinic.name', 'Clínica Vega')
            ->assertJsonPath('data.clinic.tagline', 'Dentistas en Juliaca')
            ->assertJsonPath('data.clinic.whatsapp', ['number' => '51912345678', 'display' => '912 345 678'])
            // Solo se anuncian las secciones que tienen algo publicado.
            ->assertJsonPath('data.sections.faq', true)
            ->assertJsonPath('data.sections.reviews', false)
            // El encabezado de cada página es el del panel: lo que va entre asteriscos sale en azul.
            ->assertJsonPath('data.pages.equipo.title', ['Quienes te ', 'cuidan', ''])
            ->assertJsonPath('data.pages.equipo.kicker', null)
            ->assertJsonPath('data.pages.servicios.title', ['Nuestros ', 'servicios', ''])
            ->assertJsonStructure(['data' => ['clinic' => ['hours' => ['lines', 'status', 'is_open']], 'home' => ['title' => ['strong', 'thin', 'accent']], 'closing' => ['title', 'text', 'whatsapp'], 'team_values', 'year']]);
    }

    public function test_the_cover_title_goes_in_two_tones_and_what_follows_the_comma_goes_thin(): void
    {
        $title = function (string $headline): array {
            Setting::set('web.headline', $headline);

            return $this->getJson(route('api.v1.site'))->assertOk()->json('data.home.title');
        };

        // Con un número impar de palabras, la primera mitad se lleva la del medio.
        $this->assertSame(['strong' => 'Sonrisas sanas', 'thin' => '', 'accent' => 'siempre'], $title('Sonrisas sanas siempre'));
        // Una sola palabra no tiene segunda mitad que pintar.
        $this->assertSame(['strong' => 'Sonríe', 'thin' => '', 'accent' => ''], $title('Sonríe'));
        // Lo que sigue a la coma de la primera mitad va en letra delgada.
        $this->assertSame(['strong' => 'Tu sonrisa,', 'thin' => 'nuestra', 'accent' => 'mayor prioridad'], $title('Tu sonrisa, nuestra mayor prioridad'));
        // La coma de la segunda mitad no cambia nada: esa parte va entera en celeste.
        $this->assertSame(['strong' => 'Sonrisas sanas,', 'thin' => '', 'accent' => 'siempre'], $title('Sonrisas sanas, siempre'));
    }

    public function test_the_cover_says_what_and_where_with_the_city_of_the_settings_unless_the_clinic_wrote_its_own(): void
    {
        $cover = fn (): array => $this->getJson(route('api.v1.site'))->assertOk()->json('data.home');

        // Sin nada escrito en la puesta a punto: qué se atiende y dónde, sin lema encima ni promesas de "sin esperas".
        Setting::set('clinic.district', 'Juliaca');
        $this->assertSame(['strong' => 'Implantes y odontología', 'thin' => '', 'accent' => 'en Juliaca'], $cover()['title']);
        $this->assertSame('Tu dentista en Juliaca. Reserva en 2 minutos.', $cover()['intro']);
        $this->assertNull($cover()['tagline']);

        // La ciudad sale de Ajustes: si cambia, la portada cambia sola.
        Setting::set('clinic.district', 'Puno');
        $this->assertSame('en Puno', $cover()['title']['accent']);
        $this->assertSame('Tu dentista en Puno. Reserva en 2 minutos.', $cover()['intro']);

        // Sin ciudad no queda un "en" colgando.
        Setting::set('clinic.district', null);
        $this->assertSame(['strong' => 'Implantes y', 'thin' => '', 'accent' => 'odontología'], $cover()['title']);
        $this->assertSame('Reserva en 2 minutos.', $cover()['intro']);

        // Lo que el consultorio escribe manda.
        Setting::set('web.headline', 'Sonrisas sanas siempre');
        Setting::set('web.intro', 'Atendemos a toda la familia.');
        $this->assertSame('Sonrisas sanas', $cover()['title']['strong']);
        $this->assertSame('Atendemos a toda la familia.', $cover()['intro']);
    }

    public function test_the_texts_saved_as_they_came_give_way_to_the_new_ones_but_what_the_clinic_wrote_stays(): void
    {
        $migration = require database_path('migrations/2026_11_22_214735_web_texts_say_what_and_where_instead_of_slogans.php');
        Setting::set('clinic.district', 'Juliaca');
        // Al guardar la puesta a punto y los textos de las páginas sin tocarlos, quedaron guardados los de antes.
        Setting::set('web.tagline', 'Innovación y cuidado dental');
        Setting::set('web.headline', 'Atención dental sin esperas');
        Setting::set('web.intro', 'Evaluaciones, limpiezas, implantes y más. Elige tu horario aquí mismo y te confirmamos por WhatsApp.');
        Setting::set('web.pages.servicios.kicker', 'Tu sonrisa, nuestra prioridad');
        Setting::set('web.pages.equipo.value1_title', 'Colegiados');
        Setting::set('web.pages.equipo.value1_text', 'Cada doctor con su número de colegiatura');
        // Lo que el consultorio escribió.
        Setting::set('web.pages.contacto.kicker', 'A media cuadra de la plaza');
        Setting::set('web.pages.equipo.value2_title', 'Atención en quechua');
        $this->getJson(route('api.v1.site'))->assertJsonPath('data.home.title.accent', 'sin esperas');

        $migration->up();

        $this->getJson(route('api.v1.site'))
            ->assertOk()
            ->assertJsonPath('data.home.tagline', null)
            ->assertJsonPath('data.home.title', ['strong' => 'Implantes y odontología', 'thin' => '', 'accent' => 'en Juliaca'])
            ->assertJsonPath('data.home.intro', 'Tu dentista en Juliaca. Reserva en 2 minutos.')
            ->assertJsonPath('data.pages.servicios.kicker', null)
            ->assertJsonPath('data.pages.contacto.kicker', 'A media cuadra de la plaza')
            ->assertJsonPath('data.team_values', [['icon' => 'chat-check', 'title' => 'Atención en quechua', 'text' => null]]);

        // Al volver atrás, el título y la frase (que eran obligatorios) vuelven a ser los de antes.
        $migration->down();

        $this->assertSame('Atención dental sin esperas', Setting::get('web.headline'));
        $this->assertSame('Evaluaciones, limpiezas, implantes y más. Elige tu horario aquí mismo y te confirmamos por WhatsApp.', Setting::get('web.intro'));
    }

    public function test_the_home_gives_the_next_free_times_the_first_eight_treatments_and_the_main_questions(): void
    {
        $this->freezeMonday();
        Setting::set('clinic.name', 'Clínica Vega');
        Setting::set('clinic.district', 'Juliaca');
        $evaluation = Treatment::factory()->evaluation()->create(['slug' => 'evaluacion', 'price_from' => 50]);
        $others = Treatment::factory()->count(9)->create();
        $this->doctorOffering($evaluation, ...$others);
        Faq::factory()->count(5)->create();
        Faq::factory()->for($others->first())->create(['question' => '¿Pregunta de un servicio?']);

        $this->getJson(route('api.v1.home'))
            ->assertOk()
            ->assertJsonPath('data.welcome', null)
            ->assertJsonPath('data.can_book', true)
            // Con 2 horas de anticipación mínima, lo primero es hoy a las 10:00.
            ->assertJsonPath('data.next_free.date', '2026-11-02')
            ->assertJsonPath('data.next_free.times.0.time', '10:00')
            ->assertJsonPath('data.next_free.treatment.slug', 'evaluacion')
            // La evaluación va primero; el inicio muestra ocho y dice cuántos hay.
            ->assertJsonCount(8, 'data.treatments')
            ->assertJsonPath('data.treatments.0.slug', 'evaluacion')
            ->assertJsonPath('data.treatments.0.price', 'desde S/ 50')
            ->assertJsonPath('data.treatments_count', 10)
            ->assertJsonPath('data.shows_prices', true)
            // Las cuatro preguntas principales son generales: las de un servicio van en su página.
            ->assertJsonCount(4, 'data.faqs')
            ->assertJsonMissing(['question' => '¿Pregunta de un servicio?'])
            ->assertJsonPath('meta.title', 'Clínica Vega · Dentistas en Juliaca')
            ->assertJsonPath('meta.noindex', false)
            ->assertJsonPath('meta.json_ld.0.@type', 'Dentist');
    }

    public function test_under_the_buttons_the_cover_says_where_which_days_and_from_how_much(): void
    {
        $facts = fn (): array => $this->getJson(route('api.v1.home'))->assertOk()->json('data.facts');
        Setting::set('clinic.address', 'Jr. Apurímac 648');
        Doctor::factory()->withSchedule()->create();
        $evaluation = Treatment::factory()->evaluation()->create(['price_from' => 50]);

        $this->assertSame(['address' => 'Jr. Apurímac 648', 'days' => 'Lunes a sábado', 'price' => 'Evaluación desde S/ 50'], $facts());

        $evaluation->update(['price_from' => 0]);
        $this->assertSame('Evaluación gratis', $facts()['price']);
    }

    public function test_what_is_not_set_up_stays_out_of_the_line_under_the_buttons(): void
    {
        // Sin dirección, sin horarios y con la evaluación sin precio publicado, la línea no tiene nada que decir.
        Setting::set('clinic.address', null);
        Treatment::factory()->evaluation()->create(['price_from' => 50, 'show_price' => false]);

        $this->getJson(route('api.v1.home'))
            ->assertOk()
            ->assertJsonPath('data.facts', ['address' => null, 'days' => null, 'price' => null]);
    }

    public function test_without_online_booking_the_home_promises_no_free_times(): void
    {
        Treatment::factory()->clinicOnly()->create();

        $this->getJson(route('api.v1.home'))
            ->assertOk()
            ->assertJsonPath('data.can_book', false)
            ->assertJsonPath('data.next_free', null);
    }

    public function test_the_home_features_the_treatment_of_the_specialty_a_doctor_declares(): void
    {
        $implant = Treatment::factory()->create(['name' => 'Implante dental', 'slug' => 'implante']);
        Doctor::factory()->create(['name' => 'Dr. Vega', 'specialty' => 'Implantólogo']);
        Doctor::factory()->create(['is_public' => false]);

        $this->getJson(route('api.v1.home'))
            ->assertOk()
            ->assertJsonPath('data.specialty.slug', $implant->slug)
            ->assertJsonPath('data.doctor.name', 'Dr. Vega')
            ->assertJsonPath('data.other_doctors', 0);
    }

    public function test_only_published_content_reaches_the_web(): void
    {
        Faq::factory()->create(['question' => '¿Atienden a niños?']);
        Faq::factory()->create(['question' => '¿Pregunta en borrador?', 'is_published' => false]);
        Review::factory()->create(['author_name' => 'Rosa Huamán', 'body' => 'Muy buena atención.']);
        Review::factory()->create(['author_name' => 'Opinión Oculta', 'is_published' => false]);
        SmileCase::factory()->create(['title' => 'Blanqueamiento en 2 sesiones']);
        SmileCase::factory()->create(['title' => 'Caso sin publicar', 'is_published' => false]);
        $this->campaign();
        $this->campaign(['name' => 'Promo vieja', 'slug' => 'promo-vieja', 'starts_at' => now()->subMonths(2), 'ends_at' => now()->subMonth()]);

        $this->getJson(route('api.v1.home'))
            ->assertOk()
            ->assertJsonPath('data.faqs.*.question', ['¿Atienden a niños?'])
            // De quien opina se muestra el nombre y la inicial del apellido.
            ->assertJsonPath('data.reviews.*.author', ['Rosa H.'])
            ->assertJsonPath('data.cases.*.title', ['Blanqueamiento en 2 sesiones'])
            ->assertJsonPath('data.campaigns.*.name', ['Limpieza de verano']);

        $this->getJson(route('api.v1.faqs'))->assertOk()->assertJsonPath('data.topics.*.faqs.*.question', ['¿Atienden a niños?']);
        $this->getJson(route('api.v1.reviews'))->assertOk()->assertJsonPath('data.reviews.*.body', ['Muy buena atención.']);
        $this->getJson(route('api.v1.cases'))->assertOk()->assertJsonPath('data.cases.*.title', ['Blanqueamiento en 2 sesiones']);
        $this->getJson(route('api.v1.promotions.index'))->assertOk()->assertJsonPath('data.campaigns.*.slug', ['limpieza-verano']);
    }

    public function test_the_questions_go_by_topic_with_the_general_ones_first(): void
    {
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'slug' => 'limpieza-dental']);
        Faq::factory()->for($cleaning)->create(['question' => '¿Cada cuánto me hago una limpieza?']);
        Faq::factory()->create(['question' => '¿Atienden a niños?', 'answer' => 'Sí, desde los 3 años.']);

        $this->getJson(route('api.v1.faqs'))
            ->assertOk()
            ->assertJsonPath('data.topics.*.id', ['faq-generales', 'faq-de-limpieza-dental'])
            ->assertJsonPath('data.topics.*.name', ['Generales', 'Limpieza dental'])
            ->assertJsonPath('data.topics.0.treatment', null)
            ->assertJsonPath('data.topics.1.treatment.slug', 'limpieza-dental')
            ->assertJsonPath('data.topics.0.faqs.0.question', '¿Atienden a niños?')
            ->assertJsonPath('meta.json_ld.1.@type', 'FAQPage');
    }

    public function test_the_cases_can_be_seen_by_treatment(): void
    {
        $whitening = Treatment::factory()->create(['slug' => 'blanqueamiento']);
        SmileCase::factory()->for($whitening)->create(['title' => 'Blanqueamiento en 2 sesiones']);
        SmileCase::factory()->create(['title' => 'Sonrisa nueva con carillas']);

        $this->getJson(route('api.v1.cases'))
            ->assertOk()
            ->assertJsonCount(2, 'data.cases')
            ->assertJsonPath('data.selected', null);

        $this->getJson(route('api.v1.cases', ['tratamiento' => 'blanqueamiento']))
            ->assertOk()
            ->assertJsonPath('data.cases.*.title', ['Blanqueamiento en 2 sesiones'])
            ->assertJsonPath('data.selected', 'blanqueamiento');
    }

    public function test_the_treatments_page_books_the_evaluation_when_the_web_offers_it(): void
    {
        $this->doctorOffering(Treatment::factory()->create(['name' => 'Limpieza dental', 'slug' => 'limpieza']));

        $this->getJson(route('api.v1.treatments.index'))
            ->assertOk()
            ->assertJsonPath('data.treatments.*.slug', ['limpieza'])
            ->assertJsonPath('data.treatments.0.art', 'limpieza')
            ->assertJsonPath('data.evaluation', null)
            ->assertJsonPath('meta.og_title', 'Nuestros servicios');

        $evaluation = Treatment::factory()->evaluation()->create();
        $this->doctorOffering($evaluation);

        $this->getJson(route('api.v1.treatments.index'))
            ->assertOk()
            ->assertJsonPath('data.evaluation.slug', $evaluation->slug);
    }

    public function test_a_treatment_that_is_not_booked_online_starts_with_an_evaluation(): void
    {
        $this->freezeMonday();
        Setting::set('clinic.district', 'Juliaca');
        $evaluation = Treatment::factory()->evaluation()->create();
        $endodontics = Treatment::factory()->clinicOnly()->create([
            'name' => 'Endodoncia',
            'slug' => 'endodoncia',
            'sessions_label' => '1-3',
            'price_from' => 600,
            'description' => "Se hace con **anestesia local**.\n\n<script>alert(1)</script>\n\n[Clic](javascript:alert(1))",
        ]);
        $this->doctorOffering($evaluation, $endodontics);
        Faq::factory()->for($endodontics)->create(['question' => '¿Duele una endodoncia?', 'answer' => 'No, se usa anestesia.']);

        $response = $this->getJson(route('api.v1.treatments.show', 'endodoncia'))
            ->assertOk()
            ->assertJsonPath('data.treatment.price', 'desde S/ 600')
            ->assertJsonPath('data.treatment.sessions_text', '1 – 3 sesiones')
            ->assertJsonPath('data.treatment.art', 'endodoncia')
            // Se reserva la evaluación, y los próximos horarios son los de la evaluación.
            ->assertJsonPath('data.booking', ['treatment' => $evaluation->slug, 'promo' => null, 'direct' => false])
            ->assertJsonPath('data.next_free.title', 'Próximo horario para tu evaluación')
            ->assertJsonPath('data.next_free.date', '2026-11-02')
            ->assertJsonPath('data.faqs.0.question', '¿Duele una endodoncia?')
            ->assertJsonPath('meta.title', 'Endodoncia en Juliaca · '.Setting::get('clinic.name'))
            ->assertJsonPath('meta.json_ld.1.@type', 'FAQPage');

        // La descripción llega como HTML ya limpio: lo que el doctor escribió no puede ejecutar nada.
        $description = $response->json('data.treatment.description_html');
        $this->assertStringContainsString('<strong>anestesia local</strong>', $description);
        $this->assertStringNotContainsString('<script>', $description);
        $this->assertStringNotContainsString('javascript:', $description);
    }

    public function test_hidden_or_inactive_treatments_have_no_page(): void
    {
        Treatment::factory()->create(['slug' => 'interno', 'is_public' => false]);
        Treatment::factory()->create(['slug' => 'retirado', 'is_active' => false]);

        $this->getJson(route('api.v1.treatments.show', 'interno'))->assertNotFound();
        $this->getJson(route('api.v1.treatments.show', 'retirado'))->assertNotFound();
        $this->getJson(route('api.v1.treatments.show', 'no-existe'))->assertNotFound();
    }

    public function test_a_campaign_shows_its_price_on_the_treatment_and_the_promotion_and_books_with_it(): void
    {
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'slug' => 'limpieza-dental', 'price_from' => 150]);
        $this->doctorOffering($cleaning);
        $this->campaign(['promo_code' => 'VERANO30', 'treatment_id' => $cleaning->id, 'discount_type' => DiscountType::Amount, 'discount_value' => 30]);

        $this->getJson(route('api.v1.treatments.show', 'limpieza-dental'))
            ->assertOk()
            ->assertJsonPath('data.campaign.slug', 'limpieza-verano')
            ->assertJsonPath('data.campaign.price', 'desde S/ 120')
            ->assertJsonPath('data.booking', ['treatment' => 'limpieza-dental', 'promo' => 'limpieza-verano', 'direct' => true]);

        $this->getJson(route('api.v1.promotions.show', 'limpieza-verano'))
            ->assertOk()
            ->assertJsonPath('data.campaign.price', ['normal' => 'desde S/ 150', 'now' => 'desde S/ 120'])
            ->assertJsonPath('data.campaign.promo_code', 'VERANO30')
            ->assertJsonPath('data.by_evaluation', false)
            ->assertJsonPath('data.booking', ['tratamiento' => 'limpieza-dental', 'codigo' => 'VERANO30', 'origen' => 'campaign']);
    }

    public function test_a_promotion_for_a_treatment_that_is_not_booked_online_books_the_evaluation_with_it(): void
    {
        $whitening = Treatment::factory()->clinicOnly()->create(['name' => 'Blanqueamiento', 'slug' => 'blanqueamiento', 'price_from' => 480]);
        $evaluation = Treatment::factory()->evaluation()->create(['name' => 'Evaluación dental', 'slug' => 'evaluacion']);
        $this->doctorOffering($whitening, $evaluation);
        $this->campaign(['name' => 'Blanqueamiento de verano', 'slug' => 'blanqueamiento-verano', 'treatment_id' => $whitening->id, 'discount_type' => DiscountType::Percent, 'discount_value' => 20]);

        $this->getJson(route('api.v1.promotions.show', 'blanqueamiento-verano'))
            ->assertOk()
            ->assertJsonPath('data.by_evaluation', true)
            ->assertJsonPath('data.booking', ['tratamiento' => 'evaluacion', 'promo' => 'blanqueamiento-verano', 'origen' => 'campaign']);
    }

    public function test_a_promotion_counts_one_visit_per_person_and_an_ended_one_offers_the_current_ones(): void
    {
        $campaign = $this->campaign();
        $this->campaign(['name' => 'Blanqueamiento de invierno', 'slug' => 'blanqueamiento-invierno', 'starts_at' => now()->subMonths(3), 'ends_at' => now()->subMonth()]);

        $this->getJson(route('api.v1.promotions.show', 'limpieza-verano'))->assertOk()->assertJsonPath('data.campaign.summary', 'Limpieza con 20% de descuento.');
        $this->getJson(route('api.v1.promotions.show', 'limpieza-verano'))->assertOk();
        $this->assertSame(1, $campaign->fresh()->views_count);

        $this->getJson(route('api.v1.promotions.show', 'blanqueamiento-invierno'))
            ->assertNotFound()
            ->assertJsonPath('message', 'Esta promoción ya terminó')
            ->assertJsonPath('data.ended', 'Blanqueamiento de invierno')
            ->assertJsonPath('data.campaigns.*.slug', ['limpieza-verano'])
            ->assertJsonPath('meta.noindex', true);

        $this->getJson(route('api.v1.promotions.show', 'no-existe'))
            ->assertNotFound()
            ->assertJsonPath('message', 'No encontramos esta promoción')
            ->assertJsonPath('data.ended', null);
    }

    public function test_the_team_lists_the_public_doctors_with_what_they_attend(): void
    {
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $hidden = Treatment::factory()->create(['name' => 'Tratamiento interno', 'is_public' => false]);
        $doctor = Doctor::factory()->create(['name' => 'Dra. Vega', 'specialty' => 'Ortodoncista', 'cop_number' => '12345']);
        $doctor->treatments()->attach([$cleaning->id, $hidden->id]);
        Doctor::factory()->create(['name' => 'Dr. Oculto', 'is_public' => false]);

        $this->getJson(route('api.v1.team'))
            ->assertOk()
            ->assertJsonPath('data.doctors.*.name', ['Dra. Vega'])
            ->assertJsonPath('data.doctors.0.cop_number', '12345')
            ->assertJsonPath('data.doctors.0.treatments.*.name', ['Limpieza dental'])
            ->assertJsonPath('meta.json_ld.0.@type', 'BreadcrumbList');
    }

    public function test_the_contact_and_privacy_pages_say_who_they_are_to_google(): void
    {
        Setting::set('clinic.name', 'Clínica Vega');
        Setting::set('clinic.address', 'Jr. Apurímac 648');
        Setting::set('clinic.district', 'Juliaca');
        Setting::set('clinic.whatsapp', '912 345 678');

        $this->getJson(route('api.v1.pages.contact'))
            ->assertOk()
            ->assertJsonPath('meta.title', 'Contacto y cómo llegar · Clínica Vega')
            ->assertJsonPath('meta.description', 'Cómo llegar a Clínica Vega: Jr. Apurímac 648, Juliaca. Horario de atención, WhatsApp y mapa. Escríbenos o reserva tu cita en línea. Te esperamos.')
            ->assertJsonPath('meta.json_ld.0.@type', 'Dentist')
            // Para Google, el teléfono va con el código del país.
            ->assertJsonPath('meta.json_ld.0.telephone', '+51 912 345 678');

        Setting::set('clinic.phone', '051 321456');
        $this->getJson(route('api.v1.pages.contact'))->assertJsonPath('meta.json_ld.0.telephone', '+51 51 321456');

        $this->getJson(route('api.v1.pages.privacy'))
            ->assertOk()
            ->assertJsonPath('data', ['clinic' => 'Clínica Vega', 'address' => 'Jr. Apurímac 648', 'whatsapp' => '912 345 678'])
            ->assertJsonPath('meta.title', 'Política de privacidad · Clínica Vega')
            ->assertJsonPath('meta.description', 'Cómo Clínica Vega cuida tus datos personales y de salud (Ley N.º 29733): qué datos usamos, para qué, quién los ve, cuánto los guardamos y tus derechos.');
    }

    public function test_each_page_tells_google_what_it_has_whose_it_is_and_where_in_150_to_160_letters(): void
    {
        Setting::set('clinic.name', 'Clínica Vega Implant');
        Setting::set('clinic.address', 'Jr. Apurímac 648');
        Setting::set('clinic.district', 'Juliaca');
        Treatment::factory()->evaluation()->create([
            'name' => 'Evaluación o primera consulta',
            'slug' => 'evaluacion',
            'summary' => 'Revisión completa, diagnóstico y presupuesto. Si no sabes qué necesitas, empieza aquí.',
            'price_from' => 50,
        ]);
        Treatment::factory()->create(['name' => 'Limpieza dental', 'slug' => 'limpieza-dental', 'summary' => 'Quita el sarro y las manchas.', 'price_from' => 120]);
        $this->doctorOffering(...Treatment::all());

        $description = fn (string $route, mixed ...$parameters): string => $this->getJson(route($route, $parameters))->assertOk()->json('meta.description');

        $this->assertSame('Clínica Vega Implant: implantes y odontología en Jr. Apurímac 648, Juliaca. Mira los precios y los horarios libres, y reserva tu cita en línea en 2 minutos.', $description('api.v1.home'));
        $this->assertSame('Limpieza dental en Juliaca. Quita el sarro y las manchas. Precio referencial: desde S/ 120. Reserva en línea en Clínica Vega Implant.', $description('api.v1.treatments.show', 'limpieza-dental'));
        // Con un resumen largo, el precio y la reserva entran en su versión corta: el precio no se cae.
        $this->assertSame('Evaluación o primera consulta en Juliaca. Revisión completa, diagnóstico y presupuesto. Si no sabes qué necesitas, empieza aquí. Desde S/ 50. Reserva en línea.', $description('api.v1.treatments.show', 'evaluacion'));

        foreach (['api.v1.home', 'api.v1.treatments.index', 'api.v1.team', 'api.v1.faqs', 'api.v1.promotions.index', 'api.v1.booking.options'] as $route) {
            $this->assertThat(mb_strlen($description($route)), $this->logicalAnd($this->greaterThanOrEqual(150), $this->lessThanOrEqual(160)), $route);
        }
    }
}
