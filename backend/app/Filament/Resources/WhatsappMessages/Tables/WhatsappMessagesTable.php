<?php

namespace App\Filament\Resources\WhatsappMessages\Tables;

use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Filament\Actions\SendWhatsAppAction;
use App\Filament\Resources\Appointments\Tables\AppointmentsTable;
use App\Filament\Support\WhatsAppQueue;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppOutbox;
use App\Support\Phone;
use Carbon\CarbonInterface;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class WhatsappMessagesTable
{
    /** Pestañas de lo que falta enviar: van por la cita más próxima, no por el último preparado. */
    private const TO_SEND = ['due', 'scheduled', 'promotions'];

    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            // La búsqueda del paciente separa las palabras ella misma: así "987 654 321" encuentra el celular.
            ->splitSearchTerms(false)
            ->columns([
                // Cuándo se preparó va debajo del nombre del aviso: así el botón de enviar entra sin correr la tabla.
                TextColumn::make('template_key')
                    ->label('Aviso')
                    ->badge()
                    ->color('gray')
                    // Abierto en WhatsApp sin que nadie dijera si se envió: se pregunta aquí, para no mandarlo dos veces.
                    ->description(function (WhatsappMessage $record): string {
                        if (! WhatsAppOutbox::isOpenedUnconfirmed($record)) {
                            return 'Preparado '.$record->created_at->diffForHumans();
                        }

                        /** @var CarbonInterface $openedAt */
                        $openedAt = $record->opened_at;

                        return 'Se abrió en WhatsApp '.$openedAt->diffForHumans().': ¿se envió?';
                    }),
                // El mensaje a la vista, para saber qué se manda sin abrirlo.
                TextColumn::make('preview')
                    ->label('Mensaje')
                    ->state(fn (WhatsappMessage $record) => Str::limit(Str::squish(str_replace('{enlace}', '(su enlace)', app(WhatsAppOutbox::class)->draft($record))), 140))
                    ->color('gray')
                    ->size('sm')
                    ->wrap()
                    ->lineClamp(3)
                    // Sin un ancho mínimo, la tabla la aprieta hasta dejar una palabra por línea.
                    ->extraAttributes(['style' => 'min-width: 13rem'])
                    ->extraCellAttributes(['class' => 'wa-preview']),
                TextColumn::make('phone')
                    ->label('Para')
                    ->formatStateUsing(fn (WhatsappMessage $record) => $record->patient?->full_name ?? Phone::display($record->phone))
                    ->description(fn (WhatsappMessage $record) => $record->patient ? Phone::display($record->phone) : null)
                    // Con dos doctores y nombres largos, el botón de enviar quedaba cortado en una laptop: el nombre
                    // del paciente y el del doctor bajan de línea; el teléfono y la fecha no se parten.
                    ->extraCellAttributes(['class' => 'wraps-name'])
                    // Por el celular solo con 4 números o más: sin números, "%%" traía todos los avisos.
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $w) => $w
                        ->whereHas('patient', fn (Builder $p) => $p->search($search))
                        ->when(strlen($digits = (string) preg_replace('/\D/', '', $search)) >= 4, fn (Builder $phone) => $phone->orWhere('phone', 'like', '%'.$digits.'%')))),
                TextColumn::make('appointment.starts_at')
                    ->label('Cita')
                    ->dateTime('D d/m H:i')
                    ->description(fn (WhatsappMessage $record) => AppointmentsTable::showsDoctor(auth()->user()) ? $record->appointment?->doctor->name : null)
                    ->extraCellAttributes(['class' => 'wraps-description'])
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    // En "Por enviar" y "Promociones" todos dicen lo mismo, y la columna sacaba el botón de la pantalla.
                    ->hidden(fn ($livewire) => in_array($livewire->activeTab ?? 'due', ['due', 'promotions'], true))
                    ->description(fn (WhatsappMessage $record) => match (true) {
                        $record->status === MessageStatus::Sent => trim(self::sender($record).' · '.$record->sent_at?->format('d/m H:i')),
                        $record->status === MessageStatus::Pending && $record->due_at?->isFuture() => 'Sale el '.$record->due_at->translatedFormat('d/m \a \l\a\s H:i'),
                        $record->status === MessageStatus::Failed => $record->error,
                        default => null,
                    }),
            ])
            // Por enviar: primero lo de la cita más próxima (lo que no es de una cita, por cuándo se preparó).
            // En Enviados y Todos, el último arriba.
            ->defaultSort(fn (Builder $query, $livewire) => in_array($livewire->activeTab ?? null, self::TO_SEND, true)
                ? WhatsAppQueue::soonestFirst($query)
                : $query->orderByDesc('created_at')->orderByDesc('id'))
            ->filters([
                SelectFilter::make('template_key')
                    ->label('Aviso')
                    ->options(MessageTemplateKey::class),
            ])
            ->recordActions([
                SendWhatsAppAction::make()
                    ->label(fn (WhatsappMessage $record) => $record->status === MessageStatus::Sent ? 'Reenviar' : 'Enviar por WhatsApp')
                    ->button()
                    ->visible(fn (WhatsappMessage $record) => $record->status !== MessageStatus::Cancelled)
                    ->authorize('update'),
                SendWhatsAppAction::confirmSent()
                    ->authorize('update'),
                SendWhatsAppAction::notSent()
                    ->authorize('update'),
                SendWhatsAppAction::discard()
                    ->iconButton()
                    ->tooltip('Descartar')
                    ->authorize('update'),
            ])
            ->poll('30s')
            ->emptyStateHeading(fn ($livewire) => match ($livewire->activeTab ?? null) {
                'sent' => 'Todavía no se envió ningún aviso',
                'scheduled' => 'No hay avisos programados',
                'all' => 'Todavía no hay avisos',
                default => 'No hay avisos por enviar',
            })
            ->emptyStateDescription('Cuando se reserve, mueva o cancele una cita, el aviso aparecerá aquí.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right');
    }

    /** Quién lo envió: una persona, el envío automático o, si el paciente los pidió solo ahí, su celular. */
    private static function sender(WhatsappMessage $record): string
    {
        if ($record->wasShownOnPhone()) {
            return 'En su celular';
        }

        return $record->sentBy?->name ?? 'Automático';
    }
}
