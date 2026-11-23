<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Web\SpaDocument;
use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteException;
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

    /**
     * El código de cada página que el frontend baja aparte (frontend/src/app/router.tsx): el documento lo pide junto
     * con el archivo principal, y la página abierta directamente sale de una vez, sin esperar a pedirlo después. El
     * inicio viene en el archivo principal.
     *
     * @var array<string, string>
     */
    public const MODULES = [
        'site.treatments' => 'src/pages/TreatmentsPage.tsx',
        'site.treatment' => 'src/pages/TreatmentPage.tsx',
        'site.promotions' => 'src/pages/PromotionsPage.tsx',
        'site.promotion' => 'src/pages/PromotionPage.tsx',
        'site.team' => 'src/pages/TeamPage.tsx',
        'site.cases' => 'src/pages/CasesPage.tsx',
        'site.reviews' => 'src/pages/ReviewsPage.tsx',
        'site.faq' => 'src/pages/FaqPage.tsx',
        'site.contact' => 'src/pages/ContactPage.tsx',
        'site.privacy' => 'src/pages/PrivacyPage.tsx',
        'site.booking' => 'src/pages/booking/BookingPage.tsx',
        'site.account' => 'src/pages/account/AccountPage.tsx',
        'site.account.change' => 'src/pages/change/ChangePage.tsx',
        'site.account.health' => 'src/pages/account/HealthPage.tsx',
        'patient.link' => 'src/pages/link/LinkPage.tsx',
        'patient.link.change' => 'src/pages/change/ChangePage.tsx',
    ];

    public function __invoke(Request $request, SpaDocument $document, Vite $vite): Response
    {
        $page = $request->route()->getName();
        [$resource, $query] = self::PAGES[$page] + [1 => []];

        $data = $document->forPage(
            $request,
            $resource,
            $request->route()->parameters(),
            array_filter($request->only($query), fn (mixed $value): bool => is_string($value) && $value !== ''),
        );

        return response()->view('spa', [...$data, 'module' => $this->moduleUrl($vite, $page)], $data['status']);
    }

    /** La dirección del código de la página, o null si viene en el archivo principal o no está en la compilación. */
    private function moduleUrl(Vite $vite, string $page): ?string
    {
        if (! isset(self::MODULES[$page])) {
            return null;
        }

        try {
            return $vite->asset(self::MODULES[$page], 'app') ?: null;
        } catch (ViteException) {
            // Sin él la página abre igual: el frontend lo pide al pintarla.
            return null;
        }
    }
}
