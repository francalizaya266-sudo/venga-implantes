<?php

namespace App\Http\Resources\V1;

use App\Models\Campaign;
use App\Services\Web\PublicImages;
use App\Support\CampaignText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una promoción vigente tal como se anuncia en la web.
 *
 * @mixin Campaign
 */
class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'summary' => $this->summary,
            'image_url' => PublicImages::url($this->image_path),
            'until' => CampaignText::until($this->resource),
            'discount' => CampaignText::discount($this->resource),
            'discount_beside_name' => CampaignText::discountBesideName($this->resource),
            'treatment' => $this->whenLoaded('treatment', fn () => $this->treatment
                ? ['slug' => $this->treatment->slug, 'name' => $this->treatment->name]
                : null),
        ];
    }
}
