<?php

namespace App\Http\Resources\V1;

use App\Models\Faq;
use App\Support\Markdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una pregunta frecuente con su respuesta ya convertida a párrafos.
 *
 * @mixin Faq
 */
class FaqResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'answer_html' => (string) Markdown::paragraphs($this->answer),
        ];
    }
}
