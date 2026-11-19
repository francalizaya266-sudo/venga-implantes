<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le da a cada petición un identificador que va en todo lo que se registre mientras dura (canal "json") y vuelve en
 * la cabecera X-Request-Id: con él se encuentran juntas las líneas de una misma falla. La ruta y el usuario los
 * agrega AppServiceProvider cuando se conocen. No se guarda la dirección: los enlaces del paciente llevan su token.
 */
class AddRequestContext
{
    public const HEADER = 'X-Request-Id';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();

        Context::add([
            'request_id' => $requestId,
            'method' => $request->method(),
        ]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
