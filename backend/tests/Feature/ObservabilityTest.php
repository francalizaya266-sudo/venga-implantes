<?php

namespace Tests\Feature;

use App\Http\Middleware\AddRequestContext;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Lo que mira quien opera el sistema en producción: /up, que vigilan Azure y GitHub Actions, y las líneas del
 * registro "json", que lee Wazuh.
 */
class ObservabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $logFolder;

    protected function setUp(): void
    {
        parent::setUp();

        // Lo que pasa en producción (APP_DEBUG=false): con debug, Laravel muestra el error en vez de responder.
        config(['app.debug' => false]);
        $this->logFolder = sys_get_temp_dir().'/eventos-'.Str::random(8);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->logFolder);

        parent::tearDown();
    }

    public function test_up_answers_when_laravel_and_the_database_answer(): void
    {
        $this->getJson('/up')->assertOk()->assertExactJson(['status' => 'up']);
    }

    public function test_up_fails_when_the_database_does_not_answer(): void
    {
        config(['database.connections.sqlite.database' => $this->logFolder.'/no-existe.sqlite']);
        DB::purge('sqlite');

        $this->getJson('/up')->assertInternalServerError()->assertExactJson(['status' => 'down']);
    }

    public function test_each_request_gets_its_own_id_in_the_answer(): void
    {
        $first = $this->getJson('/up')->headers->get(AddRequestContext::HEADER);
        $second = $this->getJson('/up')->headers->get(AddRequestContext::HEADER);

        $this->assertTrue(Str::isUuid($first));
        $this->assertTrue(Str::isUuid($second));
        $this->assertNotSame($first, $second);
    }

    public function test_a_json_log_line_says_which_request_route_and_user_it_came_from(): void
    {
        config(['logging.channels.json.path' => $this->logFolder.'/eventos.log']);
        Route::middleware('web')->get('prueba-de-registro/{token}', fn () => Log::channel('json')->warning('Algo para revisar'))
            ->name('prueba.registro');
        $user = User::factory()->reception()->create();

        $response = $this->actingAs($user)->get('prueba-de-registro/token-del-paciente')->assertOk();

        $files = File::glob($this->logFolder.'/eventos-*.log');
        $this->assertCount(1, $files);
        $line = json_decode(trim(File::get($files[0])), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Algo para revisar', $line['message']);
        $this->assertSame('WARNING', $line['level_name']);
        $this->assertSame($response->headers->get(AddRequestContext::HEADER), $line['extra']['request_id']);
        $this->assertSame('GET', $line['extra']['method']);
        $this->assertSame('prueba.registro', $line['extra']['route']);
        $this->assertSame($user->id, $line['extra']['user_id']);
        // La dirección no se guarda: los enlaces del paciente llevan su token.
        $this->assertStringNotContainsString('token-del-paciente', File::get($files[0]));
    }
}
