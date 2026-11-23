<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\PushOutcome;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PushSubscription;
use App\Models\WhatsappMessage;
use App\Services\Notices\PushSender;
use App\Services\WhatsApp\WhatsAppOutbox;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/**
 * Los avisos de citas y cuotas salen también en la pantalla del celular de quien los activó. Quien eligió solo el
 * celular no los recibe por WhatsApp, salvo que no lleguen a mostrarse ahí.
 */
class SendPhoneNoticesTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Account $account;

    private Appointment $appointment;

    /** @var list<array{endpoint: string, notice: array<string, string>}> */
    private array $shown = [];

    private PushOutcome $outcome = PushOutcome::Delivered;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        config(['consultorio.push.public_key' => 'clave-publica', 'consultorio.push.private_key' => 'clave-privada']);
        $this->account = Account::factory()->create();
        $patient = Patient::factory()->for($this->account)->create(['first_name' => 'Rosa']);
        $doctor = Doctor::factory()->create(['name' => 'Dra. Ana Pérez']);
        $this->appointment = Appointment::factory()->for($patient)->for($doctor)->create(['starts_at' => now()->addDay()->setTime(15, 0)]);

        $this->app->instance(PushSender::class, new class($this) extends PushSender
        {
            public function __construct(private readonly SendPhoneNoticesTest $test) {}

            public function send(PushSubscription $subscription, array $notice): PushOutcome
            {
                return $this->test->pushed($subscription, $notice);
            }
        });
    }

    /** @param  array<string, string>  $notice */
    public function pushed(PushSubscription $subscription, array $notice): PushOutcome
    {
        $this->shown[] = ['endpoint' => $subscription->endpoint, 'notice' => $notice];

        return $this->outcome;
    }

    private function reminder(): WhatsappMessage
    {
        return app(WhatsAppOutbox::class)->queueForAppointment($this->appointment, MessageTemplateKey::Reminder);
    }

    public function test_the_reminder_shows_on_the_phone_without_the_treatment_and_still_goes_by_whatsapp(): void
    {
        $phone = PushSubscription::factory()->for($this->account)->create();
        $message = $this->reminder();

        $this->artisan('avisos:enviar-al-celular')->expectsOutput('Avisos mostrados en el celular: 1')->assertSuccessful();

        $this->assertSame([[
            'endpoint' => $phone->endpoint,
            'notice' => [
                'title' => 'Recordatorio de cita',
                'body' => "Rosa: mañana a las 3:00\u{00A0}p.\u{00A0}m. con la Dra. Ana Pérez. Confírmala en Mi cuenta.",
                'url' => '/mi-cuenta#proximas',
                'tag' => 'cita-'.$this->appointment->id,
            ],
        ]], $this->shown);
        $this->assertStringNotContainsStringIgnoringCase($this->appointment->treatment->name, $this->shown[0]['notice']['body']);
        $this->assertNotNull($message->fresh()->pushed_at);
        // Quien tiene los dos, lo recibe también por WhatsApp.
        $this->assertSame(MessageStatus::Pending, $message->fresh()->status);
        $this->assertTrue(WhatsappMessage::query()->due()->whereKey($message->id)->exists());

        $this->artisan('avisos:enviar-al-celular')->expectsOutput('Avisos mostrados en el celular: 0');
        $this->assertCount(1, $this->shown);
    }

    public function test_whoever_chose_only_the_phone_gets_it_there_and_not_by_whatsapp(): void
    {
        PushSubscription::factory()->for($this->account)->create();
        $this->account->update(['whatsapp_notices' => false]);
        $message = $this->reminder();

        // Mientras espera a mostrarse en el celular, no está en "Por enviar".
        $this->assertFalse(WhatsappMessage::query()->due()->whereKey($message->id)->exists());

        $this->artisan('avisos:enviar-al-celular')->assertSuccessful();

        $message->refresh();
        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertSame(MessageChannel::WebPush, $message->channel);
        $this->assertNotNull($message->sent_at);
    }

    public function test_a_notice_that_could_not_show_on_the_phone_goes_back_to_whatsapp_after_half_an_hour(): void
    {
        PushSubscription::factory()->for($this->account)->create();
        $this->account->update(['whatsapp_notices' => false]);
        $this->outcome = PushOutcome::Failed;
        $message = $this->reminder();

        $this->artisan('avisos:enviar-al-celular')->expectsOutput('Avisos mostrados en el celular: 0');
        $this->assertFalse(WhatsappMessage::query()->due()->whereKey($message->id)->exists());

        $this->travel(WhatsappMessage::PHONE_GRACE_MINUTES + 1)->minutes();

        $this->assertTrue(WhatsappMessage::query()->due()->whereKey($message->id)->exists());
    }

    public function test_a_phone_whose_browser_no_longer_accepts_notices_is_forgotten_and_whatsapp_takes_over(): void
    {
        PushSubscription::factory()->for($this->account)->create();
        $this->account->update(['whatsapp_notices' => false]);
        $this->outcome = PushOutcome::Gone;
        $message = $this->reminder();

        $this->artisan('avisos:enviar-al-celular')->assertSuccessful();

        $this->assertDatabaseCount('push_subscriptions', 0);
        $this->assertSame(MessageStatus::Pending, $message->fresh()->status);
        $this->assertTrue(WhatsappMessage::query()->due()->whereKey($message->id)->exists());
    }

    public function test_only_what_is_due_for_a_coming_appointment_and_for_a_phone_that_is_still_remembered_is_shown(): void
    {
        $phone = PushSubscription::factory()->for($this->account)->create();
        $outbox = app(WhatsAppOutbox::class);
        // El de otra familia sin celular con avisos, uno que sale mañana y el de una cita que ya pasó.
        $outbox->queueForAppointment(Appointment::factory()->create(), MessageTemplateKey::Reminder);
        $outbox->queueForAppointment($this->appointment, MessageTemplateKey::Reminder, now()->addHours(3));
        $past = Appointment::factory()->for($this->appointment->patient)->create(['starts_at' => now()->subHour(), 'status' => AppointmentStatus::Confirmed]);
        $outbox->queueForAppointment($past, MessageTemplateKey::AppointmentScheduled);
        // Lo que no es de una cita ni de una cuota sigue solo por WhatsApp.
        $outbox->queueForAccount($this->account, MessageTemplateKey::Birthday, ['nombre' => 'Rosa']);

        $this->artisan('avisos:enviar-al-celular')->expectsOutput('Avisos mostrados en el celular: 0');

        $this->travel(3)->hours();
        $phone->device->update(['revoked_at' => now()]);
        $this->artisan('avisos:enviar-al-celular')->expectsOutput('Avisos mostrados en el celular: 0');
        $this->assertSame([], $this->shown);
    }

    public function test_each_notice_says_what_happened_in_a_few_words(): void
    {
        PushSubscription::factory()->for($this->account)->create();
        $outbox = app(WhatsAppOutbox::class);
        $outbox->queueForAppointment($this->appointment, MessageTemplateKey::Cancelled);
        $outbox->queueForAccount($this->account, MessageTemplateKey::InstallmentOverdue, ['nombre' => 'Rosa', 'cuota' => 'cuota 2 de 6', 'fecha' => 'lunes 26 de octubre', 'monto' => 'S/ 250.00']);

        $this->artisan('avisos:enviar-al-celular')->expectsOutput('Avisos mostrados en el celular: 2');

        $this->assertSame(['Cita cancelada', "Rosa: se canceló la cita de mañana martes 3 de noviembre a las 3:00\u{00A0}p.\u{00A0}m. Escríbenos y te ayudamos a elegir otro horario."], [$this->shown[0]['notice']['title'], $this->shown[0]['notice']['body']]);
        $this->assertSame(['Cuota vencida', 'Rosa: la cuota 2 de 6 venció el lunes 26 de octubre. El monto está en Mi cuenta.', '/mi-cuenta#planes'], [$this->shown[1]['notice']['title'], $this->shown[1]['notice']['body'], $this->shown[1]['notice']['url']]);
    }

    public function test_without_the_clinic_keys_nothing_is_shown(): void
    {
        config(['consultorio.push.private_key' => null]);
        PushSubscription::factory()->for($this->account)->create();
        $this->reminder();

        $this->artisan('avisos:enviar-al-celular')
            ->expectsOutput('Los avisos en el celular no están activados: faltan las claves (php artisan avisos:crear-claves).')
            ->assertSuccessful();
        $this->assertSame([], $this->shown);
    }
}
