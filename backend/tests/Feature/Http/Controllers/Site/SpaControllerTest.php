<?php

namespace Tests\Feature\Http\Controllers\Site;

use App\Enums\MagicLinkPurpose;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Campaign;
use App\Models\Patient;
use App\Models\RememberedDevice;
use App\Models\Setting;
use App\Models\Treatment;
use App\Services\Access\DeviceService;
use App\Services\Access\MagicLinkService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

class SpaControllerTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
    }

    /**
     * Lo que Laravel deja escrito en el documento para el frontend: las respuestas de la API, por su dirección.
     *
     * @return array<string, array{status: int, body: mixed}>
     */
    private function preloaded(TestResponse $response): array
    {
        $this->assertSame(1, preg_match('#<script id="preload" type="application/json">(.*?)</script>#s', $response->getContent(), $found));

        return json_decode($found[1], true, flags: JSON_THROW_ON_ERROR);
    }

    /** Un celular recordado de la cuenta, como el que deja el enlace de WhatsApp. */
    private function rememberDevice(Account $account): void
    {
        RememberedDevice::create(['account_id' => $account->id, 'token_hash' => hash('sha256', 'este-celular'), 'expires_at' => now()->addMonth()]);
        $this->withCookie(DeviceService::COOKIE, 'este-celular');
    }

    public function test_a_page_opens_with_what_google_reads_and_the_data_the_frontend_would_ask_the_api_for(): void
    {
        Setting::set('clinic.name', 'Clínica Vega');
        Setting::set('clinic.district', 'Juliaca');
        Treatment::factory()->create(['name' => 'Limpieza dental', 'slug' => 'limpieza', 'summary' => 'Deja tus dientes sin sarro.']);

        $response = $this->get(route('site.treatment', 'limpieza'));

        $response->assertOk()
            ->assertSee('<title>Limpieza dental en Juliaca · Clínica Vega</title>', false)
            ->assertSee('<meta name="description" content="Deja tus dientes sin sarro.">', false)
            ->assertSee('<link rel="canonical" href="'.route('site.treatment', 'limpieza').'">', false)
            ->assertSee('<meta property="og:title" content="Limpieza dental en Juliaca">', false)
            ->assertSee('<script type="application/ld+json">{"@context":"https://schema.org","@type":"BreadcrumbList"', false)
            ->assertDontSee('name="robots"', false)
            // La página la pinta el frontend dentro de #app.
            ->assertSee('<div id="app"></div>', false);

        $preloaded = $this->preloaded($response);
        $this->assertSame(['/api/v1/site', '/api/v1/treatments/limpieza'], array_keys($preloaded));
        $this->assertSame('Clínica Vega', $preloaded['/api/v1/site']['body']['data']['clinic']['name']);
        // Lo mismo que respondería la API: el frontend no tiene que pedirlo otra vez.
        $this->assertSame(
            ['status' => 200, 'body' => $this->getJson(route('api.v1.treatments.show', 'limpieza'))->json()],
            $preloaded['/api/v1/treatments/limpieza'],
        );
    }

    /**
     * La ruta de cada página de la web y el recurso de la API con que abre.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function pages(): array
    {
        return [
            'inicio' => ['site.home', '/api/v1/home'],
            'servicios' => ['site.treatments', '/api/v1/treatments'],
            'promociones' => ['site.promotions', '/api/v1/promotions'],
            'equipo' => ['site.team', '/api/v1/team'],
            'antes y después' => ['site.cases', '/api/v1/cases'],
            'opiniones' => ['site.reviews', '/api/v1/reviews'],
            'preguntas frecuentes' => ['site.faq', '/api/v1/faqs'],
            'contacto' => ['site.contact', '/api/v1/pages/contact'],
            'privacidad' => ['site.privacy', '/api/v1/pages/privacy'],
            'reservar' => ['site.booking', '/api/v1/booking/options'],
            'mi cuenta' => ['site.account', '/api/v1/account'],
        ];
    }

    #[DataProvider('pages')]
    public function test_each_page_of_the_web_opens_with_its_data(string $route, string $resource): void
    {
        $response = $this->get(route($route));

        $response->assertOk();
        $preloaded = $this->preloaded($response);
        $this->assertSame(['/api/v1/site', $resource], array_keys($preloaded));
        $this->assertSame(200, $preloaded[$resource]['status']);
        $this->assertArrayHasKey('data', $preloaded[$resource]['body']);
    }

    public function test_what_the_address_asks_for_reaches_the_data_of_the_page(): void
    {
        $treatment = Treatment::factory()->create(['slug' => 'limpieza']);
        $this->doctorOffering($treatment);

        $response = $this->get(route('site.booking', ['tratamiento' => 'limpieza', 'origen' => 'instagram', 'utm_source' => 'anuncio']));

        $response->assertOk();
        $preloaded = $this->preloaded($response);
        // Solo viaja lo que la página entiende: lo demás de la dirección no cambia sus datos.
        $this->assertSame(['/api/v1/site', '/api/v1/booking/options?origen=instagram&tratamiento=limpieza'], array_keys($preloaded));
        $this->assertSame($treatment->id, $preloaded['/api/v1/booking/options?origen=instagram&tratamiento=limpieza']['body']['data']['initial_treatment_id']);
    }

    public function test_a_treatment_that_is_not_public_answers_404_and_stays_out_of_google(): void
    {
        Treatment::factory()->create(['slug' => 'interno', 'is_public' => false]);

        $response = $this->get(route('site.treatment', 'interno'));

        $response->assertNotFound()
            ->assertSee('<title>No encontramos esta página · ', false)
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertDontSee('rel="canonical"', false);
        // El frontend recibe el mismo 404 que le daría la API y muestra la página del error.
        $this->assertSame(404, $this->preloaded($response)['/api/v1/treatments/interno']['status']);
    }

    public function test_a_promotion_that_ended_answers_404_and_carries_the_current_ones(): void
    {
        Campaign::query()->create(['name' => 'Limpieza de verano', 'slug' => 'limpieza-verano', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        Campaign::query()->create(['name' => 'Blanqueamiento de invierno', 'slug' => 'blanqueamiento-invierno', 'starts_at' => now()->subMonths(3), 'ends_at' => now()->subMonth()]);

        $response = $this->get(route('site.promotion', 'blanqueamiento-invierno'));

        $response->assertNotFound()->assertSee('<title>Promoción terminada · ', false);
        $ended = $this->preloaded($response)['/api/v1/promotions/blanqueamiento-invierno'];
        $this->assertSame(404, $ended['status']);
        $this->assertSame('Blanqueamiento de invierno', $ended['body']['data']['ended']);
        $this->assertSame(['limpieza-verano'], array_column($ended['body']['data']['campaigns'], 'slug'));
    }

    public function test_my_account_opens_with_the_family_of_the_remembered_device_and_stays_out_of_google(): void
    {
        $account = Account::factory()->verified()->create();
        Patient::factory()->for($account)->create(['first_name' => 'Rosa']);
        $this->rememberDevice($account);

        $response = $this->get(route('site.account'));

        $response->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertDontSee('rel="canonical"', false);
        $page = $this->preloaded($response)['/api/v1/account']['body']['data'];
        $this->assertFalse($page['guest']);
        $this->assertSame('Rosa', $page['holder']['first_name']);
    }

    public function test_a_device_that_is_not_remembered_opens_my_account_as_a_guest(): void
    {
        $response = $this->get(route('site.account'));

        $response->assertOk();
        $this->assertSame(['guest' => true], $this->preloaded($response)['/api/v1/account']['body']['data']);
    }

    public function test_changing_an_appointment_of_another_family_answers_403_and_one_that_does_not_exist_404(): void
    {
        $account = Account::factory()->verified()->create();
        Patient::factory()->for($account)->create();
        $this->rememberDevice($account);
        $appointment = Appointment::factory()->at('2026-11-04 10:00')->create();

        $forbidden = $this->get(route('site.account.change', $appointment));
        $missing = $this->get(route('site.account.change', $appointment->id + 1));

        $forbidden->assertForbidden()->assertSee('<title>No puedes ver esta página · ', false);
        $this->assertSame(403, $this->preloaded($forbidden)["/api/v1/account/appointments/{$appointment->id}/change"]['status']);
        $missing->assertNotFound()->assertSee('<title>No encontramos esta página · ', false);
    }

    public function test_the_whatsapp_link_opens_with_its_appointment_and_one_that_is_no_longer_valid_says_so_without_an_error(): void
    {
        $appointment = Appointment::factory()->at('2026-11-04 10:00')->create();
        $token = basename(app(MagicLinkService::class)->issue($appointment->patient->account, MagicLinkPurpose::Appointment, $appointment));
        $unknown = str_repeat('x', 32);

        $valid = $this->get(route('patient.link', $token));
        $expired = $this->get(route('patient.link', $unknown));
        $change = $this->get(route('patient.link.change', $unknown));

        $valid->assertOk()->assertSee('<title>Tu cita · ', false);
        $this->assertSame('appointment', $this->preloaded($valid)["/api/v1/links/{$token}"]['body']['data']['kind']);
        // Un enlace vencido no es un error: la página explica qué pasó y por dónde seguir.
        $expired->assertOk()->assertSee('<title>Enlace vencido · ', false);
        $this->assertSame(['kind' => 'expired'], $this->preloaded($expired)["/api/v1/links/{$unknown}"]['body']['data']);
        // Pero sin cita no hay horario que cambiar.
        $change->assertNotFound();
    }

    public function test_an_address_that_does_not_exist_opens_the_web_with_the_clinic_data_to_explain_the_error(): void
    {
        $response = $this->get('/esto-no-existe');

        $response->assertNotFound()->assertSee('<div id="app" data-error="404"></div>', false);
        $this->assertSame(['/api/v1/site'], array_keys($this->preloaded($response)));
    }
}
