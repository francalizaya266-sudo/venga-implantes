<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Account;

use App\Enums\AppointmentStatus;
use App\Enums\MagicLinkPurpose;
use App\Enums\MessageTemplateKey;
use App\Enums\PatientRelationship;
use App\Enums\PlanItemStatus;
use App\Enums\PlanPaymentMode;
use App\Enums\RescheduleRequestStatus;
use App\Enums\TreatmentPlanStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\RememberedDevice;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Access\DeviceService;
use App\Services\Access\MagicLinkService;
use App\Services\Billing\BillingService;
use App\Support\Initials;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Account $account;

    private Patient $mother;

    private Patient $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        // El frontend vive en el mismo dominio: sus peticiones llevan las cookies (el celular recordado).
        $this->withCredentials();
        $this->account = Account::factory()->verified()->create(['phone' => '+51987654321']);
        $this->mother = Patient::factory()->for($this->account)->create(['first_name' => 'Rosa', 'document_number' => '40123456']);
        $this->child = Patient::factory()->dependentOf($this->account)->create(['first_name' => 'Mateo']);
    }

    private function rememberDevice(?Account $account = null, string $token = 'este-celular'): static
    {
        RememberedDevice::create(['account_id' => ($account ?? $this->account)->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMonth(), 'verified_at' => now()]);

        return $this->withCookie(DeviceService::COOKIE, $token);
    }

    private function elevate(): static
    {
        return $this->withSession(['patient.elevated_until' => now()->addMinutes(10)->getTimestamp()]);
    }

    private function mateoHalfwayThroughHisPlan(Doctor $doctor): TreatmentPlan
    {
        $plan = TreatmentPlan::create(['patient_id' => $this->child->id, 'doctor_id' => $doctor->id, 'status' => TreatmentPlanStatus::InProgress, 'accepted_at' => now()->subMonth()]);
        $plan->items()->createMany([
            ['description' => 'Curación con resina', 'unit_price' => 120, 'status' => PlanItemStatus::Done, 'accepted_at' => now()->subMonth()],
            ['description' => 'Pulpotomía', 'unit_price' => 250, 'sessions_planned' => 2, 'sessions_done' => 1, 'status' => PlanItemStatus::InProgress, 'accepted_at' => now()->subMonth()],
        ]);

        return $plan;
    }

    public function test_a_new_device_is_a_guest_and_the_page_stays_out_of_google(): void
    {
        $this->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertExactJsonStructure(['data' => ['guest'], 'meta' => ['title', 'og_title', 'description', 'image', 'noindex', 'json_ld']])
            ->assertJsonPath('data.guest', true)
            ->assertJsonPath('meta.og_title', 'Mi cuenta')
            ->assertJsonPath('meta.noindex', true);
    }

    public function test_a_remembered_device_sees_the_family_upcoming_appointments_but_not_personal_data(): void
    {
        $upcoming = Appointment::factory()->for($this->child)->at('2026-11-04 10:00')->confirmed()->create();
        Appointment::factory()->for($this->mother)->at('2026-10-20 10:00')->create(['status' => AppointmentStatus::Completed]);
        // La cita de otra familia no se ve desde este celular.
        Appointment::factory()->at('2026-11-04 11:00')->create();

        $this->rememberDevice()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.guest', false)
            ->assertJsonPath('data.holder', ['first_name' => 'Rosa', 'initials' => Initials::of($this->mother->full_name)])
            ->assertJsonPath('data.several', true)
            ->assertJsonPath('data.phone', '987 654 321')
            ->assertJsonCount(1, 'data.upcoming')
            ->assertJsonPath('data.upcoming.0.id', $upcoming->id)
            ->assertJsonPath('data.upcoming.0.kicker', 'Cita de Mateo')
            ->assertJsonPath('data.upcoming.0.status', 'Confirmada')
            ->assertJsonPath('data.upcoming.0.tone', 'ok')
            ->assertJsonPath('data.upcoming.0.when', "Miércoles 4 de noviembre, 10:00\u{00A0}a.\u{00A0}m.")
            ->assertJsonPath('data.upcoming.0.what', $upcoming->treatment->name.' · '.$upcoming->doctor->name)
            // Sin verificar de nuevo no viajan planes, pagos, citas pasadas ni datos personales.
            ->assertJsonPath('data.elevated', false)
            ->assertJsonPath('data.plans', null)
            ->assertJsonPath('data.balances', null)
            ->assertJsonPath('data.past', null)
            ->assertJsonPath('data.family', null);
    }

    public function test_entering_with_the_access_link_remembers_the_device_and_shows_history_for_a_while(): void
    {
        Appointment::factory()->for($this->mother)->at('2026-10-20 10:00')->create(['status' => AppointmentStatus::Completed]);
        $token = basename(app(MagicLinkService::class)->issue($this->account, MagicLinkPurpose::Access));

        $this->getJson(route('api.v1.links.show', $token))
            ->assertOk()
            ->assertJsonPath('data.kind', 'access')
            ->assertJsonPath('meta.og_title', 'Entrar a Mi cuenta');
        // Abrir el enlace no recuerda nada: solo el botón "Entrar".
        $this->assertSame(0, RememberedDevice::count());

        $response = $this->postJson(route('api.v1.links.enter', $token))
            ->assertOk()
            ->assertJsonPath('message', '¡Listo! Ya puedes ver tus citas. Este celular quedará recordado.')
            ->assertCookie(DeviceService::COOKIE);

        $this->assertSame(1, RememberedDevice::whereBelongsTo($this->account)->count());

        $device = $response->getCookie(DeviceService::COOKIE)->getValue();
        $this->withCookie(DeviceService::COOKIE, $device)
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.elevated', true)
            ->assertJsonFragment(['name' => $this->mother->full_name, 'relationship' => 'Titular', 'document' => '40123456'])
            ->assertJsonPath('data.past.0.date', '20/10/2026');

        $this->travel(20)->minutes();

        $this->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.holder.first_name', 'Rosa')
            ->assertJsonPath('data.elevated', false)
            ->assertJsonPath('data.family', null);
    }

    public function test_after_checking_it_is_them_the_family_sees_its_plans_what_was_done_paid_and_the_next_cuota(): void
    {
        $doctor = Doctor::factory()->create(['name' => 'Dra. Vega']);
        $plan = TreatmentPlan::create(['patient_id' => $this->mother->id, 'doctor_id' => $doctor->id, 'title' => 'Ortodoncia', 'status' => TreatmentPlanStatus::InProgress, 'payment_mode' => PlanPaymentMode::Installments, 'accepted_at' => now()->subMonth()]);
        $plan->items()->createMany([
            ['description' => 'Colocación de brackets', 'unit_price' => 1500, 'status' => PlanItemStatus::Done, 'accepted_at' => now()->subMonth(), 'in_payment_schedule' => true],
            ['description' => 'Control de ortodoncia', 'unit_price' => 500, 'sessions_planned' => 10, 'sessions_done' => 3, 'status' => PlanItemStatus::InProgress, 'accepted_at' => now()->subMonth(), 'in_payment_schedule' => true],
        ]);
        $plan->installments()->createMany([
            ['number' => 0, 'due_on' => '2026-10-02', 'amount' => 800],
            ['number' => 1, 'due_on' => '2026-11-02', 'amount' => 600],
            ['number' => 2, 'due_on' => '2026-12-02', 'amount' => 600],
        ]);
        app(BillingService::class)->chargeDueInstallments();
        Payment::factory()->for($this->mother)->create(['amount' => 800]);
        $companion = TreatmentPlan::create(['patient_id' => $this->child->id, 'doctor_id' => $doctor->id, 'included_in_plan_id' => $plan->id, 'title' => 'Sonrisa en familia (2x1)', 'status' => TreatmentPlanStatus::Accepted, 'payment_mode' => PlanPaymentMode::PerSession, 'accepted_at' => now()]);
        $companion->items()->create(['description' => 'Limpieza dental', 'unit_price' => 120, 'discount' => 120, 'accepted_at' => now()]);
        TreatmentPlan::create(['patient_id' => $this->mother->id, 'doctor_id' => $doctor->id, 'title' => 'Carillas por aceptar', 'status' => TreatmentPlanStatus::Proposed]);

        $this->rememberDevice()
            ->elevate()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            // El más reciente va primero: el plan incluido en el 2x1 no tiene costo.
            ->assertJsonPath('data.plans.*.title', ['Sonrisa en familia (2x1)', 'Ortodoncia'])
            ->assertJsonPath('data.plans.0.state', 'Incluido en el 2x1')
            ->assertJsonPath('data.plans.0.included_in', 'Rosa')
            ->assertJsonPath('data.plans.0.progress', null)
            ->assertJsonPath('data.plans.1.state', 'Por pagar')
            ->assertJsonPath('data.plans.1.who', 'Rosa · Dra. Vega')
            ->assertJsonPath('data.plans.1.progress.percent', 40)
            ->assertJsonPath('data.plans.1.progress.paid', 'S/ 800.00')
            ->assertJsonPath('data.plans.1.progress.total', 'S/ 2,000.00')
            ->assertJsonPath('data.plans.1.progress.remaining', 'S/ 1,200.00')
            ->assertJsonPath('data.plans.1.progress.owed', ['label' => 'Para pagar ya', 'amount' => 'S/ 600.00', 'late' => false])
            ->assertJsonPath('data.plans.1.progress.next', ['amount' => 'S/ 600.00', 'day' => 'Miércoles 2 de diciembre', 'label' => 'Cuota 2 de 2'])
            ->assertJsonPath('data.plans.1.items_summary', 'Tratamientos: 1 de 2 hechos')
            ->assertJsonPath('data.plans.1.items.*.state', ['Hecho', '3 de 10 sesiones'])
            ->assertJsonPath('data.balances', [['name' => $this->mother->full_name, 'text' => 'S/ 600.00 por pagar', 'owed' => true]]);
    }

    public function test_the_home_greets_a_returning_patient_with_their_appointment_their_sessions_and_their_checkup(): void
    {
        $evaluation = Treatment::factory()->evaluation()->create();
        $doctor = $this->doctorOffering($evaluation);
        $doctor->update(['name' => 'Dra. Vega']);
        Appointment::factory()->for($this->mother)->for($doctor)->for(Treatment::factory()->create(['name' => 'Limpieza dental']))->at('2026-11-04 10:00')->create();
        // A Mateo le falta una sesión de su plan y no tiene cita.
        $this->mateoHalfwayThroughHisPlan($doctor);
        // Lucía vino hace 7 meses y no volvió: le toca su control.
        $lucia = Patient::factory()->dependentOf($this->account)->create(['first_name' => 'Lucía', 'last_visit_at' => now()->subMonths(7)]);

        $this->getJson(route('api.v1.home'))->assertOk()->assertJsonPath('data.welcome', null);

        $this->rememberDevice()
            ->getJson(route('api.v1.home'))
            ->assertOk()
            ->assertJsonPath('data.welcome.name', 'Rosa')
            ->assertJsonPath('data.welcome.appointments.0.kicker', 'Tu cita')
            ->assertJsonPath('data.welcome.appointments.0.status', 'Por confirmar')
            ->assertJsonPath('data.welcome.appointments.0.what', 'Limpieza dental · Dra. Vega')
            ->assertJsonPath('data.welcome.appointments.0.can_confirm', true)
            ->assertJsonPath('data.welcome.appointments.0.ask_health.text', 'Cuéntanos de tu salud en dos minutos.')
            ->assertJsonPath('data.welcome.sessions.0.kicker', 'Tratamiento de Mateo')
            ->assertJsonPath('data.welcome.sessions.0.title', 'A Mateo le falta 1 de 3 sesiones')
            ->assertJsonPath('data.welcome.sessions.0.button', 'Pedir su próxima sesión')
            ->assertJsonPath('data.welcome.sessions.0.requested', false)
            ->assertJsonPath('data.welcome.checkups.0.kicker', 'Control de Lucía')
            ->assertJsonPath('data.welcome.checkups.0.title', '7 meses desde su última visita')
            ->assertJsonPath('data.welcome.checkups.0.url', route('site.booking', ['tratamiento' => $evaluation->slug, 'para' => $lucia->id]));
    }

    public function test_relatives_with_the_same_first_name_show_their_surname(): void
    {
        $this->mother->update(['first_name' => 'María', 'last_name' => 'Quispe Huamán']);
        $daughter = Patient::factory()->dependentOf($this->account)->create(['first_name' => 'María', 'last_name' => 'Flores Ticona']);
        Appointment::factory()->for($this->mother)->at('2026-11-04 10:00')->create();
        Appointment::factory()->for($daughter)->at('2026-11-05 10:00')->create();
        Appointment::factory()->for($this->child)->at('2026-11-06 10:00')->create();

        $this->rememberDevice()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.upcoming.*.kicker', ['Tu cita', 'Cita de María Flores', 'Cita de Mateo']);

        $this->getJson(route('api.v1.home'))
            ->assertOk()
            ->assertJsonPath('data.welcome.appointments.*.kicker', ['Tu cita', 'Cita de María Flores'])
            ->assertJsonPath('data.welcome.more', 1);
    }

    public function test_an_appointment_booked_with_the_familys_number_from_another_phone_asks_the_family_to_recognize_it(): void
    {
        // Rosa ya confirmó la suya: en este celular hay una familia que cuidar.
        Appointment::factory()->for($this->mother)->at('2026-11-04 10:00')->confirmed()->create();
        // Alguien reservó después con el número de Rosa, desde otro celular (así crea la cita la fábrica).
        $carlos = Patient::factory()->dependentOf($this->account, PatientRelationship::Other)->create(['first_name' => 'Carlos', 'last_name' => 'Mamani Soto']);
        $booking = Appointment::factory()->for($carlos)->at('2026-11-06 10:00')->create();

        $this->rememberDevice()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.upcoming.*.kicker', ['Tu cita', 'Cita de Carlos'])
            ->assertJsonPath('data.upcoming.*.guest_notice', [false, true]);

        // En el inicio va primero, aunque sea la más lejana.
        $this->getJson(route('api.v1.home'))
            ->assertOk()
            ->assertJsonPath('data.welcome.appointments.*.kicker', ['Cita de Carlos', 'Tu cita']);

        // Mientras nadie la confirme, Carlos no figura entre los datos de la familia.
        $this->elevate()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonMissing(['name' => 'Carlos Mamani Soto']);

        $this->postJson(route('api.v1.account.appointments.confirm', $booking))
            ->assertOk()
            ->assertJsonPath('message', '¡Listo! La cita de Carlos quedó confirmada.');

        $this->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.upcoming.1.guest_notice', false)
            ->assertJsonFragment(['name' => 'Carlos Mamani Soto']);
    }

    public function test_someone_waiting_to_be_recognized_is_not_greeted_as_the_owner_of_the_phone(): void
    {
        // En el celular solo estaba Mateo, que ya vino a una cita; quien reservó "para mí" desde otro celular no es su titular.
        $this->mother->delete();
        Appointment::factory()->for($this->child)->at('2026-10-20 10:00')->create(['status' => AppointmentStatus::Completed]);
        $carlos = Patient::factory()->for($this->account)->create(['first_name' => 'Carlos', 'relationship' => PatientRelationship::Self]);
        Appointment::factory()->for($carlos)->at('2026-11-06 10:00')->create();

        $this->rememberDevice()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            // Sin titular reconocido, el saludo no lleva nombre ni iniciales.
            ->assertJsonPath('data.holder', null)
            ->assertJsonPath('data.upcoming.0.kicker', 'Cita de Carlos');

        $this->getJson(route('api.v1.home'))
            ->assertOk()
            ->assertJsonPath('data.welcome.name', null)
            ->assertJsonPath('data.welcome.appointments.0.kicker', 'Cita de Carlos');
    }

    public function test_my_account_says_why_an_appointment_can_no_longer_be_cancelled(): void
    {
        // Lunes 8:00 a. m.: faltan 7 horas, menos de las 12 que pide el consultorio.
        Appointment::factory()->for($this->mother)->at('2026-11-02 15:00')->confirmed()->create();

        $this->rememberDevice()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.upcoming.0.can_change', true)
            ->assertJsonPath('data.upcoming.0.can_cancel', false)
            ->assertJsonPath('data.upcoming.0.cancel_min_hours', 12)
            ->assertJsonPath('data.upcoming.0.cancel_message', 'Hola, necesito cancelar la cita de '.$this->mother->full_name." del lunes 2 de noviembre a las 3:00\u{00A0}p.\u{00A0}m.");
    }

    public function test_asking_for_the_next_session_tells_reception_once_and_only_from_the_family_phone(): void
    {
        $reception = User::factory()->reception()->create();
        $this->mateoHalfwayThroughHisPlan(Doctor::factory()->create(['name' => 'Dra. Vega']));

        $this->rememberDevice()
            ->postJson(route('api.v1.account.patients.session', $this->child))
            ->assertOk()
            ->assertJsonPath('message', 'Listo: recepción ya sabe que Mateo quiere su próxima sesión. Te escribiremos por WhatsApp con el horario.');
        $this->postJson(route('api.v1.account.patients.session', $this->child))->assertOk();

        $alert = $reception->notifications()->sole()->data;
        $this->assertSame('Pide su próxima sesión', $alert['title']);
        $this->assertSame($this->child->full_name.' quiere agendar su próxima sesión (lleva 2 de 3, con Dra. Vega). Escríbele con el horario.', $alert['body']);

        $this->getJson(route('api.v1.home'))->assertJsonPath('data.welcome.sessions.0.requested', true);

        // Otro celular no puede pedirla por Mateo.
        $other = Account::factory()->verified()->create(['phone' => '+51912345678']);
        $this->rememberDevice($other, 'otro-celular')
            ->postJson(route('api.v1.account.patients.session', $this->child))
            ->assertForbidden();
    }

    public function test_asking_for_access_leaves_one_link_ready_for_reception_without_revealing_numbers(): void
    {
        $reception = User::factory()->reception()->create();
        $answer = ['phone' => '987 654 321', 'whatsapp_message' => 'Hola, pedí mi enlace para entrar a Mi cuenta. Mi celular es 987 654 321.'];

        $this->postJson(route('api.v1.account.access'), ['phone' => '987 654 321'])->assertOk()->assertExactJson(['data' => $answer]);
        $this->postJson(route('api.v1.account.access'), ['phone' => '987654321'])->assertOk();
        // A un número que no existe se le responde lo mismo.
        $this->postJson(route('api.v1.account.access'), ['phone' => '911 111 111'])->assertOk()->assertJsonPath('data.phone', '911 111 111');
        $this->postJson(route('api.v1.account.access'), ['phone' => '12345'])->assertUnprocessable()->assertJsonValidationErrors('phone');

        $this->assertSame(1, WhatsappMessage::where('template_key', MessageTemplateKey::AccessLink)->count());
        $this->assertSame(1, $reception->notifications()->count());

        // Un tercer pedido seguido para el mismo número ya no se cuenta.
        $this->postJson(route('api.v1.account.access'), ['phone' => '987 654 321'])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Ya recibimos tu pedido. Te escribiremos pronto por WhatsApp.');
    }

    public function test_the_remembered_device_asks_for_the_link_that_shows_the_personal_data(): void
    {
        $this->postJson(route('api.v1.account.verification'))->assertForbidden();

        $this->rememberDevice()
            ->postJson(route('api.v1.account.verification'))
            ->assertOk()
            ->assertJsonPath('message', 'Te enviaremos un enlace por WhatsApp. Al tocarlo, verás tus planes, pagos, citas anteriores y datos durante unos minutos.');

        $this->assertSame(1, WhatsappMessage::where('template_key', MessageTemplateKey::AccessLink)->count());
    }

    public function test_the_family_confirms_and_cancels_from_my_account(): void
    {
        $appointment = Appointment::factory()->for($this->child)->at('2026-11-04 10:00')->create();
        $this->rememberDevice();

        $this->postJson(route('api.v1.account.appointments.confirm', $appointment))->assertOk();
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);

        $this->postJson(route('api.v1.account.appointments.cancel', $appointment), ['reason' => 'Viaje'])
            ->assertOk()
            ->assertJsonPath('message', 'La cita fue cancelada.');
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertSame('Viaje', $appointment->fresh()->cancellation_reason);
    }

    public function test_cancelling_too_late_says_why_and_keeps_the_appointment(): void
    {
        $appointment = Appointment::factory()->for($this->mother)->at('2026-11-02 15:00')->create();

        $this->rememberDevice()
            ->postJson(route('api.v1.account.appointments.cancel', $appointment))
            ->assertUnprocessable()
            ->assertJsonStructure(['message']);

        $this->assertSame(AppointmentStatus::Pending, $appointment->fresh()->status);
    }

    public function test_another_familys_appointment_is_off_limits(): void
    {
        $stranger = Appointment::factory()->at('2026-11-04 10:00')->create();
        $this->rememberDevice();

        $this->postJson(route('api.v1.account.appointments.confirm', $stranger))->assertForbidden();
        $this->postJson(route('api.v1.account.appointments.cancel', $stranger))->assertForbidden();
        $this->getJson(route('api.v1.account.appointments.change', $stranger))->assertForbidden();
        $this->getJson(route('api.v1.account.appointments.change.slots', [$stranger, 'date' => '2026-11-05']))->assertForbidden();
        $this->postJson(route('api.v1.account.appointments.change.save', $stranger), ['chosen' => ['2026-11-05 11:00']])->assertForbidden();

        $this->assertSame(AppointmentStatus::Pending, $stranger->fresh()->status);
    }

    public function test_the_family_moves_an_appointment_from_my_account(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental', 'duration_minutes' => 30]);
        $doctor = $this->doctorOffering($treatment);
        $appointment = Appointment::factory()->for($this->mother)->for($doctor)->for($treatment)->at('2026-11-04 10:00')->create();
        $this->rememberDevice();

        $this->getJson(route('api.v1.account.appointments.change', $appointment))
            ->assertOk()
            ->assertJsonPath('data.appointment.what', 'Limpieza dental · Rosa')
            ->assertJsonPath('data.appointment.now', "Miércoles 4 de noviembre, 10:00\u{00A0}a.\u{00A0}m.")
            ->assertJsonPath('data.can_change', true)
            ->assertJsonPath('data.automatic', true)
            ->assertJsonPath('data.date', '2026-11-02')
            ->assertJsonPath('meta.og_title', 'Cambiar el horario');

        $this->getJson(route('api.v1.account.appointments.change.slots', [$appointment, 'date' => '2026-11-05']))
            ->assertOk()
            ->assertJsonPath('data.date', '2026-11-05')
            ->assertJsonFragment(['time' => '11:00']);

        $this->postJson(route('api.v1.account.appointments.change.save', $appointment), ['chosen' => ['2026-11-05 11:00']])
            ->assertOk()
            ->assertJsonPath('message', "¡Listo! Tu cita se cambió al jueves 5 de noviembre, 11:00\u{00A0}a.\u{00A0}m.");

        $this->assertSame('2026-11-05 11:00', $appointment->fresh()->starts_at->format('Y-m-d H:i'));
    }

    public function test_my_account_and_the_home_remember_the_change_the_family_asked_for_while_it_waits(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental', 'duration_minutes' => 30]);
        $doctor = $this->doctorOffering($treatment);
        // Hoy a las 4:00 p. m.: tan cerca que el cambio lo aprueba el consultorio.
        $appointment = Appointment::factory()->for($this->mother)->for($doctor)->for($treatment)->at('2026-11-02 16:00')->create();
        $this->rememberDevice();

        $this->getJson(route('api.v1.account.show'))->assertOk()->assertJsonPath('data.upcoming.0.pending_request', null);

        $this->postJson(route('api.v1.account.appointments.change.save', $appointment), ['chosen' => ['2026-11-03 09:00', '2026-11-03 11:00']])
            ->assertOk()
            ->assertJsonPath('message', 'Enviamos tu pedido. Te avisaremos por WhatsApp cuando el consultorio elija uno de tus horarios. Mientras tanto, tu cita sigue como estaba.');

        // Al volver a Mi cuenta (o recargar), la tarjeta dice que espera respuesta y con qué horarios.
        $proposed = "martes 3 de noviembre a las 9:00\u{00A0}a.\u{00A0}m. o martes 3 de noviembre a las 11:00\u{00A0}a.\u{00A0}m.";
        $this->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.upcoming.0.when', "Lunes 2 de noviembre, 4:00\u{00A0}p.\u{00A0}m.")
            ->assertJsonPath('data.upcoming.0.pending_request', $proposed);
        $this->getJson(route('api.v1.home'))
            ->assertOk()
            ->assertJsonPath('data.welcome.appointments.0.pending_request', $proposed);

        // Cuando el consultorio responde, deja de decirlo.
        $appointment->rescheduleRequests()->update(['status' => RescheduleRequestStatus::Rejected]);
        $this->getJson(route('api.v1.account.show'))->assertOk()->assertJsonPath('data.upcoming.0.pending_request', null);
    }

    public function test_moving_an_appointment_asks_for_a_day_and_a_time_that_are_still_free(): void
    {
        $treatment = Treatment::factory()->create(['duration_minutes' => 30]);
        $doctor = $this->doctorOffering($treatment);
        $appointment = Appointment::factory()->for($this->mother)->for($doctor)->for($treatment)->at('2026-11-04 10:00')->create();
        $this->rememberDevice();

        $this->getJson(route('api.v1.account.appointments.change.slots', [$appointment, 'date' => 'jueves']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');
        $this->postJson(route('api.v1.account.appointments.change.save', $appointment), ['chosen' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('chosen');
        // A las 3 de la madrugada no hay atención: se dice por qué y se elige otro.
        $this->postJson(route('api.v1.account.appointments.change.save', $appointment), ['chosen' => ['2026-11-05 03:00']])
            ->assertUnprocessable()
            ->assertJsonMissingPath('errors');

        $this->assertSame('2026-11-04 10:00', $appointment->fresh()->starts_at->format('Y-m-d H:i'));
    }

    public function test_leaving_forgets_this_device(): void
    {
        $this->rememberDevice()
            ->postJson(route('api.v1.account.logout'))
            ->assertOk()
            ->assertJsonPath('message', 'Listo. Este celular ya no está recordado.');

        $this->assertNotNull(RememberedDevice::sole()->revoked_at);
        $this->getJson(route('api.v1.account.show'))->assertOk()->assertJsonPath('data.guest', true);
    }
}
