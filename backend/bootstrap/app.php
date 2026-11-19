<?php

use App\Http\Middleware\AddRequestContext;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiErrorMessage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // La API de la web vive en el mismo dominio que el frontend: usa las cookies y la sesión del grupo "web".
        then: function (): void {
            Route::middleware('web')
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AddRequestContext::class);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // La web muestra el "message" de la API tal cual: los errores que arma Laravel (404, 429, 500…) salen en
        // español y sin nombres de clases. Una respuesta que el código armó a propósito se deja como está.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            $message = ApiErrorMessage::forStatus($response->getStatusCode());

            if ($message === null || ! $request->is('api/*') || ! $response instanceof JsonResponse || $e instanceof HttpResponseException) {
                return $response;
            }

            return $response->setData([...(array) $response->getData(true), 'message' => $message]);
        });
    })->create();
