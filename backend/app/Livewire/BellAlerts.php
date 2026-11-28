<?php

namespace App\Livewire;

use App\Services\StaffAlerts;
use Filament\Livewire\DatabaseNotifications;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Livewire\Attributes\Locked;

/**
 * La campana del panel muestra solo lo pendiente, para que no se junte. Lo que solo informa (en gris, sin botón) queda
 * visto al abrirla y se va al cerrarla; lo que pide algo se queda hasta que se hace: con su botón, o solo cuando ya se
 * hizo desde otro lado (StaffAlerts::closeDone). Antes lo leído seguía en la lista, solo en otro color, y lo nuevo se
 * perdía entre lo de siempre.
 */
class BellAlerts extends DatabaseNotifications
{
    /** El panel lateral de la campana (el de Filament). */
    public const MODAL_ID = 'database-notifications';

    /** Los que se muestran por página, como en Filament. */
    private const PER_PAGE = 50;

    /** @var list<string> Lo que solo informa y se vio con la campana abierta: sigue a la vista hasta cerrarla. */
    #[Locked]
    public array $seenWhileOpen = [];

    /**
     * Se abrió la campana: lo que solo informa (sin botón: "Dr. Rojas agendó una cita", "Un paciente canceló su cita")
     * queda visto.
     */
    public function opened(): void
    {
        /** @var list<string> $seen */
        $seen = $this->getUnreadNotificationsQuery()
            ->whereJsonLength('data->actions', 0)
            ->limit(self::PER_PAGE)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        if ($seen === []) {
            return;
        }

        $this->getNotificationsQuery()->whereKey($seen)->update(['read_at' => now()]);
        $this->seenWhileOpen = array_values(array_unique([...$this->seenWhileOpen, ...$seen]));
    }

    /** Se cerró: lo visto se va. */
    public function closed(): void
    {
        $this->seenWhileOpen = [];
    }

    public function getNotifications(): DatabaseNotificationCollection|Paginator
    {
        app(StaffAlerts::class)->closeDone();

        $pending = $this->getNotificationsQuery()
            ->where(fn (Builder $query) => $query->whereNull('read_at')->orWhereIn('id', $this->seenWhileOpen));

        return $this->isPaginated()
            ? $pending->simplePaginate(self::PER_PAGE, pageName: 'database-notifications-page')
            : $pending->get();
    }

    public function render(): View
    {
        return view('livewire.bell-alerts');
    }
}
