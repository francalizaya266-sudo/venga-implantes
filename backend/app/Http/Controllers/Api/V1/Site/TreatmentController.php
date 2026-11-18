<?php

namespace App\Http\Controllers\Api\V1\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CampaignResource;
use App\Http\Resources\V1\DoctorResource;
use App\Http\Resources\V1\FaqResource;
use App\Http\Resources\V1\NextFreeResource;
use App\Http\Resources\V1\ReviewResource;
use App\Http\Resources\V1\SmileCaseResource;
use App\Http\Resources\V1\TreatmentResource;
use App\Models\Campaign;
use App\Models\Faq;
use App\Models\Review;
use App\Models\SmileCase;
use App\Models\Treatment;
use App\Services\Booking\WebAvailability;
use App\Services\Web\ClinicInfo;
use App\Services\Web\PageMeta;
use App\Services\Web\PublicImages;
use App\Services\Web\StructuredData;
use App\Support\CampaignText;
use App\Support\Markdown;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/** Tratamientos: la lista con duración y precio "desde", y una página por tratamiento. */
class TreatmentController extends Controller
{
    public function __construct(
        private readonly WebAvailability $availability,
        private readonly StructuredData $structuredData,
        private readonly ClinicInfo $clinic,
        private readonly PageMeta $meta,
    ) {}

    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function index(): array
    {
        $district = $this->clinic->district();
        $evaluation = $this->availability->treatments()->firstWhere('is_evaluation', true);

        return [
            'data' => [
                'treatments' => TreatmentResource::collection($this->visible()->get()),
                // El botón dice lo que hace: con evaluación en la web va directo a ella; sin ella, a elegir.
                'evaluation' => $evaluation ? ['slug' => $evaluation->slug] : null,
            ],
            'meta' => $this->meta->for(
                page: 'servicios',
                description: 'Servicios y tratamientos dentales'.($district ? ' en '.$district : '').' con duración y precio referencial. Reserva tu evaluación en línea.',
                jsonLd: [$this->structuredData->breadcrumbs(['Servicios' => route('site.treatments')])],
            ),
        ];
    }

    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function show(string $slug): array
    {
        $treatment = $this->visible()->where('slug', $slug)->firstOrFail();
        $faqs = Faq::query()->published()->whereBelongsTo($treatment)->get();
        $bookable = $this->availability->treatments();
        // Si no se reserva solo, se empieza por la evaluación.
        $bookAs = $bookable->contains('id', $treatment->getKey()) ? $treatment : $bookable->firstWhere('is_evaluation', true);
        $direct = (bool) $bookAs?->is($treatment);

        // La campaña vigente que baja su precio, para mostrarlo y reservar con ella.
        $campaign = Campaign::query()->published()->whereBelongsTo($treatment)->oldest('ends_at')->get()
            ->first(fn (Campaign $campaign) => $campaign->hasSlotsLeft() && $campaign->priceFor($treatment) !== null);
        $campaignPrice = $campaign?->priceFor($treatment);
        $promo = $direct ? $campaign?->slug : null;

        return [
            'data' => [
                'treatment' => [
                    ...TreatmentResource::make($treatment)->resolve(),
                    'description_html' => filled($treatment->description) ? (string) Markdown::toHtml($treatment->description) : null,
                    'image_url' => PublicImages::url($treatment->image_path),
                ],
                'campaign' => $campaign ? [
                    ...CampaignResource::make($campaign)->resolve(),
                    'price' => $campaignPrice > 0 ? 'desde '.Money::short($campaignPrice) : 'Gratis',
                    'until_lower' => mb_strtolower(CampaignText::until($campaign)),
                ] : null,
                // Lo que no se reserva solo empieza por la evaluación: se dice antes del botón, no en letra chica después.
                'booking' => $bookAs ? [
                    'treatment' => $bookAs->slug,
                    'promo' => $promo,
                    'direct' => $direct,
                ] : null,
                'next_free' => $bookAs ? NextFreeResource::for($bookAs, $this->availability->nextFree($bookAs), $promo, $direct ? null : 'Próximo horario para tu evaluación') : null,
                'doctors' => DoctorResource::collection($treatment->doctors()->active()->where('is_public', true)->orderBy('sort_order')->get()),
                'faqs' => FaqResource::collection($faqs),
                'cases' => SmileCaseResource::collection(SmileCase::query()->published()->whereBelongsTo($treatment)->limit(4)->get()),
                'reviews' => ReviewResource::collection(Review::query()->published()->whereBelongsTo($treatment)->limit(3)->get()),
                'others' => TreatmentResource::collection($this->visible()->whereKeyNot($treatment->getKey())->limit(6)->get()),
            ],
            'meta' => $this->meta->for(
                title: $treatment->seo_title ?: $treatment->name.($this->clinic->district() ? ' en '.$this->clinic->district() : ''),
                description: $treatment->seo_description ?: $treatment->summary,
                image: PublicImages::url($treatment->image_path),
                jsonLd: array_filter([
                    $this->structuredData->breadcrumbs(['Servicios' => route('site.treatments'), $treatment->name => route('site.treatment', $treatment->slug)]),
                    $faqs->isNotEmpty() ? $this->structuredData->faqPage($faqs) : null,
                ]),
            ),
        ];
    }

    /** @return Builder<Treatment> */
    private function visible(): Builder
    {
        return Treatment::query()->active()->where('is_public', true)->orderByDesc('is_evaluation')->orderBy('sort_order');
    }
}
