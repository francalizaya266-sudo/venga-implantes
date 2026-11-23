<?php

namespace App\Http\Controllers\Api\V1\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CampaignResource;
use App\Models\Campaign;
use App\Models\TreatmentPackageItem;
use App\Services\Booking\WebAvailability;
use App\Services\Clinical\TreatmentPlanService;
use App\Services\Web\ClinicInfo;
use App\Services\Web\PageMeta;
use App\Services\Web\PublicImages;
use App\Services\Web\StructuredData;
use App\Support\CampaignText;
use App\Support\Markdown;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Promociones vigentes; cada una con su página para compartir en redes y WhatsApp. */
class PromotionController extends Controller
{
    public function __construct(
        private readonly StructuredData $structuredData,
        private readonly PageMeta $meta,
    ) {}

    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function index(): array
    {
        return [
            'data' => ['campaigns' => CampaignResource::collection($this->published())],
            'meta' => $this->meta->for(
                page: 'promociones',
                jsonLd: [$this->structuredData->breadcrumbs(['Promociones' => route('site.promotions')])],
            ),
        ];
    }

    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}|JsonResponse
     */
    public function show(Request $request, string $slug, WebAvailability $availability, ClinicInfo $clinic): array|JsonResponse
    {
        $campaign = Campaign::query()->where('slug', $slug)->with('treatment', 'package.items.treatment')->first();

        // Una promoción que ya terminó no da error: ofrece las vigentes.
        if ($campaign === null || ! Campaign::query()->published()->whereKey($campaign->getKey())->exists()) {
            return response()->json([
                'message' => $campaign ? 'Esta promoción ya terminó' : 'No encontramos esta promoción',
                'data' => [
                    'ended' => $campaign?->name,
                    'campaigns' => CampaignResource::collection($this->published()),
                ],
                'meta' => $this->meta->for(title: 'Promoción terminada', noindex: true),
            ], 404);
        }

        // Visitas para las métricas de la campaña (sin contar recargas de la misma sesión).
        if (! $request->session()->has("promo-vista.{$campaign->getKey()}")) {
            $request->session()->put("promo-vista.{$campaign->getKey()}", true);
            $campaign->newQuery()->whereKey($campaign->getKey())->increment('views_count');
        }

        // Si su tratamiento no se reserva solo en la web, se reserva la evaluación con la promoción.
        $bookable = $availability->treatments();
        $bookAs = match (true) {
            $campaign->treatment === null => null,
            $bookable->contains('id', $campaign->treatment_id) => $campaign->treatment,
            default => $bookable->firstWhere('is_evaluation', true),
        };
        $package = CampaignText::package($campaign);
        $campaignPrice = $campaign->treatment ? $campaign->priceFor($campaign->treatment) : null;
        $discount = CampaignText::discount($campaign);
        $about = $campaign->summary ?: ($discount ? $discount.' · '.CampaignText::until($campaign) : null);

        return [
            'data' => [
                'campaign' => [
                    ...CampaignResource::make($campaign)->resolve(),
                    'description_html' => filled($campaign->description) ? (string) Markdown::paragraphs($campaign->description) : null,
                    'promo_code' => $campaign->promo_code,
                    'has_slots_left' => $campaign->hasSlotsLeft(),
                    'whatsapp_text' => $campaign->whatsapp_text ?: 'Hola, quiero la promoción "'.$campaign->name.'".',
                    // El precio del tratamiento con la promoción, junto al normal.
                    'price' => $campaignPrice !== null ? [
                        'normal' => $campaign->treatment->webPrice(),
                        'now' => $campaignPrice > 0 ? 'desde '.Money::short($campaignPrice) : 'Gratis',
                    ] : null,
                    'package' => $package ? [
                        'items' => $package->items->map(fn (TreatmentPackageItem $item) => $item->label())->values(),
                        'normal_price' => $package->savings() > 0 ? Money::format($package->normalPrice()) : null,
                        'price' => Money::format($package->price),
                        'savings' => $package->savings() > 0 ? Money::format($package->savings()) : null,
                        'for_two' => (bool) $package->for_two,
                        'payment_options' => TreatmentPlanService::packagePaymentOptions($package),
                        'description' => filled($package->description) ? $package->description : null,
                    ] : null,
                ],
                // Un tratamiento que no se reserva solo empieza por la evaluación, y la promoción va con ella.
                'by_evaluation' => $campaign->treatment !== null && $bookAs !== null && ! $bookAs->is($campaign->treatment),
                'booking' => array_filter([
                    'tratamiento' => $bookAs?->slug,
                    'codigo' => $campaign->promo_code,
                    'promo' => $campaign->promo_code ? null : $campaign->slug,
                    'origen' => 'campaign',
                ]),
            ],
            'meta' => $this->meta->for(
                title: $campaign->name,
                description: $about ? PageMeta::describe(Str::finish($about, '.'), 'Se aplica al pagar en el consultorio.', 'Reserva en línea en '.$clinic->name().'.') : null,
                image: PublicImages::url($campaign->image_path),
                jsonLd: [$this->structuredData->breadcrumbs(['Promociones' => route('site.promotions'), $campaign->name => route('site.promotion', $campaign->slug)])],
            ),
        ];
    }

    /** @return Collection<int, Campaign> */
    private function published(): Collection
    {
        return Campaign::query()->published()->with('treatment', 'package.items.treatment')->latest('starts_at')->get();
    }
}
