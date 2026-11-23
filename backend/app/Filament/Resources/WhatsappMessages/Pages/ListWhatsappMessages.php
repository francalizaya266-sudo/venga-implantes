<?php

namespace App\Filament\Resources\WhatsappMessages\Pages;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Filament\Actions\SendWhatsAppAction;
use App\Filament\Concerns\HasNoBreadcrumbs;
use App\Filament\Concerns\RefreshesWithToday;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Filament\Support\WhatsAppQueue;
use App\Services\WhatsApp\WhatsAppOutbox;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListWhatsappMessages extends ListRecords
{
    use HasNoBreadcrumbs;

    // Al responder "¿Se envió?", la lista se vuelve a pintar al momento.
    use RefreshesWithToday;

    protected static string $resource = WhatsappMessageResource::class;

    protected static ?string $title = 'Avisos';

    public function getSubheading(): ?string
    {
        if (app(WhatsAppOutbox::class)->channel() !== MessageChannel::ClickToChat) {
            return 'Presiona "Enviar por WhatsApp": se abre el chat del paciente con el mensaje listo y solo tienes que tocar Enviar.';
        }

        // Solo nombra «Enviar en fila» cuando el botón está: sin nada en la fila, no se ve.
        return $this->hasQueue()
            ? '«Enviar en fila» abre el chat de cada paciente con el mensaje listo. Tocas Enviar en WhatsApp y, al volver, dices si se envió.'
            : 'Presiona "Enviar por WhatsApp": se abre el chat del paciente con el mensaje listo. Tocas Enviar y, al volver, dices si se envió.';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Uno tras otro, en el orden de la lista: al volver de WhatsApp, "Sí" abre el siguiente.
            Action::make('sendInQueue')
                ->label('Enviar en fila')
                ->icon(Heroicon::OutlinedQueueList)
                ->color('success')
                // El mismo toque abre la pestaña de WhatsApp: si no, el navegador la bloquea.
                ->extraAttributes(SendWhatsAppAction::OPENS_TAB_ON_CLICK)
                ->visible(fn (): bool => $this->hasQueue())
                ->action(function (): void {
                    $first = WhatsAppQueue::next($this->queueTab());

                    if ($first === null) {
                        SendWhatsAppAction::closeTab($this);
                        Notification::make()->title('No hay avisos por enviar')->send();

                        return;
                    }

                    SendWhatsAppAction::openAndAsk($first, $this, queue: $this->queueTab());
                }),
        ];
    }

    /** En "Por enviar" y "Promociones", cuando se envían desde el panel (con la API de WhatsApp salen solos). */
    private function sendsInQueue(): bool
    {
        return app(WhatsAppOutbox::class)->channel() === MessageChannel::ClickToChat
            && in_array($this->activeTab ?? 'due', WhatsAppQueue::TABS, true);
    }

    /** Hay algo para «Enviar en fila» en esta pestaña. */
    private function hasQueue(): bool
    {
        return $this->sendsInQueue() && WhatsAppQueue::next($this->queueTab()) !== null;
    }

    private function queueTab(): string
    {
        return $this->activeTab === 'promotions' ? 'promotions' : 'due';
    }

    public function getTabs(): array
    {
        return array_filter([
            'due' => Tab::make('Por enviar')
                ->badge(fn () => WhatsappMessageResource::getEloquentQuery()->due()->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->due()),
            // Avisos con fecha de salida. Aparece cuando hay alguno.
            'scheduled' => WhatsappMessageResource::getEloquentQuery()->pending()->where('due_at', '>', now())->exists()
                ? Tab::make('Programados')
                    ->modifyQueryUsing(fn (Builder $query) => $query->pending()->where('due_at', '>', now()))
                : null,
            // Las promociones, aparte de lo de las citas. Aparece cuando hay alguna por enviar.
            'promotions' => WhatsappMessageResource::getEloquentQuery()->promotions()->exists()
                ? Tab::make('Promociones')
                    ->badge(fn () => WhatsappMessageResource::getEloquentQuery()->promotions()->count() ?: null)
                    ->modifyQueryUsing(fn (Builder $query) => $query->promotions())
                : null,
            'sent' => Tab::make('Enviados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', MessageStatus::Sent)),
            // Solo con la API de WhatsApp: los que no salieron. Aparece cuando hay alguno.
            'failed' => WhatsappMessageResource::getEloquentQuery()->where('status', MessageStatus::Failed)->exists()
                ? Tab::make('No enviados')
                    ->badge(fn () => WhatsappMessageResource::getEloquentQuery()->failedRecently()->count() ?: null)
                    ->badgeColor('danger')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('status', MessageStatus::Failed))
                : null,
            'all' => Tab::make('Todos'),
        ]);
    }
}
