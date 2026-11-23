<?php

namespace App\Http\Controllers\Api\V1\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ReviewResource;
use App\Models\Review;
use App\Services\Web\PageMeta;
use App\Services\Web\StructuredData;

/** Opiniones publicadas: se copian de Google tal cual, la web no inventa reseñas. Sin ninguna, la página no existe. */
class ReviewController extends Controller
{
    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function __invoke(StructuredData $structuredData, PageMeta $meta): array
    {
        // Sin opiniones publicadas la página no existe: ni Google ni quien escribe la dirección llegan a una página vacía.
        abort_unless(Review::query()->published()->exists(), 404);

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
