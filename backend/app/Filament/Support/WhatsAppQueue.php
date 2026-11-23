<?php

namespace App\Filament\Support;

use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Enviar en fila": los avisos de una pestaña de Avisos ("Por enviar" o "Promociones"), uno tras otro y en el orden
 * de la lista. No entran los que alguien abrió en WhatsApp sin decir si se enviaron: podrían salir dos veces.
 */
final class WhatsAppQueue
{
    /** Las pestañas que se pueden enviar en fila. */
    public const TABS = ['due', 'promotions'];

    /**
     * El siguiente que toca enviar, sin los que ya pasaron por esta fila.
     *
     * @param  list<int>  $except
     */
    public static function next(string $tab, array $except = []): ?WhatsappMessage
    {
        return self::soonestFirst(self::query($tab, $except))->first();
    }

    /**
     * Cuántos quedan por enviar en la fila, sin los que ya pasaron por ella.
     *
     * @param  list<int>  $except
     */
    public static function remaining(string $tab, array $except = []): int
    {
        return self::query($tab, $except)->count();
    }

    /** Primero lo de la cita más próxima; lo que no es de una cita, por cuándo se preparó. Como la lista. */
    public static function soonestFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw('coalesce((select a.starts_at from appointments a where a.id = whatsapp_messages.appointment_id), whatsapp_messages.created_at)')
            ->orderBy('id');
    }

    /** @param  list<int>  $except */
    private static function query(string $tab, array $except): Builder
    {
        return WhatsappMessageResource::getEloquentQuery()
            ->when($tab === 'promotions', fn (Builder $query) => $query->promotions(), fn (Builder $query) => $query->due())
            ->whereNull('opened_at')
            ->whereKeyNot($except);
    }
}
