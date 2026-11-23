<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\RefreshesWithToday;
use App\Filament\Pages\Agenda;
use App\Filament\Resources\WaitlistEntries\WaitlistEntryResource;
use App\Models\User;
use App\Services\Dashboard\WeekGaps as WeekGapsService;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

/**
 * Los próximos 7 días en una fila, empezando hoy: qué tan llena está la agenda de cada uno y cuántas horas
 * quedan libres. Al tocar un día, debajo, sus huecos por doctor (para ofrecer a quien llama, a la lista de
 * espera o a un plan sin agendar), quiénes de la lista de espera pueden venir con cada uno y el enlace a su
 * agenda. Abre en el primer día que todavía tiene huecos. Arriba, cuántas horas libres suman entre todos.
 */
class WeekGaps extends Widget
{
    use RefreshesWithToday;

    /** Mientras carga, un recuadro del mismo alto: la página no salta. */
    protected ?string $placeholderHeight = '30rem';

    protected string $view = 'filament.widgets.week-gaps';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->managesAllAgendas();
    }

    /**
     * @return array{summary: string|null, selected: int, days: list<array{label: string, short: string, number: string, occupancy: int, free: string, free_short: array{main: string, minutes: string|null}, url: string, doctors: list<array{name: string|null, gaps: string, waiting: string|null, waiting_url: string}>}>}
     */
    protected function getViewData(): array
    {
        $days = app(WeekGapsService::class)->days();
        $severalDoctors = collect($days)->flatMap(fn (array $day) => array_column($day['doctors'], 'doctor'))->unique('id')->count() > 1;
        $free = array_sum(array_column($days, 'free_minutes'));

        return [
            'summary' => match (true) {
                $days === [] => null,
                $free > 0 => 'Quedan '.Dates::duration($free).' libres de hoy a 7 días. Toca un día para ver sus huecos.',
                default => 'No queda ningún hueco de hoy a 7 días.',
            },
            // Abre en el primer día que todavía tiene huecos: es el que se ofrece primero.
            'selected' => max(0, (int) collect($days)->search(fn (array $day) => $day['free_minutes'] > 0)),
            'days' => array_map(fn (array $day) => [
                'label' => match (true) {
                    $day['date']->isToday() => 'Hoy',
                    $day['date']->isTomorrow() => 'Mañana',
                    default => ucfirst($day['date']->translatedFormat('l j')),
                },
                'short' => $day['date']->isToday() ? 'Hoy' : ucfirst(mb_substr($day['date']->translatedFormat('D'), 0, 3)),
                'number' => $day['date']->format('j'),
                'occupancy' => $day['occupancy'],
                'free' => $day['free_minutes'] > 0 ? Dates::duration($day['free_minutes']).' libres' : 'Sin huecos',
                'free_short' => self::shortDuration($day['free_minutes']),
                'url' => Agenda::getUrl(['date' => $day['date']->toDateString(), 'mode' => 'day']),
                'doctors' => array_values(array_filter(array_map(fn (array $row) => $row['gaps'] === [] ? null : [
                    'name' => $severalDoctors ? $row['doctor']->name : null,
                    'gaps' => collect($row['gaps'])->map(fn (array $gap) => self::range($gap['start'], $gap['end']))->implode(' · '),
                    'waiting' => self::waiting($row['waiting'], $severalDoctors ? $row['doctor']->name : null),
                    'waiting_url' => WaitlistEntryResource::getUrl('index', ['filters' => ['fits' => ['date' => $day['date']->toDateString(), 'doctor_id' => $row['doctor']->getKey()]]]),
                ], $day['doctors']))),
            ], $days),
        ];
    }

    /** "2 de la lista de espera pueden venir", y "con Dr. Vega" cuando hay más de un doctor. */
    private static function waiting(int $count, ?string $doctor): ?string
    {
        if ($count === 0) {
            return null;
        }

        return ($count === 1 ? '1 de la lista de espera puede venir' : $count.' de la lista de espera pueden venir').($doctor ? ' con '.$doctor : '');
    }

    /**
     * Lo libre de un día en la fila de arriba, donde hay poco espacio: "3 h", "2 h 50", "40 min" o "Lleno". Los minutos
     * de "2 h 50" van aparte: en un día angosto "13 h 20" no entra con la letra de 12 px y se ven solo las horas
     * (panel.css, .dash-week-min); el detalle del día y lo que lee el lector de pantalla los dicen siempre.
     *
     * @return array{main: string, minutes: string|null}
     */
    private static function shortDuration(int $minutes): array
    {
        return match (true) {
            $minutes <= 0 => ['main' => 'Lleno', 'minutes' => null],
            $minutes < 60 => ['main' => $minutes.' min', 'minutes' => null],
            default => ['main' => intdiv($minutes, 60).' h', 'minutes' => $minutes % 60 > 0 ? (string) ($minutes % 60) : null],
        };
    }

    private static function range(CarbonImmutable $start, CarbonImmutable $end): string
    {
        return $start->format('H:i').'–'.$end->format('H:i');
    }
}
