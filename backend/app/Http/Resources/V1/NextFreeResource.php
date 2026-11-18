<?php

namespace App\Http\Resources\V1;

use App\Models\Treatment;
use App\Support\Dates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Los próximos horarios libres de un tratamiento, sacados de la agenda: el día en palabras y cada hora
 * con los datos para reservarla de un toque.
 *
 * @property-read array{treatment: Treatment, date: string, times: list<string>, promo: string|null, title: string|null} $resource
 */
class NextFreeResource extends JsonResource
{
    /**
     * @param  array{date: string, times: list<string>}|null  $nextFree  Lo que devuelve WebAvailability::nextFree().
     * @param  string|null  $promo  La promoción con que se reserva desde estos horarios.
     * @param  string|null  $title  El título del bloque, si no es "Próximo horario libre".
     */
    public static function for(Treatment $treatment, ?array $nextFree, ?string $promo = null, ?string $title = null): ?self
    {
        return $nextFree === null ? null : new self([...$nextFree, 'treatment' => $treatment, 'promo' => $promo, 'title' => $title]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $treatment = $this->resource['treatment'];

        return [
            'title' => $this->resource['title'] ?? 'Próximo horario libre',
            'date' => $this->resource['date'],
            'day' => Dates::relativeDay($this->resource['date'], withDate: true),
            'times' => collect($this->resource['times'])->map(fn (string $time) => ['time' => $time, 'label' => Dates::time($time)])->values(),
            'treatment' => [
                'slug' => $treatment->slug,
                'name' => $treatment->name,
                'duration_minutes' => (int) $treatment->duration_minutes,
            ],
            'promo' => $this->resource['promo'],
        ];
    }
}
