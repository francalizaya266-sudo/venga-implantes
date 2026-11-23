<?php

namespace Tests\Feature\Filament;

use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Filament\Resources\Appointments\Pages\ViewAppointment;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\WhatsappMessages\Pages\ListWhatsappMessages;
use App\Livewire\WhatsappSentQuestion;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Access\PatientAccess;
use App\Services\Booking\AppointmentService;
use App\Services\WhatsApp\WhatsAppOutbox;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\AnswersWhatsAppQuestion;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/**
 * Enviar avisos desde el panel: al abrir WhatsApp no se dan por enviados; al volver se pregunta "¿Se envió?". En
 * fila, "Sí" abre el siguiente en el mismo toque.
 */
class WhatsAppSentQuestionTest extends TestCase
{
    use AnswersWhatsAppQuestion, CreatesAgenda, LazilyRefreshDatabase;

    private const URL = 'https://wa.me/51987654321?text=Hola';

    private Treatment $treatment;

    private Doctor $doctor;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->treatment = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $this->doctor = $this->doctorOffering($this->treatment);
        $this->reception = User::factory()->reception()->create();
        $this->actingAs($this->reception);
    }

    /** El aviso de la cita de ese paciente, todavía por enviar. */
    private function notice(string $firstName, string $startsAt, MessageTemplateKey $key = MessageTemplateKey::BookingConfirmation): WhatsappMessage
    {
        $patient = Patient::factory()->create(['first_name' => $firstName, 'last_name' => 'Prueba']);
        $appointment = Appointment::factory()->for($patient)->for($this->doctor)->for($this->treatment)->at($startsAt)->create();

        return app(WhatsAppOutbox::class)->queueForAppointment($appointment, $key);
    }

    public function test_opening_a_notice_asks_on_return_instead_of_marking_it_sent(): void
    {
        $message = $this->notice('Rosa', '2026-11-03 10:00');

        Livewire::test(ListWhatsappMessages::class)
            ->mountAction(TestAction::make('sendWhatsApp')->table($message))
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertDispatched(WhatsappSentQuestion::OPENED, message: $message->id, queue: null);

        // Abrirlo no es enviarlo: sigue en "Por enviar" hasta que quien lo abrió diga que sí.
        $message->refresh();
        $this->assertSame(MessageStatus::Pending, $message->status);
        $this->assertTrue($message->openedBy->is($this->reception));
        $this->assertNull($message->sent_at);

        Livewire::test(WhatsappSentQuestion::class)
            ->dispatch(WhatsappSentQuestion::OPENED, message: $message->id, url: self::URL)
            ->assertActionMounted('askSent')
            ->assertMountedActionModalSee(['¿Se envió el mensaje a Rosa Prueba?', 'Confirmación de reserva web.', 'Sí, se envió', 'No se envió', 'Abrir de nuevo'])
            ->callMountedAction()
            ->assertNotified('Aviso para Rosa Prueba enviado')
            ->assertActionNotMounted('askSent');

        $message->refresh();
        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertTrue($message->sentBy->is($this->reception));
    }

    public function test_saying_it_was_not_sent_leaves_it_to_send(): void
    {
        $message = $this->notice('Rosa', '2026-11-03 10:00');
        app(WhatsAppOutbox::class)->clickToChatUrl($message, $this->reception, markSent: false);

        Livewire::test(WhatsappSentQuestion::class)
            ->dispatch(WhatsappSentQuestion::OPENED, message: $message->id, url: self::URL)
            ->callMountedAction(['answer' => 'no'])
            ->assertNotified('Aviso para Rosa Prueba sin enviar');

        // Como si no se hubiera abierto: entra de nuevo en la fila.
        $message->refresh();
        $this->assertSame(MessageStatus::Pending, $message->status);
        $this->assertNull($message->opened_at);
    }

    public function test_a_message_written_when_opening_it_that_was_not_sent_does_not_stay_to_send(): void
    {
        $patient = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Prueba']);
        $appointment = Appointment::factory()->for($patient)->for($this->doctor)->for($this->treatment)->at('2026-11-05 11:00')->create();

        $page = Livewire::test(ViewAppointment::class, ['record' => $appointment->getKey()])
            ->mountAction('whatsapp')
            ->callMountedAction()
            ->assertDispatched(WhatsappSentQuestion::OPENED, composed: true);

        // No estaba en "Por enviar": se vuelve a armar desde la cita y en Avisos no queda uno que podría salir dos veces.
        $this->answerWhatsApp($page, sent: false);
        Notification::assertNotified('Aviso para Rosa Prueba sin enviar');
        $this->assertSame(MessageStatus::Cancelled, WhatsappMessage::sole()->status);
    }

    public function test_the_my_account_link_asked_for_on_the_website_is_the_one_that_opens(): void
    {
        $account = Account::factory()->create(['phone' => '+51987654321']);
        $rosa = Patient::factory()->for($account)->create(['first_name' => 'Rosa', 'last_name' => 'Prueba']);
        app(PatientAccess::class)->requestAccess('+51987654321');
        $asked = WhatsappMessage::query()->pending()->sole();

        $page = Livewire::test(ViewPatient::class, ['record' => $rosa->getRouteKey()])
            ->callAction(TestAction::make('sendAccessLink')->schemaComponent('profile.family', 'infolist'))
            ->assertDispatched(WhatsappSentQuestion::OPENED, message: $asked->id, composed: false);

        // Abrirlo no lo atiende: si al final no se envió, el pedido sigue en "Por enviar".
        $this->answerWhatsApp($page, sent: false);
        $this->assertSame([MessageStatus::Pending, 1], [$asked->fresh()->status, WhatsappMessage::count()]);

        $this->answerWhatsApp($page->callAction(TestAction::make('sendAccessLink')->schemaComponent('profile.family', 'infolist')));
        $this->assertSame([MessageStatus::Sent, 1], [$asked->fresh()->status, WhatsappMessage::count()]);
    }

    public function test_only_who_opened_it_is_asked(): void
    {
        $message = $this->notice('Rosa', '2026-11-03 10:00');
        app(WhatsAppOutbox::class)->clickToChatUrl($message, User::factory()->reception()->create(), markSent: false);

        Livewire::test(WhatsappSentQuestion::class)
            ->dispatch(WhatsappSentQuestion::OPENED, message: $message->id, url: self::URL)
            ->assertActionNotMounted('askSent');

        $this->assertSame(MessageStatus::Pending, $message->fresh()->status);
    }

    public function test_an_opened_notice_nobody_answered_asks_in_the_list(): void
    {
        $message = $this->notice('Rosa', '2026-11-03 10:00');
        app(WhatsAppOutbox::class)->clickToChatUrl($message, $this->reception, markSent: false);

        Livewire::test(ListWhatsappMessages::class)
            ->assertCanSeeTableRecords([$message])
            ->assertSee('Se abrió en WhatsApp hace 0 segundos: ¿se envió?')
            ->assertActionVisible(TestAction::make('confirmSent')->table($message))
            ->callAction(TestAction::make('confirmSent')->table($message))
            ->assertNotified('Pasó a "Enviados"');

        $this->assertSame(MessageStatus::Sent, $message->fresh()->status);
    }

    public function test_sending_in_queue_opens_one_after_another_with_one_tap(): void
    {
        $third = $this->notice('Carla', '2026-11-05 10:00');
        $first = $this->notice('Ana', '2026-11-03 09:00');
        $second = $this->notice('Bruno', '2026-11-04 09:00', MessageTemplateKey::AppointmentScheduled);
        // Lo abrió alguien y no dijo si se envió: no entra en la fila, podría salir dos veces.
        $opened = $this->notice('Diego', '2026-11-03 08:30');
        app(WhatsAppOutbox::class)->clickToChatUrl($opened, $this->reception, markSent: false);

        // El botón abre el de la cita más próxima, como va la lista, y su pestaña se abre en el mismo toque.
        Livewire::test(ListWhatsappMessages::class)
            ->assertSee('«Enviar en fila» abre el chat de cada paciente con el mensaje listo.')
            ->assertSeeHtml('window.waTab = window.open')
            ->callAction('sendInQueue')
            ->assertDispatched(WhatsappSentQuestion::OPENED, message: $first->id, queue: 'due');
        $this->assertNotNull($first->fresh()->opened_at);

        $question = Livewire::test(WhatsappSentQuestion::class)
            ->dispatch(WhatsappSentQuestion::OPENED, message: $first->id, url: self::URL, queue: 'due')
            ->assertMountedActionModalSee(['¿Se envió el mensaje a Ana Prueba?', 'Quedan 2 en la fila después de este.', 'Sí, abrir el siguiente', 'Sí, y terminar', 'No se envió'])
            ->assertMountedActionModalSeeHtml('window.waTab = window.open');

        // "Sí": se envió y, en el mismo toque, se abre el siguiente.
        $question->callMountedAction()
            ->assertActionMounted('askSent')
            ->assertMountedActionModalSee(['¿Se envió el mensaje a Bruno Prueba?', 'Cita programada por el consultorio.', 'Queda 1 en la fila después de este.']);
        $this->assertSame(MessageStatus::Sent, $first->fresh()->status);
        $this->assertTrue($second->fresh()->openedBy->is($this->reception));

        // "No se envió": queda por enviar y el siguiente espera su toque.
        $question->callMountedAction(['answer' => 'no'])
            ->assertActionMounted('askSent')
            ->assertMountedActionModalSee(['Sigue el mensaje a Carla Prueba', 'El anterior quedó en Por enviar.', 'Abrir en WhatsApp', 'Terminar']);
        $this->assertSame([MessageStatus::Pending, null], [$second->fresh()->status, $second->fresh()->opened_at]);
        $this->assertNull($third->fresh()->opened_at);

        // El último ya no abre otra pestaña.
        $question->callMountedAction()
            ->assertMountedActionModalSee(['¿Se envió el mensaje a Carla Prueba?', 'Es el último de la fila.', 'Sí, se envió'])
            ->assertMountedActionModalDontSeeHtml('window.waTab = window.open');
        $this->assertNotNull($third->fresh()->opened_at);

        $question->callMountedAction()
            ->assertNotified('Fila terminada')
            ->assertActionNotMounted('askSent');

        $this->assertSame(
            [MessageStatus::Sent, MessageStatus::Pending, MessageStatus::Sent, MessageStatus::Pending],
            [$first->fresh()->status, $second->fresh()->status, $third->fresh()->status, $opened->fresh()->status],
        );
    }

    public function test_yes_and_stop_ends_the_queue_without_opening_the_next(): void
    {
        $first = $this->notice('Ana', '2026-11-03 09:00');
        $second = $this->notice('Bruno', '2026-11-04 09:00');
        app(WhatsAppOutbox::class)->clickToChatUrl($first, $this->reception, markSent: false);

        Livewire::test(WhatsappSentQuestion::class)
            ->dispatch(WhatsappSentQuestion::OPENED, message: $first->id, url: self::URL, queue: 'due')
            ->callMountedAction(['answer' => 'yes', 'stop' => true])
            ->assertNotified('Fila terminada')
            ->assertActionNotMounted('askSent');

        $this->assertSame(MessageStatus::Sent, $first->fresh()->status);
        $this->assertNull($second->fresh()->opened_at);
    }

    public function test_without_anything_to_send_in_queue_the_page_does_not_offer_it(): void
    {
        $message = $this->notice('Rosa', '2026-11-03 10:00');
        app(WhatsAppOutbox::class)->clickToChatUrl($message, $this->reception, markSent: false);

        // Lo único por enviar ya se abrió y espera respuesta: no hay fila, y el texto de arriba no la nombra.
        Livewire::test(ListWhatsappMessages::class)
            ->assertActionHidden('sendInQueue')
            ->assertDontSee('«Enviar en fila»')
            ->assertSee('Tocas Enviar y, al volver, dices si se envió.');
    }

    public function test_a_notice_that_changed_while_in_whatsapp_says_so_instead_of_closing_in_silence(): void
    {
        $message = $this->notice('Rosa', '2026-11-03 10:00');
        $next = $this->notice('Bruno', '2026-11-04 09:00');
        app(WhatsAppOutbox::class)->clickToChatUrl($message, $this->reception, markSent: false);

        $question = Livewire::test(WhatsappSentQuestion::class)
            ->dispatch(WhatsappSentQuestion::OPENED, message: $message->id, url: self::URL, queue: 'due');

        // Mientras estaba en WhatsApp, otra persona movió la cita sin acordarlo: ese aviso se retiró y va el del cambio.
        app(AppointmentService::class)->rescheduleByStaff($message->appointment, CarbonImmutable::parse('2026-11-05 16:00'), User::factory()->reception()->create());

        $question->callMountedAction()
            ->assertNotified('Ese aviso cambió mientras tanto')
            ->assertActionNotMounted('askSent');

        // No se da por enviado ni abre el siguiente por su cuenta: la fila sigue desde «Enviar en fila».
        $this->assertSame(MessageStatus::Cancelled, $message->fresh()->status);
        $this->assertNull($next->fresh()->opened_at);
    }

    public function test_with_the_whatsapp_api_opening_it_still_marks_it_sent_and_there_is_no_queue(): void
    {
        config(['consultorio.whatsapp.driver' => 'cloud_api']);
        $message = $this->notice('Rosa', '2026-11-03 10:00');

        Livewire::test(ListWhatsappMessages::class)
            ->assertActionHidden('sendInQueue')
            ->mountAction(TestAction::make('sendWhatsApp')->table($message))
            ->callMountedAction()
            ->assertNotDispatched(WhatsappSentQuestion::OPENED)
            ->assertNotified('Aviso para Rosa Prueba abierto en WhatsApp');

        $this->assertSame(MessageStatus::Sent, $message->fresh()->status);
    }
}
