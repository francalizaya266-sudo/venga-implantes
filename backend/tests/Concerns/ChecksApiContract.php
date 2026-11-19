<?php

namespace Tests\Concerns;

use Illuminate\Http\Request;
use PHPUnit\Framework\Assert;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * El contrato de la API: cada respuesta JSON de /api/v1 que producen las pruebas se compara con docs/api/openapi.json
 * (la ruta, el estado y la forma del cuerpo). Así el documento no se queda atrás del código: si un campo aparece,
 * desaparece o cambia de tipo, la prueba que lo pidió falla y dice dónde.
 *
 * Valida lo que usa el documento de JSON Schema: type (con null), properties, required, additionalProperties, items,
 * minItems, maxItems, enum, const, oneOf, anyOf, minimum, maximum, pattern y $ref.
 */
trait ChecksApiContract
{
    /** @var array<string, mixed>|null */
    private static ?array $openApi = null;

    /**
     * @return array<string, mixed>
     */
    protected static function openApi(): array
    {
        return self::$openApi ??= json_decode(
            (string) file_get_contents(dirname(__DIR__, 3).'/docs/api/openapi.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    protected function assertMatchesApiContract(Request $request, Response $response): void
    {
        $path = $request->getPathInfo();

        if (! str_starts_with($path.'/', '/api/v1/') || ! str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            return;
        }

        $method = strtolower($request->getMethod());
        $status = $response->getStatusCode();
        $where = strtoupper($method).' '.$path.' respondió '.$status;
        $operation = $this->apiOperation(substr($path, strlen('/api/v1')) ?: '/', $method);

        if ($operation === null) {
            // Una dirección que no existe solo puede responder con un error.
            Assert::assertGreaterThanOrEqual(400, $status, $where.': la ruta no está en docs/api/openapi.json.');
            $schema = ['$ref' => '#/components/schemas/Error'];
        } else {
            $documented = $operation['responses'][(string) $status] ?? null;
            Assert::assertNotNull($documented, $where.': ese estado no está documentado en docs/api/openapi.json.');
            $schema = $documented['content']['application/json']['schema'];
        }

        $errors = $this->schemaErrors($schema, json_decode((string) $response->getContent(), flags: JSON_THROW_ON_ERROR), 'cuerpo');

        Assert::assertSame([], $errors, $where.' y no cumple docs/api/openapi.json:'.PHP_EOL.implode(PHP_EOL, $errors));
    }

    /**
     * La operación del documento para una ruta ("/treatments/implantes") y un método.
     *
     * @return array<string, mixed>|null
     */
    private function apiOperation(string $path, string $method): ?array
    {
        foreach (self::openApi()['paths'] as $template => $operations) {
            $parts = array_map(fn (string $part) => preg_quote($part, '#'), preg_split('/\{[^}]+\}/', $template));

            if (preg_match('#^'.implode('[^/]+', $parts).'$#', $path) === 1) {
                return $operations[$method] ?? null;
            }
        }

        return null;
    }

    /**
     * Lo que no cumple el esquema, con dónde está ("cuerpo.data.treatments[0].price: …"). Vacío si cumple.
     *
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private function schemaErrors(array $schema, mixed $value, string $at): array
    {
        if (isset($schema['$ref'])) {
            return $this->schemaErrors($this->schemaAt($schema['$ref']), $value, $at);
        }

        if (isset($schema['oneOf']) || isset($schema['anyOf'])) {
            $failures = array_map(fn (array $option) => $this->schemaErrors($option, $value, $at), $schema['oneOf'] ?? $schema['anyOf']);
            $matches = count(array_filter($failures, fn (array $errors) => $errors === []));

            return match (true) {
                $matches === 1, $matches > 1 && isset($schema['anyOf']) => [],
                $matches > 1 => [$at.': cumple más de una opción de oneOf.'],
                default => [$at.': no cumple ninguna de sus opciones:', ...array_map(fn (string $error) => '  '.$error, array_merge(...$failures))],
            };
        }

        if (array_key_exists('const', $schema) && $value !== $schema['const']) {
            return [$at.': debe ser '.json_encode($schema['const']).' y es '.json_encode($value).'.'];
        }

        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            return [$at.': '.json_encode($value, JSON_UNESCAPED_UNICODE).' no es una de '.json_encode($schema['enum']).'.'];
        }

        if (isset($schema['type'])) {
            $types = (array) $schema['type'];
            $type = $this->jsonType($value);

            if (! in_array($type, $types, true) && ! ($type === 'integer' && in_array('number', $types, true))) {
                return [$at.': es '.$type.' y debe ser '.implode(' o ', $types).'.'];
            }
        }

        return match (true) {
            $value instanceof stdClass => $this->objectErrors($schema, $value, $at),
            is_array($value) => $this->listErrors($schema, $value, $at),
            is_int($value) || is_float($value) => $this->numberErrors($schema, $value, $at),
            is_string($value) && isset($schema['pattern']) && preg_match('#'.str_replace('#', '\#', $schema['pattern']).'#u', $value) !== 1 => [$at.': "'.$value.'" no tiene la forma '.$schema['pattern'].'.'],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private function objectErrors(array $schema, stdClass $value, string $at): array
    {
        $present = get_object_vars($value);
        $errors = array_map(
            fn (string $key) => $at.'.'.$key.': falta.',
            array_values(array_diff($schema['required'] ?? [], array_map('strval', array_keys($present)))),
        );

        foreach ($present as $key => $item) {
            $extra = $schema['additionalProperties'] ?? true;

            array_push($errors, ...match (true) {
                isset($schema['properties'][$key]) => $this->schemaErrors($schema['properties'][$key], $item, $at.'.'.$key),
                $extra === false => [$at.'.'.$key.': no está documentado.'],
                is_array($extra) => $this->schemaErrors($extra, $item, $at.'.'.$key),
                default => [],
            });
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<int, mixed>  $value
     * @return list<string>
     */
    private function listErrors(array $schema, array $value, string $at): array
    {
        $errors = [];

        if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
            $errors[] = $at.': tiene '.count($value).' elementos y debe tener al menos '.$schema['minItems'].'.';
        }

        if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) {
            $errors[] = $at.': tiene '.count($value).' elementos y debe tener como mucho '.$schema['maxItems'].'.';
        }

        foreach (isset($schema['items']) ? $value : [] as $index => $item) {
            array_push($errors, ...$this->schemaErrors($schema['items'], $item, $at.'['.$index.']'));
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private function numberErrors(array $schema, int|float $value, string $at): array
    {
        return match (true) {
            isset($schema['minimum']) && $value < $schema['minimum'] => [$at.': '.$value.' es menor que '.$schema['minimum'].'.'],
            isset($schema['maximum']) && $value > $schema['maximum'] => [$at.': '.$value.' es mayor que '.$schema['maximum'].'.'],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaAt(string $reference): array
    {
        $schema = self::openApi();

        foreach (explode('/', substr($reference, 2)) as $key) {
            $schema = $schema[$key];
        }

        return $schema;
    }

    private function jsonType(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) => 'array',
            default => 'object',
        };
    }
}
