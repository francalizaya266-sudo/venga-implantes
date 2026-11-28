<?php

namespace Tests\Feature\Services\WhatsApp;

use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Models\Appointment;
use App\Models\MagicLink;
use App\Models\MessageTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppOutbox;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

class WhatsAppOutboxTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    public function test_click_to_chat_opens_the_clinic_whatsapp_with_the_message_written(): void
    {
        $this->freezeMonday();
        $reception = User::factory()->reception()->create();
        $appointment = Appointment::factory()->at('2026-11-04 10:30')->create();
        $appointment->patient->account->update(['phone' => '+51987654321']);
        $appointment->patient->update(['first_name' => 'María']);
        $outbox = app(WhatsAppOutbox::class);
        $message = $outbox->queueForAppointment($appointment, MessageTemplateKey::BookingConfirmation);

        $url = $outbox->clickToChatUrl($message, $reception);

        $this->assertStringStartsWith('https://wa.me/51987654321?text=', $url);
        $text = rawurldecode(substr($url, strpos($url, '=') + 1));
        $this->assertStringContainsString('Hola María', $text);
        $this->assertStringContainsString("miércoles 4 de noviembre a las 10:30\u{00A0}a.\u{00A0}m.", $text);
        $this->assertMatchesRegularExpression('#/c/[A-Za-z0-9]{32}#', $text, 'Lleva el enlace personal.');
        $this->assertSame(MessageStatus::Sent, $message->fresh()->status);
        $this->assertTrue($message->fresh()->sentBy->is($reception));
    }

    public function test_the_booking_confirmation_says_the_price_only_if_the_treatment_shows_it(): void
    {
        $this->freezeMonday();
        $appointment = Appointment::factory()->at('2026-11-04 10:30')->create();
        $appointment->treatment->update(['price_from' => 150]);
        $outbox = app(WhatsAppOutbox::class);

        $withPrice = $outbox->render($outbox->queueForAppointment($appointment, MessageTemplateKey::BookingConfirmation));
        $this->assertStringContainsString(".\nPrecio: desde S/ 150. Pagas en el consultorio con Yape, Plin, tarjeta o efectivo.\n\nConfirma", $withPrice);

        $appointment->treatment->update(['show_price' => false]);
        $withoutPrice = $outbox->render($outbox->queueForAppointment($appointment->fresh(), MessageTemplateKey::BookingConfirmation));
        $this->assertStringNotContainsString('Precio', $withoutPrice);
        $this->assertStringContainsString("10:30\u{00A0}a.\u{00A0}m. con {$appointment->doctor->name}.\nTe esperamos en Jr. Apurímac 648, Juliaca.\n\nConfirma tu asistencia", $withoutPrice);
    }

    public function test_the_notices_of_an_appointment_say_where_the_clinic_is_once_the_address_is_set(): void
    {
        $this->freezeMonday();
        $appointment = Appointment::factory()->at('2026-11-03 16:00')->create();
        $appointment->patient->update(['first_name' => 'Jorge']);
        $appointment->treatment->update(['name' => 'Limpieza dental', 'show_price' => false, 'before_instructions' => 'Ven con los dientes cepillados.']);
        $doctor = $appointment->doctor->name;
        $outbox = app(WhatsAppOutbox::class);
        Setting::set('clinic.address', '');
        // Preparado antes de llenar la dirección: al enviarlo ya la lleva, porque sale de Ajustes en ese momento.
        $reminder = $outbox->queueForAppointment($appointment, MessageTemplateKey::Reminder);

        $this->assertStringContainsString("Hola Jorge, te recordamos tu cita de limpieza dental de mañana martes 3 de noviembre a las 4:00\u{00A0}p.\u{00A0}m. con {$doctor} en ".Setting::get('clinic.name').".\n\nAntes de venir: Ven con los dientes cepillados.\n\nConfirma tu asistencia aquí:", $outbox->draft($reminder));

        Setting::set('clinic.address', 'Av. Larco 345');
        Setting::set('clinic.district', 'Miraflores');

        $this->assertStringContainsString(".\nTe esperamos en Av. Larco 345, Miraflores.\nAntes de venir: Ven con los dientes cepillados.\n\nConfirma", $outbox->draft($reminder));
        foreach ([MessageTemplateKey::BookingConfirmation, MessageTemplateKey::FirstWebBooking, MessageTemplateKey::AppointmentScheduled] as $key) {
            $this->assertStringContainsString("con {$doctor}.\nTe esperamos en Av. Larco 345, Miraflores.\n\n", $outbox->draft($outbox->queueForAppointment($appointment, $key)), $key->value);
        }
    }

    public function test_every_notice_explains_each_piece_of_data_its_text_uses(): void
    {
        foreach (MessageTemplateKey::cases() as $key) {
            preg_match_all('/\{\w+\}/', $key->defaultBody(), $used);

            $this->assertSame([], array_diff($used[0], array_keys($key->placeholders())), $key->value);
            $this->assertNotContains('', $key->placeholders(), $key->value);
        }

        // Un aviso de cita acepta los demás datos de la cita; uno de cuota explica su fecha como la de la cuota.
        $this->assertArrayHasKey('{indicaciones}', MessageTemplateKey::Rescheduled->placeholders());
        $this->assertArrayNotHasKey('{indicaciones}', MessageTemplateKey::Birthday->placeholders());
        $this->assertSame('el día en que vence la cuota', MessageTemplateKey::InstallmentReminder->placeholders()['{fecha}']);
    }

    public function test_uses_the_text_edited_by_the_clinic(): void
    {
        $this->freezeMonday();
        MessageTemplate::create(['key' => MessageTemplateKey::Reminder, 'name' => 'Recordatorio', 'body' => 'Mañana a las {hora}, {nombre}.']);
        $appointment = Appointment::factory()->at('2026-11-03 16:00')->create();
        $appointment->patient->update(['first_name' => 'Jorge']);
        $outbox = app(WhatsAppOutbox::class);

        $body = $outbox->render($outbox->queueForAppointment($appointment, MessageTemplateKey::Reminder));

        $this->assertSame("Mañana a las 4:00\u{00A0}p.\u{00A0}m., Jorge.", $body);
        $this->assertSame(0, MagicLink::count(), 'Sin {enlace} no se genera enlace.');
    }

    public function test_keeps_only_the_latest_pending_notice_of_each_kind(): void
    {
        $this->freezeMonday();
        $appointment = Appointment::factory()->create();
        $outbox = app(WhatsAppOutbox::class);

        $first = $outbox->queueForAppointment($appointment, MessageTemplateKey::Rescheduled);
        $outbox->queueForAppointment($appointment, MessageTemplateKey::Rescheduled);

        $this->assertSame(MessageStatus::Cancelled, $first->fresh()->status);
        $this->assertSame(1, $appointment->messages()->pending()->count());
    }

    public function test_skips_patients_without_a_phone(): void
    {
        $this->freezeMonday();
        $appointment = Appointment::factory()->create();
        $appointment->patient->update(['account_id' => null]);

        $message = app(WhatsAppOutbox::class)->queueForAppointment($appointment->fresh(), MessageTemplateKey::Reminder);

        $this->assertNull($message);
    }
}
