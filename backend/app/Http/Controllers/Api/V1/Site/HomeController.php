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
use App\Http\Resources\V1\WelcomeResource;
use App\Models\Campaign;
use App\Models\Doctor;
use App\Models\Faq;
use App\Models\Review;
use App\Models\SmileCase;
use App\Models\Treatment;
use App\Services\Access\PatientAccess;
use App\Services\Booking\WebAvailability;
use App\Services\Web\PageMeta;
use App\Services\Web\ReturningPatient;
use App\Services\Web\StructuredData;
use App\Support\SiteArt;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Inicio: próximos horarios libres, tratamientos con precio, la especialidad, quién atiende, casos, opiniones, las
 * preguntas principales y contacto. A quien vuelve desde su celular recordado lo saluda primero con lo suyo: su
 * próxima cita, sus sesiones y su control.
 */
class HomeController extends Controller
{
    /** Junto al acceso rápido entran dos filas de tratamientos; el resto está en su página. */
    private const SERVICES_SHOWN = 8;

    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function __invoke(Request $request, WebAvailability $availability, StructuredData $structuredData, PatientAccess $access, ReturningPatient $returning, PageMeta $meta): array
    {
        $device = $access->device($request);

        $bookable = $availability->treatments();
        $featured = $bookable->firstWhere('is_evaluation', true) ?? $bookable->first();
        $treatments = Treatment::query()->active()->where('is_public', true)->orderByDesc('is_evaluation')->orderBy('sort_order')->get();
        // El primero es quien el inicio presenta: con el mismo orden, el que se registró antes.
        $doctors = Doctor::query()->active()->where('is_public', true)->orderBy('sort_order')->orderBy('id')->get();
        $specialty = $this->specialty($doctors, $treatments);

        return [
            'data' => [
                'welcome' => $device ? WelcomeResource::make($returning->for($device->account)) : null,
                'can_book' => $featured !== null,
                'next_free' => $featured ? NextFreeResource::for($featured, $availability->nextFree($featured)) : null,
                'campaigns' => CampaignResource::collection(Campaign::query()->published()->where('show_on_home', true)->with('treatment')->latest('starts_at')->get()),
                'treatments' => TreatmentResource::collection($treatments->take(self::SERVICES_SHOWN)),
                'treatments_count' => $treatments->count(),
                'shows_prices' => $treatments->contains(fn (Treatment $treatment) => $treatment->webPrice() !== null),
                'specialty' => $specialty ? TreatmentResource::make($specialty) : null,
                'doctor' => $doctors->isNotEmpty() ? DoctorResource::make($doctors->first()) : null,
                'other_doctors' => max(0, $doctors->count() - 1),
                'cases' => SmileCaseResource::collection(SmileCase::query()->published()->with('treatment')->limit(3)->get()),
                'reviews' => ReviewResource::collection(Review::query()->published()->limit(3)->get()),
                'faqs' => FaqResource::collection(Faq::query()->published()->whereNull('treatment_id')->limit(4)->get()),
            ],
            'meta' => $meta->for(jsonLd: [$structuredData->dentist()]),
        ];
    }

    /**
     * El tratamiento que el inicio destaca como "Nuestra especialidad": el de la especialidad que declara un
     * doctor (un implantólogo y el tratamiento de implantes). Sin especialidad o sin ese tratamiento, ninguno.
     *
     * @param  Collection<int, Doctor>  $doctors
     * @param  Collection<int, Treatment>  $treatments
     */
    private function specialty(Collection $doctors, Collection $treatments): ?Treatment
    {
        foreach ($doctors->map(fn (Doctor $doctor) => SiteArt::specialty($doctor->specialty))->filter()->unique() as $image) {
            if ($treatment = $treatments->first(fn (Treatment $treatment) => SiteArt::keyFor($treatment) === $image)) {
                return $treatment;
            }
        }

        return null;
    }
}
