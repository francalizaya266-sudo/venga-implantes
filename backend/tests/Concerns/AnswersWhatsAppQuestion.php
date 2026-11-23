<?php

namespace Tests\Concerns;

use App\Livewire\WhatsappSentQuestion;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** Después de abrir WhatsApp desde el panel, al volver, quien lo abrió responde "¿Se envió?". */
trait AnswersWhatsAppQuestion
{
    /**
     * La pantalla acaba de abrir WhatsApp: se responde la pregunta que dejó ("Sí, se envió" o "No se envió") y la
     * pantalla se vuelve a pintar, como en el navegador.
     */
    protected function answerWhatsApp(Testable $screen, bool $sent = true): Testable
    {
        $opened = collect($screen->effects['dispatches'] ?? [])->firstWhere('name', WhatsappSentQuestion::OPENED);
        $this->assertNotNull($opened, 'Al abrir WhatsApp queda la pregunta "¿Se envió?".');

        Livewire::test(WhatsappSentQuestion::class)
            ->dispatch(WhatsappSentQuestion::OPENED, ...$opened['params'])
            ->callMountedAction($sent ? [] : ['answer' => 'no'])
            ->assertJs('Livewire.find('.json_encode($screen->id()).')?.$refresh()');

        return $screen->call('$refresh');
    }
}
