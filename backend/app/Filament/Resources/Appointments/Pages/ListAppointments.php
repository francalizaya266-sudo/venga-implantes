<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Enums\AppointmentStatus;
use App\Filament\Actions\CreateAppointmentAction;
use App\Filament\Concerns\HasNoBreadcrumbs;
use App\Filament\Concerns\RefreshesWithToday;
use App\Filament\Resources\Appointments\AppointmentResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;

/**
 * Citas: para buscar una cita de cualquier fecha, confirmar las que faltan y cerrar las que quedaron abiertas.
 * El día a día (hoy, mañana) se ve en la Agenda.
 */
class ListAppointments extends ListRecords
{
    use HasNoBreadcrumbs, RefreshesWithToday;

    protected static string $resource = AppointmentResource::class;

    /** @var array{total: int, toCall: int}|null Las citas por confirmar, contadas una vez por pedido. */
    private ?array $awaitingConfirmation = null;

    /** @var array{total: int, earlier: int}|null Las citas sin cerrar (y cuántas son de días anteriores), contadas una vez por pedido. */
    private ?array $unclosed = null;

    /** "Nueva cita" es la misma ventana de la agenda, sin salir de la lista. */
    protected function getHeaderActions(): array
    {
        return [
            CreateAppointmentAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            // Cada cita pendiente o confirmada está en un solo lugar: aquí mientras no pasa su hora, después en "Sin cerrar".
            // Quien llegó hoy sigue aquí (en sala o en atención) hasta que se cierra: no "desaparece" al tocar "Llegó".
            'upcoming' => Tab::make('Próximas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn (Builder $coming) => $coming
                    ->where(fn (Builder $notYet) => $notYet->whereIn('status', AppointmentStatus::changeable())->where('ends_at', '>=', now()))
                    ->orWhere(fn (Builder $here) => $here->whereIn('status', AppointmentStatus::inClinic())->where('starts_at', '>=', today())))),
            // El número es el de la lista. Se pinta de alerta cuando alguna ya toca llamar (está a un día de su cita).
            'pending' => Tab::make('Por confirmar')
                ->badge(fn () => $this->awaitingConfirmation()['total'] ?: null)
                ->badgeColor(fn () => $this->awaitingConfirmation()['toCall'] > 0 ? 'warning' : 'gray')
                ->badgeTooltip(fn () => match ($toCall = $this->awaitingConfirmation()['toCall']) {
                    0 => 'Citas que todavía no confirman',
                    1 => '1 dice "Llamar": está a un día de su cita',
                    default => $toCall.' dicen "Llamar": están a un día de su cita',
                })
                ->modifyQueryUsing(fn (Builder $query) => $query->awaitingConfirmation()),
            'unclosed' => Tab::make('Sin cerrar')
                ->badgeColor('warning')
                ->badge(fn () => $this->unclosed()['total'] ?: null)
                ->badgeTooltip('Ya pasó su hora: marca si vino o si no asistió')
                ->modifyQueryUsing(fn (Builder $query) => $query->unclosed()),
            'all' => Tab::make('Todas'),
        ];
    }

    /**
     * Las pestañas, el aviso de las citas olvidadas y la lista. El aviso sale solo si quedaron sin cerrar citas de días
     * anteriores: las de hoy todavía se cierran en el día, y un aviso que está siempre deja de leerse.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                Callout::make(fn () => match ($earlier = $this->unclosed()['earlier']) {
                    1 => 'Quedó 1 cita de días anteriores sin cerrar',
                    default => "Quedaron {$earlier} citas de días anteriores sin cerrar",
                })
                    ->description('Marca si el paciente vino o no: mientras siga abierta no se cobra lo atendido, no se descuentan los materiales y las inasistencias del reporte salen mal.')
                    ->warning()
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->actions([
                        Action::make('reviewUnclosed')
                            ->label('Revisar ahora')
                            ->link()
                            ->url(fn () => AppointmentResource::getUrl('index', ['tab' => 'unclosed'])),
                    ])
                    ->visible(fn () => $this->activeTab !== 'unclosed' && $this->unclosed()['earlier'] > 0),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    /** Un enlace guardado a una pestaña que ya no existe ("Hoy", "Mañana") abre la primera. */
    protected function loadDefaultActiveTab(): void
    {
        if (! array_key_exists((string) $this->activeTab, $this->getCachedTabs())) {
            $this->activeTab = $this->getDefaultActiveTab();
        }
    }

    /** @return array{total: int, earlier: int} */
    private function unclosed(): array
    {
        if ($this->unclosed === null) {
            $counts = AppointmentResource::getEloquentQuery()
                ->unclosed()
                ->toBase()
                ->selectRaw('count(*) as total, count(case when starts_at < ? then 1 end) as earlier', [today()->toDateTimeString()])
                ->first();

            $this->unclosed = ['total' => (int) $counts->total, 'earlier' => (int) $counts->earlier];
        }

        return $this->unclosed;
    }

    /** @return array{total: int, toCall: int} */
    private function awaitingConfirmation(): array
    {
        if ($this->awaitingConfirmation === null) {
            $counts = AppointmentResource::getEloquentQuery()
                ->awaitingConfirmation()
                ->toBase()
                ->selectRaw('count(*) as total, count(unconfirmed_flagged_at) as to_call')
                ->first();

            $this->awaitingConfirmation = ['total' => (int) $counts->total, 'toCall' => (int) $counts->to_call];
        }

        return $this->awaitingConfirmation;
    }
}
