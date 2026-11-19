<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\AppointmentOrigin;
use App\Enums\PatientRelationship;
use App\Models\MedicalBackground;
use App\Services\Web\PageTexts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * docs/api/openapi.json describe la API tal como es: cada ruta de /api/v1 tiene su operación (con el nombre de la
 * ruta), no hay operaciones de rutas que no existen y las listas de valores son las del código. Que cada respuesta
 * cumpla su esquema lo revisa ChecksApiContract en todas las pruebas que piden la API.
 */
class OpenApiTest extends TestCase
{
    public function test_every_api_route_is_documented_with_its_name_and_nothing_else_is(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteDefinition $route) => str_starts_with((string) $route->getName(), 'api.v1.'))
            ->flatMap(fn (RouteDefinition $route) => collect($route->methods())
                ->reject(fn (string $method) => $method === 'HEAD')
                ->mapWithKeys(fn (string $method) => [
                    $method.' '.substr($route->uri(), strlen('api/v1')) => substr((string) $route->getName(), strlen('api.v1.')),
                ]))
            ->sortKeys()
            ->all();

        $documented = collect(self::openApi()['paths'])
            ->flatMap(fn (array $operations, string $path) => collect($operations)
                ->mapWithKeys(fn (array $operation, string $method) => [strtoupper($method).' '.$path => $operation['operationId']]))
            ->sortKeys()
            ->all();

        $this->assertCount(36, $routes);
        $this->assertSame($routes, $documented);
    }

    public function test_the_lists_of_values_are_the_ones_of_the_code(): void
    {
        $schemas = self::openApi()['components']['schemas'];
        $origins = array_map(fn (AppointmentOrigin $origin) => $origin->value, AppointmentOrigin::cases());

        $this->assertSame(array_map(fn (PatientRelationship $relationship) => $relationship->value, PatientRelationship::cases()), $schemas['Relationship']['enum']);
        $this->assertSame($origins, $schemas['AppointmentOrigin']['enum']);
        // "¿Cómo nos conociste?" no pregunta lo que ya se sabe: que llegó por una promoción o por un referido.
        $this->assertSame(
            array_values(array_diff($origins, [AppointmentOrigin::Campaign->value, AppointmentOrigin::Referral->value])),
            $schemas['OriginRequest']['properties']['origin']['enum'],
        );
        $this->assertSame(array_keys(MedicalBackground::CONDITIONS), $schemas['Condition']['enum']);
        $this->assertSame(array_keys(PageTexts::PAGES), array_keys($schemas['Site']['properties']['pages']['properties']));
    }

    public function test_every_reference_points_to_a_schema_that_exists(): void
    {
        preg_match_all('/"\$ref": "#\/components\/(\w+)\/(\w+)"/', (string) json_encode(self::openApi(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), $references, PREG_SET_ORDER);

        $this->assertNotEmpty($references);

        foreach ($references as [$reference, $group, $name]) {
            $this->assertArrayHasKey($name, self::openApi()['components'][$group], $reference);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: JsonResponse, 3: string}>
     */
    public static function answersOutsideTheDocument(): array
    {
        return [
            'falta un campo' => ['GET', '/api/v1/pages/privacy', new JsonResponse(['data' => ['clinic' => 'Consultorio', 'address' => null]]), 'cuerpo.data.whatsapp: falta.'],
            'sobra un campo' => ['GET', '/api/v1/pages/contact', new JsonResponse(['data' => ['heading' => 'Ubicación', 'otro' => 1]]), 'cuerpo.data.otro: no está documentado.'],
            'otro tipo' => ['GET', '/api/v1/pages/privacy', new JsonResponse(['data' => ['clinic' => 7, 'address' => null, 'whatsapp' => null]]), 'cuerpo.data.clinic: es integer y debe ser string.'],
            'una lista donde va un objeto' => ['GET', '/api/v1/pages/contact', new JsonResponse(['data' => []]), 'cuerpo.data: es array y debe ser object.'],
            'un estado sin documentar' => ['GET', '/api/v1/site', new JsonResponse(['message' => 'No'], 418), 'ese estado no está documentado'],
            'una ruta sin documentar' => ['GET', '/api/v1/no-existe', new JsonResponse(['data' => []]), 'la ruta no está en docs/api/openapi.json'],
        ];
    }

    #[DataProvider('answersOutsideTheDocument')]
    public function test_an_answer_outside_the_document_fails_the_test_that_asked_for_it(string $method, string $uri, JsonResponse $response, string $problem): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($problem);

        $this->assertMatchesApiContract(Request::create($uri, $method), $response);
    }
}
