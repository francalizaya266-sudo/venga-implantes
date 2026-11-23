<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Enums\PatientRelationship;
use App\Enums\PaymentMethod;
use App\Enums\TreatmentPlanStatus;
use App\Filament\Pages\OwingBalances;
use App\Filament\Resources\Appointments\Pages\ViewAppointment;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\Patients\RelationManagers\ChargesRelationManager;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\Widgets\TodayAppointments;
use App\Filament\Widgets\TodayStats;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Clinical\ClinicalFileService;
use App\Services\Clinical\TreatmentPlanService;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

class BillingPanelTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    /** Una limpieza de hoy ya atendida, con su cargo armado por el sistema. */
    private function completedCleaning(): Appointment
    {
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'price_from' => 120]);
        $doctor = $this->doctorOffering($cleaning);
        $appointment = Appointment::factory()->for($doctor)->for($cleaning)->at('2026-11-02 10:00')->create(['status' => AppointmentStatus::Completed]);
        app(BillingService::class)->chargeAppointment($appointment, User::factory()->reception()->create());

        return $appointment;
    }

    public function test_reception_checks_out_a_completed_appointment_in_one_step(): void
    {
        $this->freezeMonday();
        $appointment = $this->completedCleaning();
        $charge = Charge::sole();
        $this->actingAs($reception = User::factory()->reception()->create());
        Repeater::fake();

        Livewire::test(TodayAppointments::class)
            ->mountAction(TestAction::make('checkout')->table($appointment))
            ->assertSchemaStateSet([
                'lines' => [['id' => $charge->id, 'description' => 'Limpieza dental', 'amount' => 120.0, 'discount' => 0.0]],
                'amount' => 120.0,
                'method' => PaymentMethod::Cash,
            ])
            ->fillForm([
                'lines' => [
                    ['id' => $charge->id, 'description' => 'Limpieza dental', 'amount' => 150, 'discount' => 0],
                    ['id' => null, 'description' => 'Flúor', 'amount' => 30, 'discount' => 0],
                ],
                // Otro precio y un concepto agregado: se dice por qué.
                'adjustment_reason' => 'Limpieza profunda',
                'amount' => 180,
                'method' => PaymentMethod::Yape->value,
                'reference' => '004512',
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Pago registrado')
            // Pagado todo, el botón ya no aparece.
            ->assertActionHidden(TestAction::make('checkout')->table($appointment));

        $this->assertSame(['150.00', '120.00', 'Limpieza profunda'], [$charge->fresh()->amount, $charge->fresh()->original_amount, $charge->fresh()->adjustment_reason]);
        $payment = Payment::sole();
        $this->assertSame(['180.00', PaymentMethod::Yape, '004512'], [$payment->amount, $payment->method, $payment->reference]);
        $this->assertTrue($payment->receivedBy->is($reception));
        $this->assertSame(0.0, $appointment->patient->balance());
    }

    public function test_the_checkout_window_starts_on_the_amount_and_not_on_the_concept(): void
    {
        $this->freezeMonday();
        $appointment = $this->completedCleaning();
        $this->actingAs(User::factory()->reception()->create());
        Repeater::fake();

        // Antes el cursor caía en "Concepto" con el texto marcado: una tecla borraba "Limpieza dental".
        $html = Livewire::test(TodayAppointments::class)
            ->mountAction(TestAction::make('checkout')->table($appointment))
            ->getMountedActionModalHtml();

        preg_match_all('/<input\b[^>]*>/', $html, $inputs);
        $autofocused = array_values(array_filter($inputs[0], fn (string $input) => str_contains($input, 'autofocus')));
        $this->assertCount(1, $autofocused);
        $this->assertStringContainsString('.amount"', $autofocused[0]);
        $this->assertStringNotContainsString('lines', $autofocused[0]);
    }

    public function test_a_discount_cannot_be_larger_than_the_price(): void
    {
        $this->freezeMonday();
        $appointment = $this->completedCleaning();
        $this->actingAs(User::factory()->reception()->create());
        Repeater::fake();

        Livewire::test(TodayAppointments::class)
            ->callAction(TestAction::make('checkout')->table($appointment), [
                'lines' => [['id' => Charge::sole()->id, 'description' => 'Limpieza dental', 'amount' => 120, 'discount' => 150]],
                'amount' => 0,
            ])
            ->assertHasFormErrors(['lines.0.discount']);

        $this->assertSame('0.00', Charge::sole()->discount);
    }

    public function test_collecting_the_rest_of_a_visit_says_what_was_already_paid_instead_of_calling_it_credit(): void
    {
        $this->freezeMonday();
        $appointment = $this->completedCleaning();
        Payment::factory()->for($appointment->patient)->create(['appointment_id' => $appointment->id, 'amount' => 50]);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(TodayAppointments::class)
            ->mountAction(TestAction::make('checkout')->table($appointment))
            ->assertSchemaStateSet(['previous' => -50.0, 'paid_here' => 50.0, 'amount' => 70.0])
            ->assertMountedActionModalSee(['Total a pagar', 'S/ 70.00', 'Ya pagó S/ 50.00 en esta cita'])
            ->assertMountedActionModalDontSee('a favor');
    }

    public function test_money_left_in_advance_comes_off_the_total_as_credit(): void
    {
        $this->freezeMonday();
        $appointment = $this->completedCleaning();
        // La semana pasada dejó un adelanto en caja, sin cita.
        Payment::factory()->for($appointment->patient)->create(['amount' => 30, 'paid_at' => '2026-10-26 10:00']);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(TodayAppointments::class)
            ->mountAction(TestAction::make('checkout')->table($appointment))
            ->assertSchemaStateSet(['previous' => -30.0, 'paid_here' => 0.0, 'amount' => 90.0])
            ->assertMountedActionModalSee(['Total a pagar', 'S/ 90.00', 'Ya descuenta los S/ 30.00 que tenía a favor'])
            ->assertMountedActionModalDontSee('Ya pagó');
    }

    public function test_once_they_paid_from_today_the_appointment_no_longer_offers_to_collect_again(): void
    {
        $this->freezeMonday();
        $appointment = $this->completedCleaning();
        // Pagó los S/ 120 desde "Cobrar" de Hoy, mientras lo atendían: ese pago no queda en la cita.
        Payment::factory()->for($appointment->patient)->create(['amount' => 120, 'paid_at' => '2026-11-02 10:20']);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(TodayAppointments::class)
            ->assertActionHidden(TestAction::make('checkout')->table($appointment));
    }

    public function test_a_visit_covered_by_money_left_before_still_offers_to_review_what_was_charged(): void
    {
        $this->freezeMonday();
        $appointment = $this->completedCleaning();
        // Lo cubre un adelanto de la semana pasada: no debe nada, pero hoy no pagó, y recepción revisa lo cargado.
        Payment::factory()->for($appointment->patient)->create(['amount' => 120, 'paid_at' => '2026-10-26 10:00']);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(TodayAppointments::class)
            ->assertActionVisible(TestAction::make('checkout')->table($appointment));
    }

    public function test_a_free_visit_has_nothing_to_collect_unless_they_owe_from_before(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->create(['name' => 'Evaluación', 'price_from' => 0]);
        $appointment = Appointment::factory()->for($this->doctorOffering($evaluation))->for($evaluation)->at('2026-11-02 10:00')->create(['status' => AppointmentStatus::Completed]);
        app(BillingService::class)->chargeAppointment($appointment, $reception = User::factory()->reception()->create());
        $this->actingAs($reception);

        // La evaluación gratis no cargó nada: "Cobrar" no tendría qué cobrar.
        $this->assertSame(0, Charge::query()->count());
        Livewire::test(TodayAppointments::class)
            ->assertActionHidden(TestAction::make('checkout')->table($appointment));

        // Si debe algo de antes, sí: se le cobra mientras está.
        Charge::factory()->for($appointment->patient)->create(['amount' => 40]);
        Livewire::test(TodayAppointments::class)
            ->assertActionVisible(TestAction::make('checkout')->table($appointment));
    }

    public function test_doctors_do_not_handle_money(): void
    {
        $this->freezeMonday();
        $appointment = $this->completedCleaning();
        $this->actingAs($appointment->doctor->user);

        Livewire::test(TodayAppointments::class)
            ->assertActionHidden(TestAction::make('checkout')->table($appointment));

        Livewire::test(TodayStats::class)->assertDontSee('Cobrado hoy');

        $this->assertFalse(ChargesRelationManager::canViewForRecord($appointment->patient, ViewPatient::class));
    }

    public function test_the_patient_page_shows_the_balance_and_takes_payments_and_manual_charges(): void
    {
        $patient = Patient::factory()->create();
        Charge::factory()->for($patient)->create(['amount' => 200]);
        Payment::factory()->for($patient)->create(['amount' => 80, 'method' => PaymentMethod::Plin]);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(ViewPatient::class, ['record' => $patient->getKey()])
            ->assertSee('Debe S/ 120.00')
            ->assertSee('S/ 80.00 · Plin')
            ->mountAction('registerPayment')
            ->assertSchemaStateSet(['amount' => 120.0])
            ->fillForm(['amount' => 100, 'reference' => '7781', 'notes' => 'Cuota 1'])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Pago registrado');

        $this->assertSame(20.0, $patient->balance());

        $charges = Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $patient, 'pageClass' => ViewPatient::class])
            ->callAction(TestAction::make('addCharge')->table(), ['description' => 'Trabajo de laboratorio', 'amount' => 90])
            ->assertHasNoFormErrors();

        $lab = Charge::where('description', 'Trabajo de laboratorio')->sole();
        $this->assertSame(110.0, $patient->balance());

        $charges->callAction(TestAction::make('void')->table($lab), ['reason' => 'Se registró en el paciente equivocado'])
            ->assertActionHidden(TestAction::make('void')->table($lab->fresh()));

        $this->assertSame(20.0, $patient->balance());
        $this->assertSame(2, Charge::count());
    }

    public function test_a_payment_from_caja_counts_for_the_visit_it_covers_with_the_usual_method(): void
    {
        $this->freezeMonday();
        $visit = $this->completedCleaning();
        $patient = $visit->patient;
        // Una cita de más tarde que todavía no pasó no es a cuenta de qué se cobra.
        Appointment::factory()->for($patient)->for($visit->doctor)->for(Treatment::factory())->at('2026-11-02 16:00')->confirmed()->create();
        foreach ([PaymentMethod::Yape, PaymentMethod::Cash, PaymentMethod::Yape] as $days => $method) {
            Payment::factory()->for($patient)->create(['amount' => 10, 'method' => $method, 'paid_at' => now()->subDays($days + 1)]);
        }
        $this->actingAs(User::factory()->reception()->create());

        // Se elige al paciente y el pago cuenta para la visita que cubre: la limpieza que debía.
        Livewire::test(ManagePayments::class)
            ->mountAction('registerPayment')
            ->setActionData(['patient_id' => $patient->id])
            ->assertActionDataSet(['amount' => 90.0, 'method' => PaymentMethod::Yape])
            ->fillForm(['reference' => '0045'])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Pago registrado');

        $payment = Payment::latest('id')->first();
        $this->assertSame([$visit->id, $visit->doctor_id, $visit->treatment_id, PaymentMethod::Yape], [$payment->appointment_id, $payment->doctor_id, $payment->treatment_id, $payment->method]);

        // En la ficha del paciente también se propone su medio de siempre (ya al día, el pago va como adelanto, en el ⋮).
        Livewire::test(ViewPatient::class, ['record' => $patient->getKey()])
            ->assertActionHidden('registerPayment')
            ->mountAction('registerAdvance')
            ->assertSchemaStateSet(['method' => PaymentMethod::Yape]);
    }

    public function test_yape_plin_and_transfer_ask_for_the_operation_number(): void
    {
        $patient = Patient::factory()->create();
        Charge::factory()->for($patient)->create(['amount' => 200]);
        $this->actingAs(User::factory()->reception()->create());

        $window = Livewire::test(ManagePayments::class)
            ->mountAction('registerPayment')
            ->setActionData(['patient_id' => $patient->id]);

        foreach ([PaymentMethod::Yape, PaymentMethod::Plin, PaymentMethod::Transfer] as $method) {
            $window->fillForm(['amount' => 50, 'method' => $method->value, 'reference' => null])
                ->callMountedAction()
                ->assertHasFormErrors(['reference' => 'required']);
        }

        // Basta con los últimos 4 números; menos no alcanza para cuadrar el cierre.
        $window->fillForm(['method' => PaymentMethod::Yape->value, 'reference' => '12'])
            ->callMountedAction()
            ->assertHasFormErrors(['reference']);
        $window->fillForm(['reference' => '4512'])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Pago registrado');

        // En efectivo no se pide, y con tarjeta queda opcional.
        foreach ([PaymentMethod::Cash, PaymentMethod::Card] as $method) {
            Livewire::test(ManagePayments::class)
                ->mountAction('registerPayment')
                ->setActionData(['patient_id' => $patient->id])
                ->fillForm(['amount' => 10, 'method' => $method->value])
                ->callMountedAction()
                ->assertHasNoFormErrors();
        }

        $this->assertSame(['4512', null, null], Payment::query()->orderBy('id')->pluck('reference')->all());
    }

    public function test_caja_shows_each_payment_in_two_lines_on_a_phone_with_a_short_date(): void
    {
        $this->freezeMonday();
        $patient = Patient::factory()->create();
        $thisYear = Payment::factory()->for($patient)->create(['amount' => 80, 'paid_at' => '2026-11-02 10:40']);
        $lastYear = Payment::factory()->for($patient)->create(['amount' => 60, 'paid_at' => '2025-12-30 17:15']);
        $this->actingAs(User::factory()->reception()->create());

        // La tarjeta compacta del celular sale de la clase de la lista (ver .pay-list en panel.css); el año, solo si no es este.
        Livewire::test(ManagePayments::class)
            ->removeTableFilter('paid_at')
            ->assertSeeHtml('pay-list')
            ->assertTableColumnFormattedStateSet('paid_at', '02/11 10:40', $thisYear)
            ->assertTableColumnFormattedStateSet('paid_at', '30/12/2025 17:15', $lastYear);
    }

    public function test_owing_balances_are_collected_from_caja_with_who_owes_most_first(): void
    {
        $owes = Patient::factory()->create();
        $settled = Patient::factory()->create();
        Charge::factory()->for($owes)->create(['amount' => 150]);
        Charge::factory()->for($settled)->create(['amount' => 80]);
        Payment::factory()->for($settled)->create(['amount' => 80]);
        $this->actingAs(User::factory()->reception()->create());

        $owesMore = Patient::factory()->create();
        Charge::factory()->for($owesMore)->create(['amount' => 400]);

        // En Caja › Saldos pendientes: primero quien más debe, con el total arriba; se cobra desde la misma lista.
        Livewire::test(OwingBalances::class)
            ->assertCanSeeTableRecords([$owesMore, $owes], inOrder: true)
            ->assertCanNotSeeTableRecords([$settled])
            ->assertSee(['S/ 150.00', 'Por cobrar: S/ 550.00 entre 2 pacientes'])
            ->callAction(TestAction::make('collect')->table($owes), ['method' => PaymentMethod::Cash->value])
            ->assertHasNoErrors()
            ->assertNotified('Pago registrado')
            ->assertCanNotSeeTableRecords([$owes]);

        $this->assertSame(0.0, $owes->balance());

        // La lista de pacientes muestra el saldo, sin una pestaña aparte.
        Livewire::test(ListPatients::class)
            ->assertSee('Debe S/ 400.00')
            ->assertDontSee('Con saldo pendiente');

        $this->actingAs($this->doctorOffering(Treatment::factory()->create())->user);

        Livewire::test(ListPatients::class)->assertTableColumnHidden('balance_due');
        $this->get(OwingBalances::getUrl())->assertForbidden();
    }

    public function test_the_installment_due_today_shows_on_the_appointment_and_is_charged_at_checkout(): void
    {
        Storage::fake(ClinicalFileService::DISK);
        $brackets = Treatment::factory()->create(['name' => 'Colocación de brackets']);
        $control = Treatment::factory()->create(['name' => 'Control de ortodoncia', 'price_from' => 150]);
        $doctor = $this->doctorOffering($brackets, $control);
        $patient = Patient::factory()->create();
        $reception = User::factory()->reception()->create();

        // Hace un mes aceptó la ortodoncia en cuotas y pagó la inicial.
        $this->travelTo(CarbonImmutable::parse('2026-10-02 09:00', 'America/Lima'));
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'title' => 'Ortodoncia', 'status' => TreatmentPlanStatus::Proposed]);
        $plan->items()->create(['treatment_id' => $brackets->id, 'description' => 'Colocación de brackets', 'unit_price' => 1800]);
        $controls = $plan->items()->create(['treatment_id' => $control->id, 'description' => 'Control de ortodoncia', 'unit_price' => 200, 'sessions_planned' => 2]);
        app(TreatmentPlanService::class)->sign(
            $plan->load('items'),
            ['payment_mode' => 'installments', 'down_payment' => 500, 'installments' => 3, 'first_due_on' => '2026-11-02'],
            ['signer_name' => $patient->full_name, 'signer_relationship' => PatientRelationship::Self],
            $this->signature(),
            $doctor->user,
        );
        Payment::factory()->for($patient)->create(['amount' => 500]);

        // Hoy vence la cuota 1 y viene a su control: al abrir la cita, recepción ve qué cobrar.
        $this->freezeMonday();
        $appointment = Appointment::factory()->confirmed()->for($patient)->for($doctor)->for($control)->at('2026-11-02 10:00')->create(['treatment_plan_item_id' => $controls->id]);
        $this->actingAs($reception);

        Livewire::test(ViewAppointment::class, ['record' => $appointment->getKey()])
            ->assertSee(['Para cobrar ese día', 'Saldo pendiente (Ortodoncia · cuota 1 de 3): S/ 500.00', 'Control de ortodoncia · sesión 1 de 2 · incluida en el plan (en cuotas)']);

        // El control ya está en el plan: no se carga aparte; se cobra la cuota.
        $appointment->update(['status' => AppointmentStatus::Completed]);
        app(BillingService::class)->chargeAppointment($appointment, $reception);
        Repeater::fake();

        Livewire::test(TodayAppointments::class)
            ->mountAction(TestAction::make('checkout')->table($appointment))
            ->assertSchemaStateSet(['lines' => [], 'previous' => 500.0, 'amount' => 500.0])
            ->assertMountedActionModalSee(['Total a pagar', 'S/ 500.00', 'Incluye el saldo anterior de S/ 500.00 (Ortodoncia · cuota 1 de 3 S/ 500.00)'])
            ->fillForm(['reference' => '8812'])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Pago registrado');

        $this->assertSame(['Ortodoncia · inicial', 'Ortodoncia · cuota 1 de 3'], $patient->charges()->orderBy('id')->pluck('description')->all());
        $this->assertSame(0.0, $patient->balance());
        $this->assertSame(['paid', 'paid', 'upcoming', 'upcoming'], array_column(app(BillingService::class)->installmentStates($plan->fresh()), 'state'));
    }

    private function signature(): string
    {
        $image = imagecreatetruecolor(300, 120);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 20, 80, 280, 40, imagecolorallocate($image, 15, 23, 42));
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }
}
