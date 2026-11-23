<?php

namespace App\Filament\Actions;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Livewire\WhatsappSentQuestion;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppOutbox;
use App\Support\Phone;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * Abre el WhatsApp del consultorio con el aviso ya escrito (con un enlace personal nuevo, creado al
 * abrirlo). Se puede retocar el texto antes y, al volver al panel, se dice si se envió.
 */
final class SendWhatsAppAction
{
    /**
     * @param  Closure(mixed): ?WhatsappMessage  $resolveMessage  Recibe el registro de la fila y devuelve el aviso.
     */
    public static function make(string $name = 'sendWhatsApp', ?Closure $resolveMessage = null): Action
    {
        $resolveMessage ??= fn (WhatsappMessage $record) => $record;

        return self::opening(Action::make($name))
            ->modalHeading(fn ($record) => self::heading($resolveMessage($record)))
            ->modalDescription(fn ($record) => self::description($resolveMessage($record)))
            ->fillForm(fn ($record) => [
                'body' => app(WhatsAppOutbox::class)->draft($resolveMessage($record)),
            ])
            ->schema([
                Textarea::make('body')
                    ->label('Mensaje')
                    ->rows(9)
                    ->required(),
            ])
            ->action(function (array $data, $record, Component $livewire) use ($resolveMessage) {
                self::open($resolveMessage($record), $data['body'], $livewire);
            });
    }

    /**
     * Mensaje que no estaba en la bandeja (receta, presupuesto): se arma al abrir, se puede
     * retocar y recién al abrir WhatsApp queda registrado (al volver, se dice si se envió).
     *
     * @param  Closure  $draft  Devuelve el aviso sin guardar, o null (después de avisar por qué) si no hay nada que enviar. Recibe $record, $livewire, etc.
     */
    public static function compose(string $name, Closure $draft): Action
    {
        return self::opening(Action::make($name))
            ->modalHeading('Enviar por WhatsApp')
            ->modalDescription(self::DESCRIPTION)
            ->fillForm(function (Action $action) use ($draft) {
                $message = $action->evaluate($draft);

                if (! $message) {
                    $action->cancel();
                }

                return ['body' => app(WhatsAppOutbox::class)->draft($message)];
            })
            ->schema([
                Textarea::make('body')
                    ->label('Mensaje')
                    ->rows(12)
                    ->required(),
            ])
            ->action(function (array $data, Action $action, Component $livewire) use ($draft) {
                /** @var WhatsappMessage|null $message */
                $message = $action->evaluate($draft);

                if (! $message) {
                    $action->halt();
                }

                $composed = ! $message->exists;
                $message->save();
                self::open($message, $data['body'], $livewire, $composed);
            });
    }

    /** Se abrió en WhatsApp y nadie dijo si se envió: aquí se dice que sí, sin abrirlo otra vez. */
    public static function confirmSent(): Action
    {
        return Action::make('confirmSent')
            ->label('Ya se envió')
            ->icon(Heroicon::OutlinedCheck)
            ->color('gray')
            ->visible(fn (WhatsappMessage $record) => WhatsAppOutbox::isOpenedUnconfirmed($record))
            ->action(function (WhatsappMessage $record) {
                app(WhatsAppOutbox::class)->markSent($record, auth()->user());
                Notification::make()->success()->title('Pasó a "Enviados"')->send();
            });
    }

