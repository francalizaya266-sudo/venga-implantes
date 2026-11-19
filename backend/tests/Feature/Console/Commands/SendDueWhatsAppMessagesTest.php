<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Models\Appointment;
use App\Services\WhatsApp\WhatsAppOutbox;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

class SendDueWhatsAppMessagesTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    public function test_does_nothing_while_the_official_api_is_not_active(): void
    {
        Http::preventStrayRequests();
        $this->freezeMonday();
        $message = app(WhatsAppOutbox::class)->queueForAppointment(Appointment::factory()->create(), MessageTemplateKey::Reminder);

        $this->artisan('whatsapp:enviar')->assertSuccessful();

        $this->assertSame(MessageStatus::Pending, $message->fresh()->status);
    }

    public function test_sends_due_messages_through_the_cloud_api_when_active(): void
    {
        Http::preventStrayRequests();
        Http::fake(['graph.facebook.com/v21.0/123/messages' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        config([
            'consultorio.whatsapp.driver' => 'cloud_api',
            'consultorio.whatsapp.cloud_api.token' => 'token',
            'consultorio.whatsapp.cloud_api.phone_number_id' => '123',
        ]);
        $this->freezeMonday();
        $message = app(WhatsAppOutbox::class)->queueForAppointment(Appointment::factory()->create(), MessageTemplateKey::Reminder);

        $this->artisan('whatsapp:enviar')->assertSuccessful();

        $this->assertSame(MessageStatus::Sent, $message->fresh()->status);
        $this->assertSame('wamid.1', $message->fresh()->provider_message_id);
        Http::assertSent(fn ($request) => $request['to'] === ltrim($message->phone, '+') && $request['type'] === 'text');
    }

    public function test_a_message_meta_does_not_answer_is_left_failed_with_the_reason_and_the_next_one_is_still_sent(): void
    {
        Http::preventStrayRequests();
        Http::fakeSequence('graph.facebook.com/v21.0/123/messages')
            ->pushFailedConnection('Connection timed out')
            ->push(['messages' => [['id' => 'wamid.2']]]);
        config([
            'consultorio.whatsapp.driver' => 'cloud_api',
            'consultorio.whatsapp.cloud_api.token' => 'token',
            'consultorio.whatsapp.cloud_api.phone_number_id' => '123',
        ]);
        $this->freezeMonday();
        $outbox = app(WhatsAppOutbox::class);
        $first = $outbox->queueForAppointment(Appointment::factory()->create(), MessageTemplateKey::Reminder);
        $second = $outbox->queueForAppointment(Appointment::factory()->create(), MessageTemplateKey::Reminder);

        $this->artisan('whatsapp:enviar')->assertSuccessful();

        $this->assertSame(MessageStatus::Failed, $first->fresh()->status);
        $this->assertSame('Connection timed out', $first->fresh()->error);
        $this->assertSame(MessageStatus::Sent, $second->fresh()->status);
        $this->assertSame('wamid.2', $second->fresh()->provider_message_id);
    }
}
