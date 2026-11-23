<?php

namespace App\Filament\Resources\Accounts;

use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Filament\Actions\SendWhatsAppAction;
use App\Models\Account;
use App\Models\RememberedDevice;
use App\Models\WhatsappMessage;
use App\Services\Access\DeviceService;
use App\Services\WhatsApp\WhatsAppOutbox;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** Bloquear o permitir las reservas web de un celular, enviarle su enlace de Mi cuenta y cerrarla en sus celulares. */
final class AccountActions
{
    /**
     * Enlace personal para entrar a Mi cuenta desde un celular nuevo ("Pedir acceso"). Desde la ficha del
     * paciente, $account da el celular de su familia.
     *
     * @param  Closure(mixed): ?Account|null  $account
     */
    public static function sendAccessLink(?Closure $account = null): Action
    {
        $account ??= fn (Account $record) => $record;

        return SendWhatsAppAction::compose('sendAccessLink', function (Action $action) use ($account) {
            $phone = $action->evaluate($account);

            if ($phone === null) {
                return null;
            }

            // Si el paciente lo pidió desde la web, se envía ese mismo aviso: si al final no se envió, sigue en Avisos.
            return self::requestedAccessLink($phone)
                ?? app(WhatsAppOutbox::class)->draftForAccount($phone, MessageTemplateKey::AccessLink, patientId: $phone->holder?->getKey());
        })
            ->label('Enviar enlace de Mi cuenta')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->visible(fn (Action $action) => ($phone = $action->evaluate($account)) !== null && ! $phone->isBlocked())
            // Otro pedido desde la web que nadie abrió ya quedó atendido con este.
            ->after(fn (Action $action) => WhatsappMessage::query()
                ->whereBelongsTo($action->evaluate($account))
                ->where('template_key', MessageTemplateKey::AccessLink)
                ->pending()
                ->whereNull('opened_at')
                ->update(['status' => MessageStatus::Cancelled]));
    }

    /** El enlace de Mi cuenta que el paciente pidió desde la web y espera en Avisos. */
    private static function requestedAccessLink(Account $account): ?WhatsappMessage
    {
        return WhatsappMessage::query()
            ->whereBelongsTo($account)
            ->where('template_key', MessageTemplateKey::AccessLink)
            ->pending()
            ->oldest('id')
            ->first();
    }

    /**
     * Mi cuenta queda abierta en el celular que tocó un enlace del número. Si el paciente lo perdió o cambió de
     * celular, el consultorio la cierra en todos: para volver a entrar necesita un enlace nuevo.
     */
    public static function forgetDevices(): Action
    {
        return Action::make('forgetDevices')
            ->label('Cerrar Mi cuenta en sus celulares')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->visible(fn (Account $record) => RememberedDevice::query()->whereBelongsTo($record)->usable()->exists())
            ->authorize('block')
            ->requiresConfirmation()
            ->modalHeading('¿Cerrar Mi cuenta en sus celulares?')
            ->modalDescription('Úsalo si perdió o cambió su celular. Mi cuenta deja de estar abierta en los celulares donde entró, y para volver a entrar necesitará un enlace nuevo por WhatsApp.')
            ->modalSubmitActionLabel('Cerrar Mi cuenta')
            ->action(function (Account $record) {
                $closed = app(DeviceService::class)->forgetAll($record);

                Notification::make()
                    ->success()
                    ->title($closed === 1 ? 'Mi cuenta quedó cerrada en 1 celular' : "Mi cuenta quedó cerrada en {$closed} celulares")
                    ->send();
            });
    }

    public static function block(): Action
    {
        return Action::make('block')
            ->label('Bloquear')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (Account $record) => ! $record->isBlocked())
            ->authorize('block')
            ->modalHeading('Bloquear reservas web')
            ->modalDescription('Este celular no podrá reservar desde la web. El consultorio sí puede seguir agendándole citas.')
            ->modalSubmitActionLabel('Bloquear')
            ->schema([
                Textarea::make('reason')
                    ->label('Motivo')
                    ->required()
                    ->rows(2)
                    ->maxLength(250),
            ])
            ->action(function (array $data, Account $record) {
                $record->update([
                    'blocked_at' => now(),
                    'blocked_reason' => $data['reason'],
                    'blocked_by_user_id' => auth()->id(),
                ]);

                Notification::make()->success()->title('Celular bloqueado para reservas web')->send();
            });
    }

    public static function unblock(): Action
    {
        return Action::make('unblock')
            ->label('Desbloquear')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Account $record) => $record->isBlocked())
            ->authorize('block')
            ->requiresConfirmation()
            ->modalHeading('¿Permitir otra vez las reservas web?')
            ->modalSubmitActionLabel('Desbloquear')
            ->action(function (Account $record) {
                $record->update(['blocked_at' => null, 'blocked_reason' => null, 'blocked_by_user_id' => null]);

                Notification::make()->success()->title('Celular desbloqueado')->send();
            });
    }
}
