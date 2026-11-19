<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Support\ApiErrorMessage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Lo que recibe la web cuando algo sale mal: datos que no son del tipo esperado se rechazan con 422 (no con un 500)
 * y los errores que arma Laravel llegan en español y sin nombres de clases, porque la web los muestra tal cual.
 */
class ApiErrorsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_booking_link_with_a_list_where_text_goes_is_rejected_without_breaking(): void
    {
        foreach (['codigo', 'promo', 'tratamiento', 'doctor', 'origen'] as $field) {
            $this->getJson(route('api.v1.booking.options', [$field => ['x']]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }

        $this->getJson(route('api.v1.cases', ['tratamiento' => ['x']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tratamiento');
    }

    public function test_the_page_opened_with_such_a_link_says_it_does_not_exist(): void
    {
        $this->get(route('site.booking', ['codigo' => ['x']]))->assertNotFound();
        $this->get(route('site.cases', ['tratamiento' => ['x']]))->assertNotFound();

        // Con texto, la misma página abre como siempre.
        $this->get(route('site.booking', ['codigo' => 'VERANO']))->assertOk();
    }

    public function test_what_does_not_exist_is_said_in_spanish_without_naming_the_code(): void
    {
        $this->getJson(route('api.v1.treatments.show', 'no-existe'))
            ->assertNotFound()
            ->assertExactJson(['message' => ApiErrorMessage::forStatus(404)]);

        $this->getJson('/api/v1/no-existe')
            ->assertNotFound()
            ->assertJsonPath('message', ApiErrorMessage::forStatus(404));
    }

    public function test_a_wrong_method_and_too_many_attempts_are_said_in_spanish(): void
    {
        $this->deleteJson(route('api.v1.site'))
            ->assertMethodNotAllowed()
            ->assertJsonPath('message', 'Esta dirección no acepta ese tipo de pedido.');

        for ($attempt = 1; $attempt <= 240; $attempt++) {
            $this->getJson(route('api.v1.account.show'));
        }

        $this->getJson(route('api.v1.account.show'))
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Hiciste muchos intentos seguidos. Espera un momento y vuelve a intentarlo.');
    }

    public function test_a_failure_in_the_server_does_not_show_its_details(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('api/v1/prueba-de-falla', fn () => throw new RuntimeException('SQLSTATE: detalle interno'));

        $this->getJson('/api/v1/prueba-de-falla')
            ->assertInternalServerError()
            ->assertExactJson(['message' => 'Algo falló de nuestro lado. Inténtalo de nuevo en unos minutos o escríbenos por WhatsApp.']);
    }
}
