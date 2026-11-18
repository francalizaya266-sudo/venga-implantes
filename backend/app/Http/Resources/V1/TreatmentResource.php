<?php

namespace App\Http\Resources\V1;

use App\Models\Treatment;
use App\Support\SiteArt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un servicio tal como lo muestra la web: su nombre, para qué sirve, cuánto dura y su precio "desde".
 *
 * @mixin Treatment
 */
class TreatmentResource extends JsonResource
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
            'summary' => $this->summary,
            'art' => SiteArt::keyFor($this->resource),
            'is_evaluation' => (bool) $this->is_evaluation,
            'duration_minutes' => (int) $this->duration_minutes,
            'sessions_text' => $this->sessionsText(),
            'price' => $this->webPrice(),
        ];
    }
}
