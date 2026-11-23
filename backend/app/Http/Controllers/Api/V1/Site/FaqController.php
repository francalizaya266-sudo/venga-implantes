<?php

namespace App\Http\Controllers\Api\V1\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\FaqResource;
use App\Models\Faq;
use App\Services\Web\PageMeta;
use App\Services\Web\StructuredData;
use App\Support\SiteArt;
use Illuminate\Database\Eloquent\Collection;

/** Preguntas frecuentes, agrupadas por tema: primero las generales y después las de cada servicio. */
class FaqController extends Controller
{
    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function __invoke(StructuredData $structuredData, PageMeta $meta): array
    {
        $faqs = Faq::query()->published()->with('treatment')->get();

        $topics = $faqs->groupBy(fn (Faq $faq) => (string) $faq->treatment?->slug)
            ->map(function (Collection $questions, string $slug) {
                $treatment = $questions->first()->treatment;

                return [
                    'id' => $slug === '' ? 'faq-generales' : 'faq-de-'.$slug,
                    'name' => $treatment?->name ?? 'Generales',
                    'treatment' => $treatment ? ['slug' => $treatment->slug, 'art' => SiteArt::keyFor($treatment)] : null,
                    'faqs' => FaqResource::collection($questions),
                ];
            })
            ->sortBy(fn (array $topic) => $topic['treatment'] === null ? 0 : 1)
            ->values();

        return [
            'data' => ['topics' => $topics],
            'meta' => $meta->for(
                page: 'preguntas',
                jsonLd: array_filter([
                    $structuredData->breadcrumbs(['Preguntas frecuentes' => route('site.faq')]),
                    $faqs->isNotEmpty() ? $structuredData->faqPage($faqs) : null,
                ]),
            ),
        ];
    }
}