    /** Se abrió WhatsApp pero el mensaje no llegó a enviarse: vuelve a "Por enviar". */
    public static function notSent(): Action
    {
        return Action::make('notSent')
            ->label('No se envió')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (WhatsappMessage $record) => WhatsAppOutbox::canUndoSend($record))
            ->action(function (WhatsappMessage $record) {
                app(WhatsAppOutbox::class)->markNotSent($record);
                Notification::make()->success()->title('Volvió a "Por enviar"')->send();
            });
    }

    private const DESCRIPTION = 'Revisa el mensaje. Se abrirá WhatsApp con el texto listo: solo presiona Enviar.';

    /**
     * El botón abre una pestaña vacía en el mismo toque (el celular no bloquea lo que abre un toque) y,
     * cuando el servidor arma el enlace, la lleva a WhatsApp.
     */
    private static function opening(Action $action): Action
    {
        return $action
            ->label('Enviar por WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('success')
            ->modalSubmitActionLabel('Abrir WhatsApp')
            ->modalSubmitAction(fn (Action $action) => $action->extraAttributes(self::OPENS_TAB_ON_CLICK));
    }

    /**
     * Para el botón que abre WhatsApp: abre una pestaña vacía en el mismo toque (el navegador no bloquea lo que abre
     * un toque). Cuando el servidor arma el enlace, goToTab() la lleva a WhatsApp. Va en ".capture": el botón trae su
     * propio "x-on:click" (vacío) y ese borraba este.
     */
    public const OPENS_TAB_ON_CLICK = ['x-on:click.capture' => "window.waTab = window.open('', '_blank')"];

    /** Lleva a WhatsApp la pestaña que abrió el toque; si el navegador no la dejó abrir, la intenta abrir ahora. */
    public static function goToTab(string $url, Component $livewire): void
    {
        $livewire->js('(() => { const url = '.json_encode($url).'; const tab = window.waTab; window.waTab = null; if (tab && ! tab.closed) { tab.location.href = url; } else { window.open(url, "_blank"); } })()');
    }

    /** Al final no se abre WhatsApp (el horario se ocupó, no tiene celular): se cierra la pestaña vacía. */
    public static function closeTab(Component $livewire): void
    {
        $livewire->js('(() => { const tab = window.waTab; window.waTab = null; if (tab && ! tab.closed) { tab.close(); } })()');
    }

    private static function open(WhatsappMessage $message, string $body, Component $livewire, bool $composed = false): void
    {
        // Desde el panel, al volver se pregunta si se envió. Con la API de WhatsApp no hace falta: queda enviado.
        if (app(WhatsAppOutbox::class)->channel() === MessageChannel::ClickToChat) {
            self::openAndAsk($message, $livewire, $body, composed: $composed);

            return;
        }

        $url = app(WhatsAppOutbox::class)->clickToChatUrl($message, auth()->user(), $body);
        $to = $message->patient?->full_name ?? Phone::display($message->phone);

        self::goToTab($url, $livewire);

        // Si el navegador no dejó abrirlo, queda el enlace a mano; y si al final no se envió, se puede deshacer.
        Notification::make()
            ->success()
            ->title("Aviso para {$to} abierto en WhatsApp")
            ->body('Si al final no se envió, búscalo en Avisos › Enviados y toca «No se envió».')
            ->actions([
                Action::make('reopen')->label('Abrir de nuevo')->url($url, shouldOpenInNewTab: true)->link(),
            ])
            ->send();
    }

    /**
     * Abre el aviso en WhatsApp sin darlo por enviado: al volver al panel, "¿Se envió?" (WhatsappSentQuestion)
     * espera la respuesta. En fila, con la pestaña de Avisos que se está enviando.
     *
     * @param  bool  $composed  Se armó al abrirlo: si no se envió, se descarta en vez de volver a "Por enviar".
     */
    public static function openAndAsk(WhatsappMessage $message, Component $livewire, ?string $body = null, ?string $queue = null, bool $composed = false): void
    {
        $url = app(WhatsAppOutbox::class)->clickToChatUrl($message, auth()->user(), $body, markSent: false);

        self::goToTab($url, $livewire);
        $livewire->dispatch(WhatsappSentQuestion::OPENED, message: $message->getKey(), url: $url, queue: $queue, composed: $composed, from: $livewire->getId());
    }

    private static function heading(WhatsappMessage $message): string
    {
        $to = $message->patient?->full_name;

        return $to ? "WhatsApp para {$to}" : 'WhatsApp a '.Phone::display($message->phone);
    }

    private static function description(WhatsappMessage $message): string
    {
        $to = $message->patient ? Phone::display($message->phone).'. ' : '';

        return $to.self::DESCRIPTION.(str_contains(app(WhatsAppOutbox::class)->draft($message), '{enlace}')
            ? ' {enlace} se cambia por su enlace personal al abrirlo.'
            : '');
    }

    /** Descartar un aviso que ya no hace falta enviar. */
    public static function discard(): Action
    {
        return Action::make('discard')
            ->label('Descartar')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('¿Descartar este aviso?')
            ->modalDescription('No se le enviará. Queda en Todos, como descartado.')
            ->modalSubmitActionLabel('Descartar aviso')
            ->modalCancelActionLabel('Volver')
            ->visible(fn (WhatsappMessage $record) => $record->status === MessageStatus::Pending)
            ->action(fn (WhatsappMessage $record) => $record->update(['status' => MessageStatus::Cancelled]));
    }
}
