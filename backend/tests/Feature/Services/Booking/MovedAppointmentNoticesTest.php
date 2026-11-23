<?php

namespace Tests\Feature\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\MagicLinkPurpose;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Filament\Pages\Agenda;
use App\Filament\Resources\Appointments\Pages\ViewAppointment;
use App\Filament\Resources\RescheduleRequests\Pages\ListRescheduleRequests;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\RememberedDevice;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Access\DeviceService;
use App\Services\Access\MagicLinkService;
use App\Services\Booking\AppointmentReminders;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\RescheduleRequestService;
use App\Services\Booking\WebBookingData;
use App\Services\WhatsApp\WhatsAppOutbox;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/**
 * Cuando una cita cambia de horario o de doctor, por la vía que sea, los avisos que todavía no se enviaron no se
 * quedan con los datos viejos: se rearman con los nuevos o, si ya va un aviso del cambio, se retiran.
 */
class MovedAppointmentNoticesTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Treatment $treatment;

    private Doctor $doctor;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->treatment = Treatment::factory()->create(['name' => 'Limpieza dental', 'duration_minutes' => 30]);
        $this->doctor = $this->doctorOffering($this->treatment);
        $this->reception = User::factory()->reception()->create();
    }

    /** Cita con su aviso todavía sin enviar (por defecto, la confirmación de la reserva web). */
    private function appointmentWithNotice(string $startsAt, MessageTemplateKey $key = MessageTemplateKey::BookingConfirmation, array $attributes = []): Appointment
    {
        $appointment = Appointment::factory()->for($this->doctor)->for($this->treatment)->at($startsAt)->create($attributes);
        app(WhatsAppOutbox::class)->queueForAppointment($appointment, $key);

        return $appointment;
    }

    /** @return list<MessageTemplateKey> */
    private function pendingNotices(Appointment $appointment): array
    {
        return $appointment->messages()->pending()->orderBy('id')->pluck('template_key')->all();
    }

    private function pendingNotice(Appointment $appointment, MessageTemplateKey $key): WhatsappMessage
    {
        return $appointment->messages()->pending()->where('template_key', $key)->sole();
    }

    /** La hora como la escribe el aviso. */
    private function hourOf(string $startsAt): string
    {
        return Dates::time($startsAt);
    }

    private function text(WhatsappMessage $message): string
    {
        return app(WhatsAppOutbox::class)->draft($message);
    }

    public function test_approving_a_requested_change_leaves_only_the_notice_with_the_new_time(): void
    {
        // Reservó en la web para el martes 3 a las 9:30 y la confirmación sigue sin enviarse (un día ocupado).
        $appointment = app(AppointmentService::class)->bookFromWeb(new WebBookingData(
            doctorId: $this->doctor->id,
            treatmentId: $this->treatment->id,
            startsAt: CarbonImmutable::parse('2026-11-03 09:30'),
            phone: '987 654 321',
            firstName: 'Carmen',
            lastName: 'Mamani',
            acceptsPrivacy: true,
        ));
        $confirmation = $this->pendingNotice($appointment, MessageTemplateKey::BookingConfirmation);
        $this->assertStringContainsString('martes 3 de noviembre', $this->text($confirmation));

        // Pide cambiarla y recepción aprueba el miércoles 4 a las 3:00 p. m. desde Cambios pedidos.
        $request = app(RescheduleRequestService::class)->requestByPatient($appointment, [
            CarbonImmutable::parse('2026-11-04 09:00'),
            CarbonImmutable::parse('2026-11-04 15:00'),
        ]);
        $this->actingAs($this->reception);

        Livewire::test(ListRescheduleRequests::class)
            ->callAction(TestAction::make('approve')->table($request), ['option_id' => $request->options[1]->id])
            ->assertHasNoFormErrors();

        // En Avisos queda un solo mensaje para ella, con el horario nuevo; la confirmación vieja ya no sale.
        $this->assertSame([MessageTemplateKey::RescheduleApproved], $this->pendingNotices($appointment));
        $this->assertStringContainsString('ahora es el miércoles 4 de noviembre', $this->text($this->pendingNotice($appointment, MessageTemplateKey::RescheduleApproved)));
        $this->assertSame(MessageStatus::Cancelled, $confirmation->fresh()->status);
    }

    public function test_offering_another_time_without_agreeing_it_replaces_the_old_confirmation(): void
    {
        $appointment = $this->appointmentWithNotice('2026-11-03 10:00');
        $request = app(RescheduleRequestService::class)->requestByPatient($appointment, [CarbonImmutable::parse('2026-11-04 10:00')]);

        app(RescheduleRequestService::class)->offerAnotherTime($request, CarbonImmutable::parse('2026-11-05 16:00'), $this->reception);

        $this->assertSame([MessageTemplateKey::Rescheduled], $this->pendingNotices($appointment));
        $this->assertStringContainsString('cambió al jueves 5 de noviembre', $this->text($this->pendingNotice($appointment, MessageTemplateKey::Rescheduled)));
    }

    public function test_offering_another_time_already_agreed_rebuilds_the_confirmation_with_the_new_time(): void
    {
        $appointment = $this->appointmentWithNotice('2026-11-03 10:00');
        $request = app(RescheduleRequestService::class)->requestByPatient($appointment, [CarbonImmutable::parse('2026-11-04 10:00')]);

        app(RescheduleRequestService::class)->offerAnotherTime($request, CarbonImmutable::parse('2026-11-05 16:00'), $this->reception, agreed: true);

        // Ya lo sabe: no se le prepara el aviso del cambio, y la confirmación que falta enviar dice el horario nuevo.
        $this->assertSame([MessageTemplateKey::BookingConfirmation], $this->pendingNotices($appointment));
        $text = $this->text($this->pendingNotice($appointment, MessageTemplateKey::BookingConfirmation));
        $this->assertStringContainsString('quedó reservada para el jueves 5 de noviembre', $text);
        $this->assertStringNotContainsString('martes 3 de noviembre', $text);
    }

    public function test_moving_it_from_its_page_replaces_the_old_confirmation_with_the_change_notice(): void
    {
        $appointment = $this->appointmentWithNotice('2026-11-03 10:00', attributes: ['status' => AppointmentStatus::Confirmed]);
        $this->actingAs($this->reception);

        Livewire::test(ViewAppointment::class, ['record' => $appointment->getKey()])
            ->callAction('move', ['doctor_id' => $this->doctor->id, 'date' => '2026-11-04', 'slot' => '16:00'])
            ->assertHasNoFormErrors();

        $this->assertSame([MessageTemplateKey::Rescheduled], $this->pendingNotices($appointment));
        $this->assertStringContainsString('cambió al miércoles 4 de noviembre', $this->text($this->pendingNotice($appointment, MessageTemplateKey::Rescheduled)));
    }

    public function test_dragging_it_in_the_agenda_to_another_doctor_rebuilds_what_is_left_to_send(): void
    {
        $other = $this->doctorOffering($this->treatment);
        $other->update(['name' => 'Dra. Ana Torres']);
        $appointment = $this->appointmentWithNotice('2026-11-04 10:30', MessageTemplateKey::AppointmentScheduled);
        $this->actingAs($this->reception);

        // Acordado por teléfono: no hay aviso del cambio, así que el que faltaba enviar dice la hora y el doctor nuevos.
        Livewire::test(Agenda::class, ['date' => '2026-11-04'])
            ->callAction('move', ['agreed' => true], arguments: ['id' => $appointment->id, 'column' => "{$other->id}|2026-11-04", 'time' => '16:00'])
            ->assertNotified('Cita movida y confirmada.');

        $this->assertSame([MessageTemplateKey::AppointmentScheduled], $this->pendingNotices($appointment));
        $notice = $this->pendingNotice($appointment, MessageTemplateKey::AppointmentScheduled);
        $this->assertSame(['miércoles 4 de noviembre', 'Dra. Ana Torres'], [$notice->params['fecha'], $notice->params['doctor']]);
        $this->assertSame($this->hourOf('2026-11-04 16:00'), $notice->params['hora']);

        // Sin acordar: va el aviso del cambio y el anterior se retira.
        Livewire::test(Agenda::class, ['date' => '2026-11-04'])
            ->callAction('move', arguments: ['id' => $appointment->id, 'column' => "{$other->id}|2026-11-04", 'time' => '17:00']);

        $this->assertSame([MessageTemplateKey::Rescheduled], $this->pendingNotices($appointment));
    }

    public function test_a_pending_reminder_follows_the_new_time_when_it_is_still_its_day(): void
    {
        // Martes 10:00: el recordatorio de la cita de mañana a las 10:30 ya está listo y sin enviar.
        $appointment = Appointment::factory()->for($this->doctor)->for($this->treatment)->at('2026-11-04 10:30')->confirmed()->create();
        $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00', 'America/Lima'));
        app(AppointmentReminders::class)->prepareReminders();
        $reminder = $this->pendingNotice($appointment, MessageTemplateKey::Reminder);

        // Se acuerda por teléfono pasarla a las 4:00 p. m. del mismo día: el recordatorio sigue, con la hora nueva.
        app(AppointmentService::class)->rescheduleByStaff($appointment, CarbonImmutable::parse('2026-11-04 16:00'), $this->reception, agreed: true);

        $this->assertSame([MessageTemplateKey::Reminder], $this->pendingNotices($appointment));
        $this->assertSame($this->hourOf('2026-11-04 16:00'), $reminder->fresh()->params['hora']);
        app(AppointmentReminders::class)->prepareReminders();
        $this->assertSame(1, $appointment->messages()->where('template_key', MessageTemplateKey::Reminder)->count(), 'No se prepara otro.');

        // Sin acordar, el aviso del cambio ya le dice la hora: el recordatorio se retira y no vuelve ese día.
        app(AppointmentService::class)->rescheduleByStaff($appointment, CarbonImmutable::parse('2026-11-04 17:00'), $this->reception);
        app(AppointmentReminders::class)->prepareReminders();

        $this->assertSame([MessageTemplateKey::Rescheduled], $this->pendingNotices($appointment));
    }

    public function test_a_pending_reminder_waits_for_its_new_day_when_the_appointment_moves_further(): void
    {
        $appointment = Appointment::factory()->for($this->doctor)->for($this->treatment)->at('2026-11-04 10:30')->confirmed()->create();
        $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00', 'America/Lima'));
        app(AppointmentReminders::class)->prepareReminders();

        app(AppointmentService::class)->rescheduleByStaff($appointment, CarbonImmutable::parse('2026-11-06 11:00'), $this->reception, agreed: true);
        app(AppointmentReminders::class)->prepareReminders();

        // Para el viernes todavía no toca: el del miércoles sale de "Por enviar" y el nuevo se prepara el jueves.
        $this->assertSame([], $this->pendingNotices($appointment));

        $this->travelTo(CarbonImmutable::parse('2026-11-05 09:05', 'America/Lima'));
        app(AppointmentReminders::class)->prepareReminders();

        $reminder = $this->pendingNotice($appointment, MessageTemplateKey::Reminder);
        $this->assertStringContainsString('tu cita de mañana viernes 6 de noviembre', $this->text($reminder));
        $this->assertSame($this->hourOf('2026-11-06 11:00'), $reminder->params['hora']);
    }

    public function test_the_patient_moving_it_from_the_link_rebuilds_the_confirmation_and_drops_an_older_change_notice(): void
    {
        $appointment = $this->appointmentWithNotice('2026-11-04 10:00');
        app(WhatsAppOutbox::class)->queueForAppointment($appointment, MessageTemplateKey::AppointmentScheduled);
        $token = basename(app(MagicLinkService::class)->issue($appointment->patient->account, MagicLinkPurpose::Appointment, $appointment));

        $this->withCredentials()->postJson(route('api.v1.links.change.save', $token), ['chosen' => ['2026-11-05 12:00']])->assertOk();

        $this->assertSame([MessageTemplateKey::BookingConfirmation, MessageTemplateKey::AppointmentScheduled], $this->pendingNotices($appointment));
        $this->assertStringContainsString('quedó reservada para el jueves 5 de noviembre', $this->text($this->pendingNotice($appointment, MessageTemplateKey::BookingConfirmation)));

        // Si antes el consultorio la había movido, ese aviso ya no sirve: el horario lo eligió él.
        app(AppointmentService::class)->rescheduleByStaff($appointment->fresh(), CarbonImmutable::parse('2026-11-05 16:00'), $this->reception);
        $this->assertSame([MessageTemplateKey::Rescheduled], $this->pendingNotices($appointment));

        $this->postJson(route('api.v1.links.change.save', $token), ['chosen' => ['2026-11-06 12:00']])->assertOk();

        $this->assertSame([], $this->pendingNotices($appointment));
    }

    public function test_the_patient_moving_it_from_my_account_rebuilds_the_confirmation(): void
    {
        $account = Account::factory()->verified()->create(['phone' => '+51987654321']);
        $patient = Patient::factory()->for($account)->create(['first_name' => 'Rosa']);
        $appointment = Appointment::factory()->for($patient)->for($this->doctor)->for($this->treatment)->at('2026-11-04 10:00')->create();
        app(WhatsAppOutbox::class)->queueForAppointment($appointment, MessageTemplateKey::BookingConfirmation);
        RememberedDevice::create(['account_id' => $account->id, 'token_hash' => hash('sha256', 'este-celular'), 'expires_at' => now()->addMonth(), 'verified_at' => now()]);

        $this->withCredentials()->withCookie(DeviceService::COOKIE, 'este-celular')
            ->postJson(route('api.v1.account.appointments.change.save', $appointment), ['chosen' => ['2026-11-05 11:00']])
            ->assertOk();

        $this->assertSame([MessageTemplateKey::BookingConfirmation], $this->pendingNotices($appointment));
        $this->assertStringContainsString('quedó reservada para el jueves 5 de noviembre', $this->text($this->pendingNotice($appointment, MessageTemplateKey::BookingConfirmation)));
    }

    public function test_passing_a_waiting_patient_to_another_doctor_rebuilds_what_is_left_to_send(): void
    {
        $other = $this->doctorOffering($this->treatment);
        $other->update(['name' => 'Dr. Luis Ramos']);
        $appointment = $this->appointmentWithNotice('2026-11-02 10:30', attributes: ['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => '2026-11-02 10:05']);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:10', 'America/Lima'));
        $this->actingAs($this->reception);

        Livewire::test(ViewAppointment::class, ['record' => $appointment->getKey()])
            ->mountAction('handOver')
            ->setActionData(['doctor_id' => $other->id])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('Dr. Luis Ramos', $this->pendingNotice($appointment, MessageTemplateKey::BookingConfirmation)->params['doctor']);
    }

    public function test_arriving_earlier_rebuilds_what_is_left_to_send(): void
    {
        $appointment = $this->appointmentWithNotice('2026-11-02 11:30', MessageTemplateKey::AppointmentScheduled);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:50', 'America/Lima'));

        app(AppointmentService::class)->arriveEarlier($appointment, CarbonImmutable::parse('2026-11-02 10:00'), $this->reception);

        $this->assertSame($this->hourOf('2026-11-02 10:00'), $this->pendingNotice($appointment, MessageTemplateKey::AppointmentScheduled)->params['hora']);
    }

    public function test_the_notice_that_the_appointment_stays_as_it_was_goes_away_once_it_moves(): void
    {
        $appointment = $this->appointmentWithNotice('2026-11-03 10:00', attributes: ['status' => AppointmentStatus::Confirmed]);
        $request = app(RescheduleRequestService::class)->requestByPatient($appointment, [CarbonImmutable::parse('2026-11-04 10:00')]);
        app(RescheduleRequestService::class)->reject($request, $this->reception, 'Ese día no hay cupo');
        $this->assertContains(MessageTemplateKey::RescheduleRejected, $this->pendingNotices($appointment));

        app(AppointmentService::class)->rescheduleByStaff($appointment->fresh(), CarbonImmutable::parse('2026-11-05 10:00'), $this->reception, agreed: true);

        $this->assertNotContains(MessageTemplateKey::RescheduleRejected, $this->pendingNotices($appointment));
    }
}
