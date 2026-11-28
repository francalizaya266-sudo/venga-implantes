<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Account;

use App\Enums\AppointmentStatus;
use App\Enums\MagicLinkPurpose;
use App\Enums\MessageTemplateKey;
use App\Enums\PatientRelationship;
use App\Enums\PaymentMethod;
use App\Enums\PlanItemStatus;
use App\Enums\PlanPaymentMode;
use App\Enums\RescheduleRequestStatus;
use App\Enums\TreatmentPlanStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Consultation;
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

    /** @param  bool  $verified  Si tocó un enlace del número; si solo reservó con un número nuevo, no. */
    private function rememberDevice(?Account $account = null, string $token = 'este-celular', bool $verified = true): static
    {
        RememberedDevice::create(['account_id' => ($account ?? $this->account)->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMonth(), 'verified_at' => $verified ? now() : null]);

        return $this->withCookie(DeviceService::COOKIE, $token);
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

    public function test_a_phone_that_only_booked_sees_the_family_upcoming_appointments_but_not_personal_data(): void
    {
        $upcoming = Appointment::factory()->for($this->child)->at('2026-11-04 10:00')->confirmed()->create();
        Appointment::factory()->for($this->mother)->at('2026-10-20 10:00')->create(['status' => AppointmentStatus::Completed]);
        // La cita de otra familia no se ve desde este celular.
        Appointment::factory()->at('2026-11-04 11:00')->create();

        $this->rememberDevice(verified: false)
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
            // Hasta que toque un enlace del número no viajan planes, pagos, citas pasadas ni datos personales.
            ->assertJsonPath('data.elevated', false)
            ->assertJsonPath('data.plans', null)
            ->assertJsonPath('data.balances', null)
            ->assertJsonPath('data.past', null)
            ->assertJsonPath('data.family', null);
    }

    public function test_entering_with_the_access_link_keeps_this_phone_showing_everything_until_it_leaves(): void
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

        // No vence a los minutos: el mismo celular sigue viendo todo meses después, sin pedir otro enlace.
        $this->travel(10)->months();

        $this->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.elevated', true)
            ->assertJsonPath('data.past.0.date', '20/10/2026');

        $this->postJson(route('api.v1.account.logout'))->assertOk();

        $this->getJson(route('api.v1.account.show'))->assertOk()->assertJsonPath('data.guest', true);
    }

    public function test_a_phone_that_goes_more_than_400_days_without_entering_asks_for_a_new_link(): void
    {
        $token = basename(app(MagicLinkService::class)->issue($this->account, MagicLinkPurpose::Access));
        $device = $this->postJson(route('api.v1.links.enter', $token))->getCookie(DeviceService::COOKIE)->getValue();

        $this->travel(399)->days();
        $this->withCookie(DeviceService::COOKIE, $device)->getJson(route('api.v1.account.show'))->assertJsonPath('data.elevated', true);

        // Cada visita renueva los 400 días; sin entrar en todo ese tiempo, el celular deja de estar recordado.
        $this->travel(401)->days();
        $this->getJson(route('api.v1.account.show'))->assertJsonPath('data.guest', true);
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
            ->assertJsonPath('data.plans.1.progress.next', ['amount' => 'S/ 600.00', 'day' => 'Miércoles 2 de diciembre', 'date' => '2026-12-02', 'label' => 'Cuota 2 de 2'])
            // Las mismas palabras que lee el doctor en el panel (RN-28).
            ->assertJsonPath('data.plans.1.steps', ['done' => 1, 'started' => 1, 'total' => 2, 'text' => '1 de 2 hechos · 1 en curso'])
            ->assertJsonPath('data.plans.1.items.*.state', ['Hecho', '3 de 10 sesiones'])
            ->assertJsonPath('data.balances', [['patient_id' => $this->mother->id, 'name' => $this->mother->full_name, 'text' => 'S/ 600.00 por pagar', 'owed' => true]])
            ->assertJsonPath('data.owed_total', 'S/ 600.00');
    }

    public function test_a_plan_shows_only_what_the_family_accepted_with_its_tooth_its_next_session_and_every_cuota(): void
    {
        $doctor = Doctor::factory()->create(['name' => 'Dra. Vega']);
        $plan = TreatmentPlan::create(['patient_id' => $this->mother->id, 'doctor_id' => $doctor->id, 'title' => 'Ortodoncia', 'status' => TreatmentPlanStatus::InProgress, 'payment_mode' => PlanPaymentMode::Installments, 'accepted_at' => now()->subMonth()]);
        $accepted = ['accepted_at' => now()->subMonth(), 'in_payment_schedule' => true];
        $plan->items()->createMany([
            ['description' => 'Colocación de brackets', 'unit_price' => 1500, 'status' => PlanItemStatus::Done] + $accepted,
            ['description' => 'Control de ortodoncia', 'unit_price' => 400, 'sessions_planned' => 10, 'sessions_done' => 3, 'status' => PlanItemStatus::InProgress] + $accepted,
            ['description' => 'Curación con resina', 'tooth' => '36', 'surfaces' => 'O', 'unit_price' => 100] + $accepted,
            // Lo que se canceló y lo que la familia no aceptó no se cuentan ni se muestran.
            ['description' => 'Blanqueamiento', 'unit_price' => 450, 'status' => PlanItemStatus::Cancelled] + $accepted,
            ['description' => 'Carillas', 'unit_price' => 900],
        ]);
        $plan->installments()->createMany([
            ['number' => 0, 'due_on' => '2026-10-02', 'amount' => 800],
            ['number' => 1, 'due_on' => '2026-10-20', 'amount' => 600],
            ['number' => 2, 'due_on' => '2027-01-04', 'amount' => 600],
        ]);
        app(BillingService::class)->chargeDueInstallments();
        // Pagó la inicial y abonó S/ 200 de la cuota que ya venció.
        Payment::factory()->for($this->mother)->create(['amount' => 1000]);
        $control = $plan->items()->where('description', 'Control de ortodoncia')->sole();
        // La sesión cancelada no cuenta: la próxima es la que sigue en pie.
        Appointment::factory()->for($this->mother)->for($doctor)->at('2026-11-03 09:00')->cancelled()->create(['treatment_plan_item_id' => $control->id]);
        Appointment::factory()->for($this->mother)->for($doctor)->at('2026-11-04 10:00')->confirmed()->create(['treatment_plan_item_id' => $control->id]);

        $this->rememberDevice()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.plans.0.patient_id', $this->mother->id)
            ->assertJsonPath('data.plans.0.state', 'Atrasado')
            ->assertJsonPath('data.plans.0.tone', 'bad')
            ->assertJsonPath('data.plans.0.finished', false)
            ->assertJsonPath('data.plans.0.steps', ['done' => 1, 'started' => 1, 'total' => 3, 'text' => '1 de 3 hechos · 1 en curso'])
            // La pieza va con su nombre, no con su número.
            ->assertJsonPath('data.plans.0.items.*.label', ['Colocación de brackets', 'Control de ortodoncia', 'Curación con resina'])
            ->assertJsonPath('data.plans.0.items.*.detail', [null, null, 'Primer molar inferior izquierdo · cara oclusal'])
            ->assertJsonPath('data.plans.0.items.*.state', ['Hecho', '3 de 10 sesiones', 'Por hacer'])
            ->assertJsonPath('data.plans.0.items.*.status', ['done', 'in_progress', 'pending'])
            ->assertJsonPath('data.plans.0.items.*.sessions', [null, ['done' => 3, 'planned' => 10], null])
            ->assertJsonPath('data.plans.0.next_session', "Miércoles 4 de noviembre, 10:00\u{00A0}a.\u{00A0}m.")
            ->assertJsonPath('data.plans.0.progress.percent', 50)
            ->assertJsonPath('data.plans.0.progress.owed', ['label' => 'Vencido', 'amount' => 'S/ 400.00', 'late' => true])
            // El pago que sigue cae en otro año: se dice cuál.
            ->assertJsonPath('data.plans.0.progress.next', ['amount' => 'S/ 600.00', 'day' => 'Lunes 4 de enero de 2027', 'date' => '2027-01-04', 'label' => 'Cuota 2 de 2'])
            ->assertJsonPath('data.plans.0.installments.*.label', ['Inicial', 'Cuota 1 de 2', 'Cuota 2 de 2'])
            ->assertJsonPath('data.plans.0.installments.*.day', ['Viernes 2 de octubre', 'Martes 20 de octubre', 'Lunes 4 de enero de 2027'])
            ->assertJsonPath('data.plans.0.installments.*.amount', ['S/ 800.00', 'S/ 600.00', 'S/ 600.00'])
            ->assertJsonPath('data.plans.0.installments.*.state', ['paid', 'late', 'upcoming'])
            ->assertJsonPath('data.plans.0.installments.*.state_label', ['Pagado', 'Vencido', 'Por vencer'])
            // De la cuota vencida solo se pide lo que falta.
            ->assertJsonPath('data.plans.0.installments.*.left', [null, 'S/ 400.00', null]);
    }

    public function test_a_cuota_that_is_due_today_is_not_late_yet(): void
    {
        $plan = TreatmentPlan::create(['patient_id' => $this->mother->id, 'doctor_id' => Doctor::factory()->create()->id, 'title' => 'Implante', 'status' => TreatmentPlanStatus::Accepted, 'payment_mode' => PlanPaymentMode::Installments, 'accepted_at' => now()]);
        $plan->items()->create(['description' => 'Implante dental', 'unit_price' => 900, 'accepted_at' => now(), 'in_payment_schedule' => true]);
        $plan->installments()->createMany([
            ['number' => 0, 'due_on' => '2026-11-02', 'amount' => 300],
            ['number' => 1, 'due_on' => '2026-12-02', 'amount' => 600],
        ]);
        app(BillingService::class)->chargeDueInstallments();

        $this->rememberDevice()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.plans.0.state', 'Por pagar')
            ->assertJsonPath('data.plans.0.tone', null)
            ->assertJsonPath('data.plans.0.steps.text', '0 de 1 hechos')
            ->assertJsonPath('data.plans.0.next_session', null)
            ->assertJsonPath('data.plans.0.progress.owed', ['label' => 'Para pagar ya', 'amount' => 'S/ 300.00', 'late' => false])
            ->assertJsonPath('data.plans.0.installments.*.state_label', ['Vence hoy', 'Por vencer'])
            ->assertJsonPath('data.plans.0.installments.*.left', [null, null]);
    }

    public function test_finished_and_paid_plans_go_last_and_cancelled_ones_are_not_shown(): void
    {
        $doctor = Doctor::factory()->create();
        $done = ['status' => PlanItemStatus::Done, 'accepted_at' => now()];
        // El más reciente ya se terminó y se pagó: queda al final, como historial.
        $sealants = TreatmentPlan::create(['patient_id' => $this->child->id, 'doctor_id' => $doctor->id, 'title' => 'Sellantes', 'status' => TreatmentPlanStatus::Completed, 'payment_mode' => PlanPaymentMode::PerSession, 'accepted_at' => now()]);
        $sealant = $sealants->items()->create(['description' => 'Sellante', 'unit_price' => 80] + $done);
        Charge::factory()->for($this->child)->create(['treatment_plan_item_id' => $sealant->id, 'amount' => 80]);
        Payment::factory()->for($this->child)->create(['amount' => 80]);
        // Terminado pero con saldo: sigue arriba hasta que se pague.
        $crown = TreatmentPlan::create(['patient_id' => $this->mother->id, 'doctor_id' => $doctor->id, 'title' => 'Corona', 'status' => TreatmentPlanStatus::Completed, 'payment_mode' => PlanPaymentMode::PerSession, 'accepted_at' => now()->subWeek()]);
        $crownItem = $crown->items()->create(['description' => 'Corona de porcelana', 'unit_price' => 700] + $done);
        Charge::factory()->for($this->mother)->create(['treatment_plan_item_id' => $crownItem->id, 'amount' => 700]);
        $this->mateoHalfwayThroughHisPlan($doctor)->update(['title' => 'Curaciones']);
        TreatmentPlan::create(['patient_id' => $this->mother->id, 'doctor_id' => $doctor->id, 'title' => 'Blanqueamiento', 'status' => TreatmentPlanStatus::Cancelled, 'accepted_at' => now()]);

        $this->rememberDevice()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.plans.*.title', ['Corona', 'Curaciones', 'Sellantes'])
            ->assertJsonPath('data.plans.*.finished', [false, false, true])
            ->assertJsonPath('data.plans.*.state', ['Por pagar', 'Al día', 'Terminado'])
            ->assertJsonPath('data.plans.2.progress.percent', 100)
            ->assertJsonPath('data.plans.2.progress.remaining', 'S/ 0.00');
    }

    public function test_a_past_appointment_keeps_what_the_patient_took_home_and_nothing_of_the_clinical_record(): void
    {
        $doctor = Doctor::factory()->create(['name' => 'Dra. Vega']);
        $endodontics = Treatment::factory()->create(['name' => 'Endodoncia', 'aftercare' => 'No mastiques de ese lado por dos días.']);
        $plan = TreatmentPlan::create(['patient_id' => $this->mother->id, 'doctor_id' => $doctor->id, 'status' => TreatmentPlanStatus::InProgress, 'accepted_at' => now()->subMonth()]);
        $worked = $plan->items()->createMany([
            ['description' => 'Endodoncia', 'tooth' => '36', 'unit_price' => 600, 'status' => PlanItemStatus::Done, 'accepted_at' => now()->subMonth()],
            ['description' => 'Radiografía periapical', 'unit_price' => 40, 'status' => PlanItemStatus::Done, 'accepted_at' => now()->subMonth()],
        ]);
        $attended = Appointment::factory()->for($this->mother)->for($doctor)->for($endodontics)->at('2026-10-20 10:00')->create(['status' => AppointmentStatus::Completed]);
        $consultation = Consultation::factory()->for($this->mother)->for($doctor)->create([
            'appointment_id' => $attended->id,
            'closed_at' => '2026-10-20 10:40',
            'chief_complaint' => 'Dolor al masticar',
            'clinical_exam' => 'Caries profunda con compromiso pulpar.',
            'procedures' => 'Apertura cameral y conductometría.',
            'indications' => ' Toma el analgésico después de comer. ',
        ]);
        $consultation->planItems()->attach($worked);
        $consultation->prescriptions()->create(['patient_id' => $this->mother->id, 'doctor_id' => $doctor->id, 'issued_at' => '2026-10-20 10:30'])
            ->items()->createMany([
                ['medication' => 'Paracetamol 500 mg', 'instructions' => null, 'sort_order' => 2],
                ['medication' => 'Ibuprofeno 400 mg', 'instructions' => '1 tableta cada 8 horas por 3 días', 'sort_order' => 1],
            ]);
        // El doctor todavía no cerró esta atención: lo que va escribiendo no sale a la web.
        $open = Appointment::factory()->for($this->child)->for($doctor)->at('2026-10-27 10:00')->create(['status' => AppointmentStatus::Completed]);
        Consultation::factory()->for($this->child)->for($doctor)->create(['appointment_id' => $open->id, 'closed_at' => null, 'indications' => 'Borrador de indicaciones']);
        Appointment::factory()->for($this->child)->for($doctor)->at('2026-10-13 10:00')->create(['status' => AppointmentStatus::NoShow]);
        Appointment::factory()->for($this->mother)->for($doctor)->at('2026-10-06 10:00')->cancelled()->create();
        // La cita de otra familia no aparece.
        Appointment::factory()->for($doctor)->at('2026-10-21 10:00')->create(['status' => AppointmentStatus::Completed]);

        $this->rememberDevice()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            // La más reciente primero.
            ->assertJsonPath('data.past.*.date', ['27/10/2026', '20/10/2026', '13/10/2026', '06/10/2026'])
            ->assertJsonPath('data.past.*.name', ['Mateo', 'Rosa', 'Mateo', 'Rosa'])
            ->assertJsonPath('data.past.*.status', ['Atendida', 'Atendida', 'No asistió', 'Cancelada'])
            ->assertJsonPath('data.past.*.tone', ['ok', 'ok', 'bad', 'bad'])
            ->assertJsonPath('data.past.1.patient_id', $this->mother->id)
            ->assertJsonPath('data.past.1.treatment', 'Endodoncia')
            ->assertJsonPath('data.past.1.doctor', 'Dra. Vega')
            ->assertJsonPath('data.past.1.visit', [
                'done' => ['Endodoncia · Primer molar inferior izquierdo', 'Radiografía periapical'],
                'indications' => 'Toma el analgésico después de comer.',
                'medicines' => [
                    ['name' => 'Ibuprofeno 400 mg', 'how' => '1 tableta cada 8 horas por 3 días'],
                    ['name' => 'Paracetamol 500 mg', 'how' => null],
                ],
                'aftercare' => 'No mastiques de ese lado por dos días.',
            ])
            ->assertJsonPath('data.past.0.visit', null)
            ->assertJsonPath('data.past.2.visit', null)
            // La historia clínica no sale del consultorio: ni el motivo, ni el examen, ni lo que anotó el doctor.
            ->assertDontSee(['Dolor al masticar', 'Caries profunda', 'Apertura cameral', 'Borrador de indicaciones']);
    }

    public function test_todays_appointment_that_is_still_expected_stays_among_the_upcoming_and_not_in_the_past(): void
    {
        // Lunes 8:00 a. m.: la de las 7:00 ya empezó y sigue confirmada; la otra de hoy ya se atendió.
        $expected = Appointment::factory()->for($this->mother)->at('2026-11-02 07:00')->confirmed()->create();
        $attended = Appointment::factory()->for($this->child)->at('2026-11-02 07:30')->create(['status' => AppointmentStatus::Completed]);

        $this->rememberDevice()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.upcoming.*.id', [$expected->id])
            ->assertJsonPath('data.upcoming.0.countdown', 'Hoy')
            ->assertJsonPath('data.past.*.id', [$attended->id]);
    }

    public function test_an_attended_appointment_that_left_nothing_to_take_home_has_nothing_to_open(): void
    {
        $attended = Appointment::factory()->for($this->mother)->for(Treatment::factory()->create(['aftercare' => null]))->at('2026-10-20 10:00')->create(['status' => AppointmentStatus::Completed]);
        Consultation::factory()->for($this->mother)->create(['appointment_id' => $attended->id, 'closed_at' => now(), 'indications' => null]);

        $this->rememberDevice()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.past.0.status', 'Atendida')
            ->assertJsonPath('data.past.0.visit', null);
    }

    public function test_the_family_sees_what_it_paid_and_what_it_owes_between_everyone(): void
    {
        $lucia = Patient::factory()->dependentOf($this->account)->create(['first_name' => 'Lucía']);
        Charge::factory()->for($this->mother)->create(['amount' => 400]);
        Charge::factory()->for($this->child)->create(['amount' => 50]);
        Charge::factory()->for($lucia)->create(['amount' => 120]);
        Payment::factory()->for($this->mother)->create(['amount' => 150, 'method' => PaymentMethod::Yape, 'paid_at' => '2026-10-28 09:00']);
        Payment::factory()->for($this->child)->create(['amount' => 80, 'method' => PaymentMethod::Cash, 'paid_at' => '2026-10-30 09:00']);
        // Un pago anulado no se cuenta ni se muestra, y el de otra familia tampoco.
        Payment::factory()->for($this->mother)->create(['amount' => 999, 'paid_at' => '2026-10-31 09:00', 'voided_at' => now()]);
        Payment::factory()->create(['amount' => 777]);

        $this->rememberDevice()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.payments', [
                ['id' => Payment::where('amount', 80)->sole()->id, 'patient_id' => $this->child->id, 'date' => '30/10/2026', 'name' => 'Mateo', 'amount' => 'S/ 80.00', 'method' => PaymentMethod::Cash->getLabel()],
                ['id' => Payment::where('amount', 150)->sole()->id, 'patient_id' => $this->mother->id, 'date' => '28/10/2026', 'name' => 'Rosa', 'amount' => 'S/ 150.00', 'method' => PaymentMethod::Yape->getLabel()],
            ])
            // Cada quien con su saldo; lo que Mateo tiene a favor no se le resta a lo que deben las demás.
            ->assertJsonPath('data.balances.*.patient_id', [$lucia->id, $this->child->id, $this->mother->id])
            ->assertJsonPath('data.balances.*.text', ['S/ 120.00 por pagar', 'S/ 30.00 a favor', 'S/ 250.00 por pagar'])
            ->assertJsonPath('data.balances.*.owed', [true, false, true])
            ->assertJsonPath('data.owed_total', 'S/ 370.00');
    }

    public function test_a_family_that_owes_nothing_has_no_balances_and_nothing_to_pay(): void
    {
        Charge::factory()->for($this->mother)->create(['amount' => 150]);
        Payment::factory()->for($this->mother)->create(['amount' => 150]);

        $this->rememberDevice()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.balances', [])
            ->assertJsonPath('data.owed_total', null)
            ->assertJsonCount(1, 'data.payments');
    }

    public function test_my_account_says_who_still_has_a_session_to_ask_for_or_a_checkup_to_book_without_showing_the_plan(): void
    {
        $evaluation = Treatment::factory()->evaluation()->create();
        $doctor = $this->doctorOffering($evaluation);
        $doctor->update(['name' => 'Dra. Vega']);
        $this->mateoHalfwayThroughHisPlan($doctor);
        $lucia = Patient::factory()->dependentOf($this->account)->create(['first_name' => 'Lucía', 'last_visit_at' => now()->subMonths(7)]);

        // Lo mismo que saluda en el inicio a un celular recordado: sin montos, y el plan sigue pidiendo el enlace.
        $this->rememberDevice(verified: false)
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.sessions.0.patient_id', $this->child->id)
            ->assertJsonPath('data.sessions.0.title', 'A Mateo le falta 1 de 3 sesiones')
            ->assertJsonPath('data.sessions.0.button', 'Pedir su próxima sesión')
            ->assertJsonPath('data.sessions.0.requested', false)
            ->assertJsonPath('data.checkups.0.kicker', 'Control de Lucía')
            ->assertJsonPath('data.checkups.0.url', route('site.booking', ['tratamiento' => $evaluation->slug, 'para' => $lucia->id]))
            ->assertJsonPath('data.plans', null)
            ->assertJsonPath('data.payments', null)
            ->assertJsonPath('data.owed_total', null);
    }

    public function test_each_upcoming_appointment_says_how_soon_it_is_and_can_be_saved_to_the_calendar(): void
    {
        $today = Appointment::factory()->for($this->mother)->at('2026-11-02 15:00')->confirmed()->create();
        Appointment::factory()->for($this->child)->at('2026-11-03 09:00')->create();
        Appointment::factory()->for($this->mother)->at('2026-11-07 09:00')->create();
        // Falta más de un mes: la fecha ya lo dice.
        Appointment::factory()->for($this->child)->at('2026-12-15 09:00')->create();

        $response = $this->rememberDevice(verified: false)
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.upcoming.*.patient_id', [$this->mother->id, $this->child->id, $this->mother->id, $this->child->id])
            ->assertJsonPath('data.upcoming.*.countdown', ['Hoy', 'Mañana', 'En 5 días', null]);

        // La dirección va firmada: abre el archivo de esa cita y de ninguna otra.
        $this->get($response->json('data.upcoming.0.calendar_url'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');

        // Ya está en el consultorio: no hay nada que guardar en el calendario.
        $today->update(['status' => AppointmentStatus::CheckedIn]);

        $this->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.upcoming.0.countdown', 'Hoy')
            ->assertJsonPath('data.upcoming.0.calendar_url', null);
    }

    public function test_each_person_of_the_family_comes_with_their_data_and_whether_they_still_have_to_tell_their_health(): void
    {
        $this->mother->update(['last_name' => 'Quispe Huamán', 'birth_date' => '1988-03-09', 'email' => 'rosa@example.com']);
        $this->mother->medicalBackground()->create(['allergies' => 'Penicilina']);
        $this->child->update(['last_name' => 'Flores Quispe', 'birth_date' => null, 'email' => null, 'document_number' => null]);

        $this->rememberDevice()
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.family', [
                ['id' => $this->child->id, 'name' => 'Mateo Flores Quispe', 'short_name' => 'Mateo', 'initials' => 'MF', 'is_self' => false, 'relationship' => $this->child->relationship->getLabel(), 'document' => null, 'birth_date' => null, 'email' => null, 'ask_health' => 'Cuéntanos de la salud de Mateo en dos minutos.'],
                // Lo que ya contó de su salud no viaja a la web: solo se sabe que ya lo contó.
                ['id' => $this->mother->id, 'name' => 'Rosa Quispe Huamán', 'short_name' => 'Rosa', 'initials' => 'RQ', 'is_self' => true, 'relationship' => 'Titular', 'document' => '40123456', 'birth_date' => '09/03/1988', 'email' => 'rosa@example.com', 'ask_health' => null],
            ])
            ->assertDontSee('Penicilina');
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
            ->assertJsonPath('data.welcome.sessions.0.note', 'Con la Dra. Vega. Cada sesión la programa su doctor: pídela y te escribimos con el horario.')
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
        $this->getJson(route('api.v1.account.show'))
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
        $this->assertSame($this->child->full_name.' quiere agendar su próxima sesión (lleva 2 de 3, con la Dra. Vega). Escríbele con el horario.', $alert['body']);

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

    public function test_a_phone_that_only_booked_asks_for_the_link_and_after_tapping_it_sees_the_personal_data(): void
    {
        $this->postJson(route('api.v1.account.verification'))->assertForbidden();

        $this->rememberDevice(verified: false)
            ->postJson(route('api.v1.account.verification'))
            ->assertOk()
            ->assertJsonPath('message', 'Te enviaremos un enlace por WhatsApp. Al tocarlo, verás en este celular tus planes, pagos, citas anteriores y datos.');

        $this->assertSame(1, WhatsappMessage::where('template_key', MessageTemplateKey::AccessLink)->count());

        // Toca el enlace desde el mismo celular: queda verificado, sin otro celular recordado.
        $token = basename(app(MagicLinkService::class)->issue($this->account, MagicLinkPurpose::Access));
        $this->postJson(route('api.v1.links.enter', $token))->assertOk();

        $this->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.elevated', true)
            ->assertJsonFragment(['name' => $this->mother->full_name, 'relationship' => 'Titular']);
        $this->assertSame(1, RememberedDevice::whereBelongsTo($this->account)->whereNull('revoked_at')->count());
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
