<?php

namespace App\Services\Web;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * El documento con que abre la web. El frontend (React) pinta las páginas en el navegador, pero Google y la vista
 * previa de WhatsApp no ejecutan JavaScript: por eso Laravel escribe en el HTML el título, la descripción y los
 * datos estructurados de la página, y deja a mano los mismos datos que el frontend le pediría a la API, para que
 * la página salga completa a la primera. De ahí en adelante el frontend navega pidiéndole a la API.
 *
 * Cada página de la web tiene su recurso en la API (routes/api.php): aquí se ejecuta esa misma acción, sin otra
 * petición, y su respuesta viaja dentro del documento.
 */
final class SpaDocument
{
    /** Lo que comparten todas las páginas: los datos del consultorio y los textos del panel. */
    private const SITE = 'api.v1.site';

    public function __construct(
        private readonly Router $router,
        private readonly PageMeta $meta,
        private readonly ClinicInfo $clinic,
    ) {}

    /**
     * El documento de una página: sus datos y lo que ella dice de sí misma.
     *
     * @param  string  $resource  El nombre de la ruta de la API que entrega los datos de la página.
     * @param  array<string, mixed>  $parameters  Los parámetros de la dirección ("slug", "token"…).
     * @param  array<string, string>  $query  Los parámetros de consulta que la página le pasa a la API.
     * @return array{meta: array<string, mixed>, preload: array<string, array{status: int, body: mixed}>, status: int, error: int|null, clinic: string}
     */
    public function forPage(Request $request, string $resource, array $parameters = [], array $query = []): array
    {
        $page = $this->call($request, $resource, $parameters, $query);

        return $this->document(
            meta: $page['body']['meta'] ?? $this->meta->for(title: self::errorTitle($page['status']), noindex: true),
            preload: [$page['url'] => ['status' => $page['status'], 'body' => $page['body']]],
            request: $request,
            status: in_array($page['status'], [403, 404], true) ? $page['status'] : 200,
        );
    }

    /**
     * El documento de un error (403, 404, 429…): la web, con su encabezado y su pie, diciendo qué pasó.
     *
     * @return array{meta: array<string, mixed>, preload: array<string, array{status: int, body: mixed}>, status: int, error: int|null, clinic: string}
     */
    public function forError(Request $request, int $status): array
    {
        return $this->document(
            meta: $this->meta->for(title: self::errorTitle($status), noindex: true),
            preload: [],
            request: $request,
            status: $status,
            error: $status,
        );
    }

    public static function errorTitle(int $status): string
    {
        return match ($status) {
            403 => 'No puedes ver esta página',
            404 => 'No encontramos esta página',
            419 => 'La página venció',
            429 => 'Demasiados intentos seguidos',
            default => 'Algo falló de nuestro lado',
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, array{status: int, body: mixed}>  $preload
     * @return array{meta: array<string, mixed>, preload: array<string, array{status: int, body: mixed}>, status: int, error: int|null, clinic: string}
     */
    private function document(array $meta, array $preload, Request $request, int $status, ?int $error = null): array
    {
        $site = $this->call($request, self::SITE);

        return [
            'meta' => $meta,
            'preload' => [$site['url'] => ['status' => $site['status'], 'body' => $site['body']], ...$preload],
            'status' => $status,
            'error' => $error,
            'clinic' => $this->clinic->name(),
        ];
    }

    /**
     * Ejecuta la acción de un recurso de la API dentro de esta misma petición (con sus cookies y su sesión) y
     * devuelve lo que el frontend recibiría al pedirlo: la dirección, el estado y el cuerpo.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, string>  $query
     * @return array{url: string, status: int, body: mixed}
     */
    private function call(Request $request, string $resource, array $parameters = [], array $query = []): array
    {
        $url = route($resource, [...$parameters, ...$query], absolute: false);

        try {
            // La ruta se enlaza con su dirección para que resuelva sus modelos ({appointment}, {patient}) como siempre.
            $route = clone $this->router->getRoutes()->getByName($resource);
            $route->bind(Request::create($url));
            $this->router->substituteBindings($route);
            $this->router->substituteImplicitBindings($route);

            $response = Router::toResponse($request, $route->run());
        } catch (ModelNotFoundException|ValidationException) {
            // Un enlace con datos que no se entienden (por ejemplo "?tratamiento[]=") es una dirección que no existe.
            return ['url' => $url, 'status' => 404, 'body' => ['message' => self::errorTitle(404)]];
        } catch (HttpExceptionInterface $e) {
            return ['url' => $url, 'status' => $e->getStatusCode(), 'body' => ['message' => self::errorTitle($e->getStatusCode())]];
        }

        return [
            'url' => $url,
            'status' => $response->getStatusCode(),
            'body' => $response instanceof JsonResponse ? $response->getData(true) : null,
        ];
    }
}
