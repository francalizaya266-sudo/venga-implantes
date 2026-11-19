<?php

namespace App\Filament\Support;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Consultations\ConsultationResource;
use App\Filament\Resources\LabOrders\LabOrderResource;
use App\Filament\Resources\RescheduleRequests\RescheduleRequestResource;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\CashClosing;
use Carbon\CarbonImmutable;

/**
 * El número junto a cada opción principal del menú: lo que espera ahí, sumando lo de sus opciones de adentro. Esas
 * solo se ven al entrar a la opción, así que sin esto un cambio de horario pedido o un trabajo de laboratorio
 * quedaban escondidos. Al pasar el mouse, el detalle de qué cuenta.
 */
final class MenuPending
{
    /**
     * Agenda: las citas de hoy y mañana que faltan confirmar (las mismas de "Por confirmar" en Hoy) y los cambios
     * de horario que pidieron los pacientes.
     *
     * @return list<array{count: int, label: string}>
     */
    public static function agenda(): array
    {
        return self::remember(__FUNCTION__, fn () => self::pieces([
            [
                AppointmentResource::canViewAny() ? AppointmentResource::getEloquentQuery()->awaitingConfirmation()->todayOrTomorrow()->count() : 0,
                'cita por confirmar (hoy o mañana)',
                'citas por confirmar (hoy y mañana)',
            ],
            [
                RescheduleRequestResource::canViewAny() ? (int) RescheduleRequestResource::getNavigationBadge() : 0,
                'cambio de horario pedido',
                'cambios de horario pedidos',
            ],
        ]));
    }

    /**
     * Pacientes: las atenciones propias que quedaron sin terminar y los trabajos de laboratorio que piden algo.
     *
     * @return list<array{count: int, label: string}>
     */
    public static function patients(): array
    {
        return self::remember(__FUNCTION__, fn () => self::pieces([
            [
                ConsultationResource::canViewAny() ? (int) ConsultationResource::getNavigationBadge() : 0,
                'atención sin terminar',
                'atenciones sin terminar',
            ],
            [
                LabOrderResource::canViewAny() ? (int) LabOrderResource::getNavigationBadge() : 0,
                'trabajo de laboratorio por revisar',
                'trabajos de laboratorio por revisar',
            ],
        ]));
    }

    /**
     * Caja: a quiénes falta cobrarles algo de lo de hoy, con la misma cuenta que "Falta cobrar" en Hoy y que el
     * cierre del día.
     *
     * @return list<array{count: int, label: string}>
     */
    public static function cash(): array
    {
        /** @var User $user */
        $user = auth()->user();

        return self::remember(__FUNCTION__, fn () => self::pieces([
            [
                $user->can('viewAny', Payment::class) ? (new CashClosing(CarbonImmutable::today()))->pending()->count() : 0,
                'paciente por cobrar (de hoy)',
                'pacientes por cobrar (de hoy)',
            ],
        ]));
    }

    /**
     * El número y su detalle se piden por separado en el mismo pedido: se cuenta una sola vez por persona.
     *
     * @param  callable(): list<array{count: int, label: string}>  $count
     * @return list<array{count: int, label: string}>
     */
    private static function remember(string $option, callable $count): array
    {
        $key = 'menu-pending.'.$option.'.'.auth()->id();

        if (! request()->attributes->has($key)) {
            request()->attributes->set($key, $count());
        }

        return request()->attributes->get($key);
    }

    /** @param  list<array{count: int, label: string}>  $pieces */
    public static function badge(array $pieces): ?string
    {
        $total = array_sum(array_column($pieces, 'count'));

        return $total > 0 ? (string) $total : null;
    }

    /**
     * "2 citas por confirmar (hoy y mañana) · 1 cambio de horario pedido"
     *
     * @param  list<array{count: int, label: string}>  $pieces
     */
    public static function tooltip(array $pieces): ?string
    {
        return $pieces === [] ? null : collect($pieces)->map(fn (array $piece) => $piece['count'].' '.$piece['label'])->implode(' · ');
    }

    /**
     * Solo lo que tiene algo, cada uno con su número y su nombre en singular o plural.
     *
     * @param  list<array{0: int, 1: string, 2: string}>  $counts  [cuántos, en singular, en plural]
     * @return list<array{count: int, label: string}>
     */
    private static function pieces(array $counts): array
    {
        return collect($counts)
            ->filter(fn (array $count) => $count[0] > 0)
            ->map(fn (array $count) => ['count' => $count[0], 'label' => $count[0] === 1 ? $count[1] : $count[2]])
            ->values()
            ->all();
    }
}
