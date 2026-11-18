<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Account;

use App\Enums\AppointmentStatus;
use App\Enums\MagicLinkPurpose;
use App\Enums\RescheduleRequestStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\RememberedDevice;
use App\Models\RescheduleRequest;
use App\Models\Treatment;
use App\Services\Access\DeviceService;
use App\Services\Access\MagicLinkService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

class LinkControllerTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        // El frontend vive en el mismo dominio: sus peticiones llevan las cookies (el celular recordado).
        $this->withCredentials();
    }

    /** @return array{0: Appointment, 1: string} */
    private function appointmentWithLink(string $startsAt = '2026-11-04 10:00', array $attributes = []): array
    {
        $treatment = Treatment::factory()->create(['duration_minutes' => 30]);
        $doctor = $this->doctorOffering($treatment);
        $appointment = Appointment::factory()->for($doctor)->for($treatment)->at($startsAt)->create($attributes);
        $url = app(MagicLinkService::class)->issue($appointment->patient->account, MagicLinkPurpose::Appointment, $appointment);

        return [$appointment, basename($url)];
    }

    public function test_opening_the_link_shows_the_appointment_without_confirming_it(): void
    {
        [$appointment, $token] = $this->appointmentWithLink();

        $this->getJson(route('api.v1.links.show', $token))
            ->assertOk()
            ->assertJsonPath('data.kind', 'appointment')
            ->assertJsonPath('data.title', 'Hola, '.$appointment->patient->first_name)
            ->assertJsonPath('data.appointment.kicker', 'Tu cita')
            ->assertJsonPath('data.appointment.status', 'Por confirmar')
            ->assertJsonPath('data.appointment.can_confirm', true)
            ->assertJsonPath('data.appointment.can_cancel', true)
            ->assertJsonPath('meta.noindex', true);

        $this->assertSame(AppointmentStatus::Pending, $appointment->fresh()->status);
        $this->assertNull($appointment->patient->account->fresh()->phone_verified_at);
        $this->assertSame(0, RememberedDevice::count());
    }

    public function test_confirming_from_the_link_verifies_the_phone_and_remembers_the_device(): void
    {
        [$appointment, $token] = $this->appointmentWithLink();

        $this->postJson(route('api.v1.links.confirm', $token))
            ->assertOk()
            ->assertJsonPath('message', '¡Listo! Tu asistencia quedó confirmada.')
            ->assertCookie(DeviceService::COOKIE);

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
        $this->assertNotNull($appointment->patient->account->fresh()->phone_verified_at);
        $this->assertSame(1, RememberedDevice::whereBelongsTo($appointment->patient->account)->count());
    }

    public function test_the_link_of_an_appointment_the_family_has_not_recognized_names_who_it_is_for_and_asks(): void
    {
        [$appointment, $token] = $this->appointmentWithLink();
        $appointment->patient->update(['first_name' => 'Carlos', 'last_name' => 'Mamani Soto']);
        // En ese celular ya había alguien que el consultorio conoce: el enlace le llega a esa familia, no a Carlos.
        Appointment::factory()->for(Patient::factory()->for($appointment->patient->account))->at('2026-10-20 10:00')->create(['status' => AppointmentStatus::Completed]);

        $this->getJson(route('api.v1.links.show', $token))
            ->assertOk()
            ->assertJsonPath('data.title', '¿Reconoces esta cita?')
            ->assertJsonPath('data.appointment.kicker', 'Cita de Carlos Mamani')
            ->assertJsonPath('data.appointment.guest_notice', true);

        $this->postJson(route('api.v1.links.confirm', $token))->assertOk();

        $this->getJson(route('api.v1.links.show', $token))
            ->assertOk()
            ->assertJsonPath('data.title', 'Hola, Carlos')
            ->assertJsonPath('data.appointment.kicker', 'Tu cita')
            ->assertJsonPath('data.appointment.status', 'Confirmada')
            ->assertJsonPath('data.appointment.guest_notice', false);
    }

    public function test_cancelling_from_the_link_cancels_the_appointment(): void
    {
        [$appointment, $token] = $this->appointmentWithLink();

        $this->postJson(route('api.v1.links.cancel', $token), ['reason' => 'Viaje'])
            ->assertOk()
            ->assertJsonPath('message', 'Tu cita fue cancelada. Cuando quieras, puedes reservar otra.');

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertSame('Viaje', $appointment->fresh()->cancellation_reason);
    }

    public function test_too_close_to_the_appointment_it_cannot_be_cancelled_from_the_link(): void
    {
        [$appointment, $token] = $this->appointmentWithLink('2026-11-02 15:00');

        $this->getJson(route('api.v1.links.show', $token))
            ->assertOk()
            ->assertJsonPath('data.appointment.can_cancel', false)
            ->assertJsonPath('data.appointment.cancel_min_hours', 12);

        $this->postJson(route('api.v1.links.cancel', $token))
            ->assertUnprocessable()
            ->assertJsonStructure(['message']);

        $this->assertSame(AppointmentStatus::Pending, $appointment->fresh()->status);
    }

    public function test_an_expired_link_says_so_and_no_longer_acts(): void
    {
        [$appointment, $token] = $this->appointmentWithLink();
        $this->travel(10)->days();

        $this->getJson(route('api.v1.links.show', $token))
            ->assertOk()
            ->assertJsonPath('data', ['kind' => 'expired'])
            ->assertJsonPath('meta.og_title', 'Enlace vencido');

        $this->postJson(route('api.v1.links.confirm', $token))->assertNotFound();
        $this->getJson(route('api.v1.links.change', $token))->assertNotFound();
        $this->assertSame(AppointmentStatus::Pending, $appointment->fresh()->status);
    }

    public function test_actions_on_an_unknown_link_return_404(): void
    {
        $unknown = str_repeat('x', 32);

        $this->postJson(route('api.v1.links.confirm', $unknown))->assertNotFound();
        $this->postJson(route('api.v1.links.cancel', $unknown))->assertNotFound();
        $this->postJson(route('api.v1.links.enter', $unknown))->assertNotFound();
        $this->postJson(route('api.v1.links.change.save', $unknown), ['chosen' => ['2026-11-05 11:00']])->assertNotFound();
    }

    public function test_the_link_of_an_appointment_does_not_open_my_account(): void
    {
        [, $token] = $this->appointmentWithLink();

        $this->postJson(route('api.v1.links.enter', $token))->assertNotFound();

        $this->assertSame(0, RememberedDevice::count());
    }

    public function test_more_than_a_day_ahead_the_patient_moves_the_appointment_at_once(): void
    {
        [$appointment, $token] = $this->appointmentWithLink('2026-11-04 10:00');

        $this->getJson(route('api.v1.links.change', $token))
            ->assertOk()
            ->assertJsonPath('data.can_change', true)
            ->assertJsonPath('data.automatic', true)
            ->assertJsonPath('data.fixed_date', false)
            ->assertJsonPath('data.pending_request', null)
            ->assertJsonPath('data.days.0.date', '2026-11-02');

        $this->getJson(route('api.v1.links.change.slots', [$token, 'date' => '2026-11-05']))
            ->assertOk()
            ->assertJsonFragment(['time' => '12:00']);

        $this->postJson(route('api.v1.links.change.save', $token), ['chosen' => ['2026-11-05 12:00']])
            ->assertOk()
            ->assertJsonPath('message', "¡Listo! Tu cita se cambió al jueves 5 de noviembre, 12:00\u{00A0}p.\u{00A0}m.")
            // Cambiar el horario también es actuar con el enlace: el celular queda recordado.
            ->assertCookie(DeviceService::COOKIE);

        $appointment->refresh();
        $this->assertSame('2026-11-05 12:00', $appointment->starts_at->format('Y-m-d H:i'));
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);

        // El enlace sigue sirviendo para la nueva fecha.
        $this->getJson(route('api.v1.links.show', $token))
            ->assertOk()
            ->assertJsonPath('data.appointment.when', "Jueves 5 de noviembre, 12:00\u{00A0}p.\u{00A0}m.");
    }

    public function test_close_to_the_appointment_the_patient_proposes_up_to_three_times(): void
    {
        [$appointment, $token] = $this->appointmentWithLink('2026-11-02 16:00');

        $this->getJson(route('api.v1.links.change', $token))
            ->assertOk()
            ->assertJsonPath('data.automatic', false)
            ->assertJsonPath('data.max_options', 3);

        $this->postJson(route('api.v1.links.change.save', $token), ['chosen' => ['2026-11-03 09:00', '2026-11-03 10:00', '2026-11-03 11:00', '2026-11-03 12:00']])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Puedes proponer hasta 3 horarios. Quita uno para elegir otro.');

        $this->postJson(route('api.v1.links.change.save', $token), ['chosen' => ['2026-11-03 09:00', '2026-11-03 11:00'], 'reason' => 'Me salió una reunión'])
            ->assertOk()
            ->assertJsonPath('message', 'Enviamos tu pedido. Te avisaremos por WhatsApp cuando el consultorio elija uno de tus horarios. Mientras tanto, tu cita sigue como estaba.');

        $request = RescheduleRequest::with('options')->sole();
        $this->assertSame(RescheduleRequestStatus::Pending, $request->status);
        $this->assertSame(['2026-11-03 09:00', '2026-11-03 11:00'], $request->options->map(fn ($option) => $option->starts_at->format('Y-m-d H:i'))->all());
        $this->assertSame('2026-11-02 16:00', $appointment->fresh()->starts_at->format('Y-m-d H:i'));

        $this->getJson(route('api.v1.links.show', $token))
            ->assertOk()
            ->assertJsonPath('data.appointment.pending_request', "martes 3 de noviembre a las 9:00\u{00A0}a.\u{00A0}m. o martes 3 de noviembre a las 11:00\u{00A0}a.\u{00A0}m.");
    }

    public function test_a_fixed_date_session_always_needs_the_clinic(): void
    {
        [, $token] = $this->appointmentWithLink('2026-11-10 10:00', ['fixed_date' => true]);

        $this->getJson(route('api.v1.links.change', $token))
            ->assertOk()
            ->assertJsonPath('data.fixed_date', true)
            ->assertJsonPath('data.automatic', false);
    }

    public function test_a_time_that_is_not_free_is_not_accepted(): void
    {
        [$appointment, $token] = $this->appointmentWithLink('2026-11-04 10:00');
        Appointment::factory()->for($appointment->doctor)->at('2026-11-05 11:00')->create();

        $this->postJson(route('api.v1.links.change.save', $token), ['chosen' => ['2026-11-05 11:00']])
            ->assertUnprocessable()
            ->assertJsonStructure(['message']);
        $this->postJson(route('api.v1.links.change.save', $token), ['chosen' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('chosen');

        $this->assertSame('2026-11-04 10:00', $appointment->fresh()->starts_at->format('Y-m-d H:i'));
    }

    public function test_a_link_only_opens_its_own_appointment(): void
    {
        [$appointment] = $this->appointmentWithLink('2026-11-04 10:00');
        [$other, $otherToken] = $this->appointmentWithLink('2026-11-04 11:00');

        $this->getJson(route('api.v1.links.change', $otherToken))
            ->assertOk()
            ->assertJsonPath('data.appointment.what', $other->treatment->name.' · '.$other->patient->first_name);
        // Sin el celular recordado de esa familia, Mi cuenta no abre la cita de nadie.
        $this->getJson(route('api.v1.account.appointments.change', $appointment))->assertForbidden();
    }
}
