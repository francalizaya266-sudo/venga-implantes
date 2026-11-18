<?php

namespace App\Http\Resources\V1;

use App\Models\SmileCase;
use App\Services\Web\PublicImages;
use App\Support\Markdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un caso de "Antes y después" que el paciente autorizó a mostrar.
 *
 * @mixin SmileCase
 */
class SmileCaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'before_url' => PublicImages::url($this->before_path),
            'after_url' => PublicImages::url($this->after_path),
            'description_html' => filled($this->description) ? (string) Markdown::paragraphs($this->description) : null,
            'treatment' => $this->whenLoaded('treatment', fn () => $this->treatment
                ? ['slug' => $this->treatment->slug, 'name' => $this->treatment->name]
                : null),
        ];
    }
}
