<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Web\SpaDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Las páginas de la web. Todas entregan el mismo documento, el que carga el frontend (React), con el título, la
 * descripción y los datos de la página que se abrió ya escritos en él.
 */
class SpaController extends Controller
{
    /**
     * El recurso de la API que entrega los datos de cada página y los parámetros de consulta que le pasa.
     *
     * @var array<string, array{0: string, 1?: list<string>}>
     */
    private const PAGES = [
        'site.home' => ['api.v1.home'],
        'site.treatments' => ['api.v1.treatments.index'],
        'site.treatment' => ['api.v1.treatments.show'],
        'site.promotions' => ['api.v1.promotions.index'],
        'site.promotion' => ['api.v1.promotions.show'],
        'site.team' => ['api.v1.team'],
        'site.cases' => ['api.v1.cases', ['tratamiento']],
        'site.reviews' => ['api.v1.reviews'],
        'site.faq' => ['api.v1.faqs'],
        'site.contact' => ['api.v1.pages.contact'],
        'site.privacy' => ['api.v1.pages.privacy'],
        'site.booking' => ['api.v1.booking.options', ['codigo', 'promo', 'origen', 'doctor', 'tratamiento', 'para']],
        'site.account' => ['api.v1.account.show'],
        'site.account.change' => ['api.v1.account.appointments.change'],
        'site.account.health' => ['api.v1.account.patients.health'],
        'patient.link' => ['api.v1.links.show'],
        'patient.link.change' => ['api.v1.links.change'],
    ];

    public function __invoke(Request $request, SpaDocument $document): Response
    {
        [$resource, $query] = self::PAGES[$request->route()->getName()] + [1 => []];

        $data = $document->forPage(
            $request,
            $resource,
            $request->route()->parameters(),
            array_filter($request->only($query), fn (mixed $value): bool => is_string($value) && $value !== ''),
        );

        return response()->view('spa', $data, $data['status']);
    }
}
