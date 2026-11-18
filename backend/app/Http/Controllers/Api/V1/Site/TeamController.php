<?php

namespace App\Http\Controllers\Api\V1\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DoctorResource;
use App\Models\Doctor;
use App\Services\Web\PageMeta;
use App\Services\Web\StructuredData;

/** Equipo: cada doctor con su presentación, su formación y lo que atiende. */
class TeamController extends Controller
{
    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function __invoke(StructuredData $structuredData, PageMeta $meta): array
    {
        $doctors = Doctor::query()->active()->where('is_public', true)
            ->with(['treatments' => fn ($query) => $query->active()->where('is_public', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return [
            'data' => ['doctors' => DoctorResource::collection($doctors)],
            'meta' => $meta->for(
                page: 'equipo',
                description: 'Quién te atiende: especialidad, formación y número de colegiatura (COP).',
                jsonLd: [$structuredData->breadcrumbs(['Equipo' => route('site.team')])],
            ),
        ];
    }
}
