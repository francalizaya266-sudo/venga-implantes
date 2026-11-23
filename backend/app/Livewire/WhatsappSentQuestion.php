<?php

namespace App\Livewire;

use App\Enums\MessageStatus;
use App\Filament\Actions\SendWhatsAppAction;
use App\Filament\Pages\Dashboard;
use App\Filament\Support\WhatsAppQueue;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppOutbox;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "¿Se envió?": después de abrir un aviso en WhatsApp desde el panel, al volver se pregunta si se envió, en vez de
 * darlo por enviado. Va en todas las pantallas y no dibuja nada más: la pregunta la pide la pantalla que abrió
 * WhatsApp. Si nadie responde, el aviso sigue en "Por enviar", marcado como abierto.
 *
 * En fila ("Enviar en fila" de Avisos), "Sí" abre el siguiente en el mismo toque, hasta terminar la fila.
 */
class WhatsappSentQuestion extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** Lo avisa la pantalla que acaba de abrir WhatsApp. */
    public const OPENED = 'whatsapp-opened';

    /** El aviso por el que se pregunta. */
    #[Locked]
    public ?int $messageId = null;

    /** El enlace de WhatsApp, para abrirlo de nuevo si no se abrió. */
    #[Locked]
    public ?string $url = null;

    /** Se armó al abrirlo (una receta, un saludo): no estaba en "Por enviar" y, si no se envió, no queda ahí. */
    #[Locked]
    public bool $composed = false;

    /** La pantalla que abrió WhatsApp (la lista, la ficha, Hoy): con la respuesta se vuelve a pintar. */
    #[Locked]
    public ?string $openedFrom = null;

    /** En fila: la pestaña de Avisos que se está enviando. */
    #[Locked]
    public ?string $queue = null;

    /** En fila: el que sigue después de uno que no se envió, esperando su toque. */
    #[Locked]
    public ?int $nextId = null;

    /** @var list<int> En fila: los que no se enviaron, para no ofrecerlos de nuevo. */
    #[Locked]
    public array $notSent = [];

    /** En fila: cuántos se enviaron. */
    #[Locked]
    public int $sent = 0;

    #[On(self::OPENED)]
    public function opened(int $message, string $url, ?string $queue = null, bool $composed = false, ?string $from = null): void
    {
        if ($this->ownMessage($message) === null) {
            return;
        }

        // Cada vez que se abre WhatsApp desde una pantalla empieza de cero: un aviso suelto o una fila nueva.
        $this->fill([
            'messageId' => $message,
            'url' => str_starts_with($url, 'https://wa.me/') ? $url : null,
            'composed' => $composed,
            'openedFrom' => $from,
            'queue' => in_array($queue, WhatsAppQueue::TABS, true) ? $queue : null,
            'nextId' => null,
            'notSent' => [],
            'sent' => 0,
        ]);

        $this->replaceMountedAction('askSent');
    }

    public function askSentAction(): Action
    {
        return Action::make('askSent')
            ->modalIcon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->modalIconColor('success')
            ->modalWidth(Width::Large)
            ->modalHeading(fn (): string => $this->heading())
            ->modalDescription(fn (): string => $this->description())
            ->modalSubmitActionLabel(fn (): string => $this->submitLabel())
            // El toque que abre el siguiente abre también su pestaña: si no, el navegador la bloquea.
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->color('success')
                ->extraAttributes($this->submitOpensNext() ? SendWhatsAppAction::OPENS_TAB_ON_CLICK : []))
            ->extraModalFooterActions(fn (Action $action): array => $this->otherAnswers($action))
            ->modalCancelAction(false)
            ->closeModalByClickingAway(false)
            ->action(fn (Action $action, array $arguments) => $this->answer($action, $arguments));
    }

    public function render(): View
    {
        return view('livewire.whatsapp-sent-question');
    }

    private function heading(): string
    {
        if ($next = $this->upNext()) {
            return 'Sigue el mensaje a '.$this->recipient($next);
        }

        $message = $this->asked();

        return $message ? '¿Se envió el mensaje a '.$this->recipient($message).'?' : '¿Se envió el mensaje?';
    }

    private function description(): string
    {
        if ($next = $this->upNext()) {
            return $next->template_key->getLabel().'. El anterior quedó en Por enviar.'.$this->remainingText($next);
        }

        $message = $this->asked();

        return trim(($message ? $message->template_key->getLabel().'.' : '')
            .($message && $this->queue ? $this->remainingText($message) : '')
            .($this->url ? ' Si WhatsApp no se abrió, toca «Abrir de nuevo».' : ''));
    }

    /** " Quedan 4 en la fila después de este." */
    private function remainingText(WhatsappMessage $current): string
    {
        $remaining = WhatsAppQueue::remaining((string) $this->queue, [...$this->notSent, $current->getKey()]);

        return match ($remaining) {
            0 => ' Es el último de la fila.',
            1 => ' Queda 1 en la fila después de este.',
            default => " Quedan {$remaining} en la fila después de este.",
        };
    }

    private function submitLabel(): string
    {
        return match (true) {
            $this->nextId !== null => 'Abrir en WhatsApp',
            $this->hasNextInQueue() => 'Sí, abrir el siguiente',
            default => 'Sí, se envió',
        };
    }

    private function submitOpensNext(): bool
    {
        return $this->nextId !== null || $this->hasNextInQueue();
    }

    /** @return list<Action> */
    private function otherAnswers(Action $action): array
    {
        if ($this->nextId !== null) {
            return [$action->makeModalSubmitAction('stop', ['stop' => true])->label('Terminar')];
        }

        return array_values(array_filter([
            $this->hasNextInQueue() ? $action->makeModalSubmitAction('sentAndStop', ['answer' => 'yes', 'stop' => true])->label('Sí, y terminar') : null,
            $action->makeModalSubmitAction('notSent', ['answer' => 'no'])->label('No se envió'),
            $this->url ? Action::make('openAgain')->label('Abrir de nuevo')->link()->url($this->url, shouldOpenInNewTab: true) : null,
        ]));
    }

    /** @param  array<string, mixed>  $arguments */
    private function answer(Action $action, array $arguments): void
    {
        $stop = (bool) ($arguments['stop'] ?? false);

        // Después de uno que no se envió: abrir el siguiente con este toque, o terminar.
        if ($this->nextId !== null) {
            $this->continueQueue($action, $stop ? null : WhatsAppQueue::next((string) $this->queue, $this->notSent), opensTab: true);

            return;
        }

        $message = $this->asked();

        if ($message === null) {
            SendWhatsAppAction::closeTab($this);

            return;
        }

        $sent = ($arguments['answer'] ?? 'yes') === 'yes';
        $outbox = app(WhatsAppOutbox::class);

        if ($sent) {
            $outbox->markSent($message, auth()->user());
            $this->sent++;
        } elseif ($message->status === MessageStatus::Pending && $this->composed) {
            // Se vuelve a armar desde donde salió: si quedara en "Por enviar", podría salir dos veces.
            $message->update(['status' => MessageStatus::Cancelled]);
        } elseif ($message->status === MessageStatus::Pending) {
            $outbox->markNotSent($message);
            $this->notSent[] = $message->getKey();
        }

        // La pantalla que lo abrió, la lista de Avisos y las tarjetas de Hoy lo muestran al momento.
        $this->dispatch(Dashboard::CHANGED);

        if ($this->openedFrom !== null) {
            $this->js('Livewire.find('.json_encode($this->openedFrom).')?.$refresh()');
        }

        if ($this->queue === null) {
            $this->notifyAnswer($message, $sent);
            $this->forget();

            return;
        }

        $this->continueQueue($action, $stop ? null : WhatsAppQueue::next($this->queue, $this->notSent), opensTab: $sent);
    }

    /**
     * Sigue la fila: abre el siguiente si el toque ya abrió su pestaña; si no (el anterior no se envió), lo deja
     * esperando su toque. Sin siguiente, termina.
     */
    private function continueQueue(Action $action, ?WhatsappMessage $next, bool $opensTab): void
    {
        if ($next === null) {
            SendWhatsAppAction::closeTab($this);
            $this->finishQueue();

            return;
        }

        if ($opensTab) {
            $this->url = app(WhatsAppOutbox::class)->clickToChatUrl($next, auth()->user(), markSent: false);
            SendWhatsAppAction::goToTab($this->url, $this);
            $this->messageId = $next->getKey();
            $this->nextId = null;
        } else {
            $this->fill(['messageId' => null, 'url' => null, 'nextId' => $next->getKey()]);
        }

        // La ventana sigue abierta, ahora con el siguiente.
        $action->halt();
    }

    private function finishQueue(): void
    {
        $left = count($this->notSent);

        Notification::make()
            ->success()
            ->title('Fila terminada')
            ->body(trim(match ($this->sent) {
                0 => 'No se envió ninguno.',
                1 => 'Se envió 1 aviso.',
                default => "Se enviaron {$this->sent} avisos.",
            }.match ($left) {
                0 => '',
                1 => ' 1 sigue en Por enviar.',
                default => " {$left} siguen en Por enviar.",
            }))
            ->send();

        $this->forget();
    }

    private function notifyAnswer(WhatsappMessage $message, bool $sent): void
    {
        $to = $this->recipient($message);

        match (true) {
            $sent => Notification::make()->success()->title("Aviso para {$to} enviado")->send(),
            $message->status === MessageStatus::Sent => Notification::make()->title('Queda como estaba')->body('Ya se había enviado antes.')->send(),
            $this->composed => Notification::make()->warning()->title("Aviso para {$to} sin enviar")->body('No quedó en Avisos. Para enviarlo, ábrelo de nuevo desde aquí.')->send(),
            default => Notification::make()->warning()->title("Aviso para {$to} sin enviar")->body('Quedó en Avisos › Por enviar.')->send(),
        };
    }

    /** Ya se respondió: la próxima vez que se abra WhatsApp empieza de cero. */
    private function forget(): void
    {
        $this->reset(['messageId', 'url', 'composed', 'openedFrom', 'queue', 'nextId', 'notSent', 'sent']);
    }

    /** El aviso por el que se pregunta, si sigue siendo de quien lo abrió. */
    private function asked(): ?WhatsappMessage
    {
        return $this->messageId ? $this->ownMessage($this->messageId) : null;
    }

    /** En fila, el que sigue después de uno que no se envió. */
    private function upNext(): ?WhatsappMessage
    {
        return $this->nextId ? WhatsappMessage::query()->with('patient')->find($this->nextId) : null;
    }

    private function hasNextInQueue(): bool
    {
        return $this->queue !== null
            && $this->messageId !== null
            && WhatsAppQueue::next($this->queue, [...$this->notSent, $this->messageId]) !== null;
    }

    /** Solo responde quien lo abrió, y solo de un aviso que sigue por enviar (o que se volvió a enviar). */
    private function ownMessage(int $id): ?WhatsappMessage
    {
        return WhatsappMessage::query()
            ->with('patient')
            ->whereKey($id)
            ->where('opened_by_user_id', auth()->id())
            ->whereIn('status', [MessageStatus::Pending, MessageStatus::Sent])
            ->first();
    }

    private function recipient(WhatsappMessage $message): string
    {
        return $message->patient?->full_name ?? Phone::display($message->phone);
    }
}
