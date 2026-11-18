<?php

namespace App\Http\Controllers\Api\V1\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SmileCaseResource;
use App\Models\SmileCase;
use App\Models\Treatment;
use App\Services\Web\PageMeta;
use App\Services\Web\StructuredData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Antes y después: los casos publicados, con el filtro por tratamiento. */
class SmileCaseController extends Controller
{
    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function __invoke(Request $request, StructuredData $structuredData, PageMeta $meta): array
    {
        $treatments = Treatment::query()->whereHas('smileCases', fn (Builder $query) => $query->published())->orderBy('sort_order')->get();
        $selected = $treatments->firstWhere('slug', (string) $request->query('tratamiento'));

        return [
            'data' => [
                'cases' => SmileCaseResource::collection(
                    SmileCase::query()->published()->with(['treatment', 'doctor'])
                        ->when($selected, fn (Builder $query) => $query->whereBelongsTo($selected))
                        ->get()
                ),
                'treatments' => $treatments->map(fn (Treatment $treatment) => ['slug' => $treatment->slug, 'name' => $treatment->name])->values(),
                'selected' => $selected?->slug,
            ],
            'meta' => $meta->for(
                page: 'casos',
                description: 'Resultados reales de pacientes que autorizaron mostrar sus fotos.',
                jsonLd: [$structuredData->breadcrumbs(['Antes y después' => route('site.cases')])],
            ),
        ];
    }
}
