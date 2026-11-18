<?php

namespace App\Http\Resources\V1;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una opinión publicada: en la web solo sale el nombre y la inicial del apellido.
 *
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'body' => $this->body,
            'author' => $this->shortName(),
            'source' => $this->source->getLabel(),
            'url' => $this->url,
            'date' => $this->reviewed_on?->translatedFormat('F Y'),
        ];
    }
}
