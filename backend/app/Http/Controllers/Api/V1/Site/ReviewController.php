<?php

namespace App\Http\Controllers\Api\V1\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ReviewResource;
use App\Models\Review;
use App\Services\Web\PageMeta;
use App\Services\Web\StructuredData;

/** Opiniones publicadas: se copian de Google tal cual, la web no inventa reseñas. */
class ReviewController extends Controller
{
    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function __invoke(StructuredData $structuredData, PageMeta $meta): array
    {
        return [
            'data' => ['reviews' => ReviewResource::collection(Review::query()->published()->with('treatment')->get())],
            'meta' => $meta->for(
                page: 'opiniones',
                description: 'Lo que dicen nuestros pacientes.',
                jsonLd: [$structuredData->breadcrumbs(['Opiniones' => route('site.reviews')])],
            ),
        ];
    }
}
