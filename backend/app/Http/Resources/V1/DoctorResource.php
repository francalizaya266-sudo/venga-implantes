<?php

namespace App\Http\Resources\V1;

use App\Models\Doctor;
use App\Models\Treatment;
use App\Services\Web\PublicImages;
use App\Support\Initials;
use App\Support\Markdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Un doctor tal como se presenta en la web: especialidad, colegiatura, presentación y formación.
 *
 * @mixin Doctor
 */
class DoctorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'specialty' => $this->specialty,
            'cop_number' => $this->cop_number,
            'rne_number' => $this->rne_number,
            'photo_url' => PublicImages::url($this->photo_path),
            'initials' => Initials::of($this->name),
            'bio_short' => filled($this->bio) ? Str::limit($this->bio, 160) : null,
            'bio_html' => filled($this->bio) ? (string) Markdown::paragraphs($this->bio) : null,
            'education' => collect(preg_split('/\R/', trim((string) $this->education)) ?: [])
                ->map(fn (string $line) => trim($line))
                ->filter()
                ->values(),
            'accepts_online_booking' => (bool) $this->accepts_online_booking,
            'treatments' => $this->whenLoaded('treatments', fn () => $this->treatments
                ->map(fn (Treatment $treatment) => ['slug' => $treatment->slug, 'name' => $treatment->name])
                ->values()),
        ];
    }
}
