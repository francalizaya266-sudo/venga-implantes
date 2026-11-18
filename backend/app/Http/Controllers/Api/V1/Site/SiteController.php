<?php

namespace App\Http\Controllers\Api\V1\Site;

use App\Http\Controllers\Controller;
use App\Services\Web\ClinicInfo;
use App\Services\Web\PageTexts;
use App\Support\CampaignText;
use App\Support\Markdown;
use App\Support\Phone;

/**
 * Lo que comparten todas las páginas de la web: los datos del consultorio, su horario, qué secciones tienen
 * contenido y el encabezado y el cierre de cada página, tal como se editan en el panel.
 */
class SiteController extends Controller
{
    /**
     * @return array{data: array<string, mixed>}
     */
    public function __invoke(ClinicInfo $clinic, PageTexts $texts): array
    {
        $now = now()->toImmutable();
        $hours = $clinic->hours();
        $whatsapp = $clinic->whatsapp();

        return ['data' => [
            'clinic' => [
                'name' => $clinic->name(),
                'tagline' => $clinic->district() ? 'Dentistas en '.$clinic->district() : null,
                'district' => $clinic->district(),
                'logo_url' => $clinic->logoUrl(),
                'address' => $clinic->fullAddress(),
                'directions_url' => $clinic->directionsUrl(),
                'map_embed_url' => $clinic->mapEmbedUrl(),
                'whatsapp' => $whatsapp ? ['number' => Phone::forWhatsApp($whatsapp), 'display' => Phone::display($whatsapp)] : null,
                'phone' => $clinic->phone() ? ['display' => $clinic->phone(), 'href' => $clinic->phoneHref()] : null,
                'call_href' => $clinic->callHref(),
                'email' => $clinic->email(),
                'socials' => collect($clinic->socials())->map(fn (string $url, string $network) => ['network' => $network, 'url' => $url])->values(),
                'reviews_url' => $clinic->reviewsUrl(),
                'google_rating' => $clinic->googleRating(),
                'hours' => [
                    'lines' => $hours->isEmpty() ? [] : $hours->lines(),
                    'note' => $clinic->hoursNote(),
                    'status' => $hours->status($now),
                    'is_open' => $hours->isOpen($now),
                ],
            ],
            'home' => [
                'tagline' => $clinic->tagline(),
                'title' => $this->homeTitle($clinic->headline()),
                'intro' => $clinic->intro(),
                'about_html' => filled($clinic->about()) ? (string) Markdown::paragraphs($clinic->about()) : null,
                'hero_image_url' => $clinic->heroImageUrl(),
            ],
            'sections' => $clinic->sections(),
            'pages' => collect(array_keys(PageTexts::PAGES))->mapWithKeys(fn (string $page) => [$page => $this->page($texts, $page)]),
            'closing' => $texts->closing(null),
            'team_values' => $texts->teamValues(),
            'how_to_pay' => CampaignText::HOW_TO_PAY,
            'year' => $now->year,
        ]];
    }

    /**
     * El encabezado y el cierre de una página con textos editables.
     *
     * @return array{kicker: string|null, icon: string, title: array{0: string, 1: string, 2: string}, lead: string|null, photo_url: string|null, closing: array{title: string, text: string|null, whatsapp: bool}}
     */
    private function page(PageTexts $texts, string $page): array
    {
        return [
            'kicker' => $texts->kicker($page),
            'icon' => $texts->icon($page),
            'title' => $texts->titleParts($page),
            'lead' => $texts->lead($page),
            'photo_url' => $texts->photoUrl($page),
            'closing' => $texts->closing($page),
        ];
    }

    /**
     * El título del inicio en dos tonos, como la marca: la segunda mitad va en celeste y en cursiva. Si la primera
     * mitad trae una coma, lo que le sigue va en letra delgada: "Tu sonrisa," fuerte y "nuestra" delgada.
     *
     * @return array{strong: string, thin: string, accent: string}
     */
    private function homeTitle(string $headline): array
    {
        $words = preg_split('/\s+/', $headline, flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $firstHalf = (int) ceil(count($words) / 2);
        $start = implode(' ', array_slice($words, 0, $firstHalf));
        $comma = mb_strpos($start, ',');

        return [
            'strong' => $comma === false ? $start : mb_substr($start, 0, $comma + 1),
            'thin' => $comma === false ? '' : trim(mb_substr($start, $comma + 1)),
            'accent' => implode(' ', array_slice($words, $firstHalf)),
        ];
    }
}
