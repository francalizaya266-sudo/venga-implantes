<?php

namespace App\Http\Resources\V1;

use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Elegir día y hora en la web: la tira de días con horarios libres y los horarios del día elegido, con los
 * que no dejan huecos en la agenda marcados para mostrarse primero.
 *
 * @property-read array{days: list<string>|null, date: string|null, slots: Collection<int, array{time: string, doctor_id: int, recommended: bool, waste: int}>, suggested: Collection<int, array{time: string, doctor_id: int, recommended: bool, waste: int}>} $resource
 */
class SlotPickerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $date = $this->resource['date'];
        $suggested = $this->resource['suggested']->pluck('time')->all();

        return [
            'days' => $this->when($this->resource['days'] !== null, fn () => array_map(self::day(...), $this->resource['days'])),
            'date' => $date,
            'day' => $date ? Dates::day($date) : null,
            'slots' => $this->resource['slots']->map(fn (array $slot) => [
                'time' => $slot['time'],
                'label' => Dates::time($slot['time']),
                'doctor_id' => $slot['doctor_id'],
                // Los demás quedan detrás de "Ver más horarios".
                'suggested' => in_array($slot['time'], $suggested, true),
            ])->values(),
        ];
    }

    /**
     * Un día de la tira: "Hoy", "Mañana" o "Lun", su número y su mes.
     *
     * @return array{date: string, weekday: string, number: int, month: string, label: string}
     */
    private static function day(string $date): array
    {
        $day = CarbonImmutable::parse($date);

        return [
            'date' => $date,
            'weekday' => match (true) {
                $day->isToday() => 'Hoy',
                $day->isTomorrow() => 'Mañana',
                default => ucfirst($day->translatedFormat('D')),
            },
            'number' => $day->day,
            'month' => $day->translatedFormat('M'),
            'label' => Dates::day($day),
        ];
    }
}
