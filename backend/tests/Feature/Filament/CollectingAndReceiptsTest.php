<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Enums\MessageStatus;
use App\Enums\PaymentMethod;
use App\Enums\PlanPaymentMode;
use App\Enums\TreatmentPlanStatus;
use App\Filament\Pages\CashClosing;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\Widgets\CashClosingCheck;
use App\Filament\Widgets\TodayStats;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\DailyClosing;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Billing\PaymentReceipt;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

class CollectingAndReceiptsTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    public function test_a_payment_without_a_visit_counts_for_the_doctor_of_what_it_covers_first(): void
    {
        $this->freezeMonday();
        $treatment = Treatment::factory()->create(['name' => 'Endodoncia']);
        $doctor = $this->doctorOffering($treatment);
        $patient = Patient::factory()->create();
        Charge::factory()->for($patient)->create(['description' => 'Endodoncia · sesión 1 de 2', 'amount' => 250, 'doctor_id' => $doctor->id, 'treatment_id' => $treatment->id, 'charged_at' => now()->subDays(3)]);
        Charge::factory()->for($patient)->create(['description' => 'Radiografía', 'amount' => 40, 'charged_at' => now()->subDay()]);
        $this->actingAs(User::factory()->reception()->create());

        // Desde "Hoy", el mismo cobro que en Caja y en la ficha.
        Livewire::test(Dashboard::class)
            ->mountAction('registerPayment')
            ->setActionData(['patient_id' => $patient->id])
            ->assertActionDataSet(['amount' => 290.0])
            ->setActionData(['amount' => 200, 'method' => PaymentMethod::Yape->value, 'reference' => '0081'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Pago registrado');

        $payment = Payment::sole();
        $this->assertSame([$doctor->id, $treatment->id], [$payment->doctor_id, $payment->treatment_id]);
        $this->assertSame(90.0, $patient->balance());
    }

    public function test_collecting_charges_the_installment_that_fell_due_even_if_the_morning_task_did_not_run(): void
    {
        $this->freezeMonday();
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'doctor_id' => Doctor::factory()->create()->id, 'title' => 'Blanqueamiento', 'status' => TreatmentPlanStatus::Accepted, 'payment_mode' => PlanPaymentMode::Installments, 'accepted_at' => now()->subWeek()]);
        $plan->installments()->create(['number' => 1, 'due_on' => '2026-11-02', 'amount' => 225]);
        $plan->installments()->create(['number' => 2, 'due_on' => '2026-12-02', 'amount' => 225]);
        $this->actingAs(User::factory()->reception()->create());

        // Sin cargarla, decía "Está al día" y no proponía nada.
        Livewire::test(Dashboard::class)
            ->mountAction('registerPayment')
            ->setActionData(['patient_id' => $patient->id])
            ->assertActionDataSet(['amount' => 225.0])
            ->assertMountedActionModalSee(['Debe S/ 225.00: Blanqueamiento · cuota 1 de 2 S/ 225.00 (2/11).', 'Próxima cuota: S/ 225.00, vence el miércoles 2 de diciembre.'])
            ->assertMountedActionModalDontSee('Cuota vencida');
    }

    public function test_collecting_says_since_when_an_installment_is_late_and_when_the_next_one_falls_due(): void
    {
        $this->freezeMonday();
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'doctor_id' => Doctor::factory()->create()->id, 'title' => 'Ortodoncia', 'status' => TreatmentPlanStatus::Accepted, 'payment_mode' => PlanPaymentMode::Installments, 'accepted_at' => now()->subMonths(2)]);
        $plan->installments()->create(['number' => 1, 'due_on' => '2026-10-01', 'amount' => 250]);
        $plan->installments()->create(['number' => 2, 'due_on' => '2026-12-01', 'amount' => 250]);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(Dashboard::class)
            ->mountAction('registerPayment')
            ->setActionData(['patient_id' => $patient->id])
            ->assertActionDataSet(['amount' => 250.0])
            ->assertMountedActionModalSee('Cuota vencida desde el 1 de octubre. La próxima, S/ 250.00, vence el martes 1 de diciembre.');
    }

    public function test_collecting_from_someone_in_their_visit_today_says_that_todays_part_comes_at_the_end(): void
    {
        $this->freezeMonday();
        $treatment = Treatment::factory()->create(['name' => 'Endodoncia']);
        $doctor = $this->doctorOffering($treatment);
        $patient = Patient::factory()->create();
        Charge::factory()->for($patient)->create(['description' => 'Radiografía', 'amount' => 40, 'charged_at' => now()->subWeek()]);
        $visit = Appointment::factory()->for($doctor)->for($treatment)->for($patient)->at('2026-11-02 07:30')->create(['status' => AppointmentStatus::InProgress]);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(Dashboard::class)
            ->mountAction('registerPayment')
            ->setActionData(['patient_id' => $patient->id])
            ->assertMountedActionModalSee(['Lo están atendiendo: Endodoncia de las 07:30', 'Lo de hoy se carga al terminar y todavía no está en lo que debe.'])
            ->setActionData(['amount' => 250])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        // Si igual pagó por adelantado, el cobro de su cita al terminar ya lo descuenta: no se le cobra dos veces.
        $visit->update(['status' => AppointmentStatus::Completed]);
        Charge::factory()->for($patient)->create(['description' => 'Endodoncia', 'amount' => 210, 'appointment_id' => $visit->id, 'charged_at' => now()]);
        $this->assertSame(0.0, $patient->balance());
    }

    public function test_collecting_more_than_owed_or_into_a_closed_day_is_pointed_out(): void
    {
        $this->freezeMonday();
        $patient = Patient::factory()->create();
        Charge::factory()->for($patient)->create(['description' => 'Curación', 'amount' => 150, 'charged_at' => now()->subWeek()]);
        DailyClosing::factory()->create(['date' => '2026-10-31']);

        // Un cero de más: se ve antes de cobrar.
        $this->actingAs(User::factory()->reception()->create());
        Livewire::test(Dashboard::class)
            ->mountAction('registerPayment')
            ->setActionData(['patient_id' => $patient->id])
            ->assertMountedActionModalDontSee('más de lo que debe')
            ->setActionData(['amount' => 1500])
            ->assertMountedActionModalSee('Son S/ 1,350.00 más de lo que debe: quedan a favor.')
            ->setActionData(['amount' => 150])
            ->assertMountedActionModalDontSee('más de lo que debe');

        // La administradora sí puede cobrar en un día cerrado, pero se le dice que cambia su cierre.
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(Dashboard::class)
            ->mountAction('registerPayment')
            ->setActionData(['patient_id' => $patient->id])
            ->assertMountedActionModalDontSee('La caja de ese día ya se cerró')
            ->setActionData(['paid_at' => '2026-10-31 10:00'])
            ->assertMountedActionModalSee('La caja de ese día ya se cerró: este pago cambia su cierre.');
    }

    public function test_collecting_offers_first_who_was_charged_today_and_still_owes(): void
    {
        $this->freezeMonday();
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $doctor = $this->doctorOffering($treatment);
        $seen = fn (Patient $patient, string $at) => Appointment::factory()->for($doctor)->for($treatment)->for($patient)->at($at)->create(['status' => AppointmentStatus::Completed]);
        $owes = Patient::factory()->create(['first_name' => 'Olivia', 'last_name' => 'Arenas']);
        $seen($owes, '2026-11-02 08:00');
        Charge::factory()->for($owes)->create(['amount' => 120]);
        // Una urgencia sin cita también se cobra hoy: sale igual, después de quien más debe.
        $urgency = Patient::factory()->create(['first_name' => 'Luis', 'last_name' => 'Mamani']);
        Charge::factory()->for($urgency)->create(['amount' => 90]);
        // Quien se atendió hoy y ya pagó, y quien debe de otro día, no salen: a esos se les busca por nombre.
        $paid = Patient::factory()->create(['first_name' => 'Pedro', 'last_name' => 'Huamán']);
        $seen($paid, '2026-11-02 07:00');
        Charge::factory()->for($paid)->create(['amount' => 80]);
        Payment::factory()->for($paid)->create(['amount' => 80]);
        $earlier = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']);
        $seen($earlier, '2026-10-30 10:00');
        Charge::factory()->for($earlier)->create(['amount' => 60, 'charged_at' => '2026-10-30 10:40']);
        // Quienes están en la clínica y deben de antes salen después: a primera hora son los únicos.
        $inClinic = fn (string $firstName, AppointmentStatus $status, string $at, float $owed) => tap(Patient::factory()->create(['first_name' => $firstName, 'last_name' => 'Torres']), function (Patient $patient) use ($doctor, $treatment, $status, $at, $owed) {
            Appointment::factory()->for($doctor)->for($treatment)->for($patient)->at($at)->create(['status' => $status]);
            if ($owed > 0) {
                Charge::factory()->for($patient)->create(['amount' => $owed, 'charged_at' => '2026-10-20 10:00']);
            }
        });
        $inChair = $inClinic('Marta', AppointmentStatus::InProgress, '2026-11-02 07:30', 300);
        $waiting = $inClinic('Hugo', AppointmentStatus::CheckedIn, '2026-11-02 08:00', 45);
        $inClinic('Elena', AppointmentStatus::CheckedIn, '2026-11-02 08:15', 0);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(Dashboard::class)
            ->mountAction('registerPayment')
            ->assertFormFieldExists('patient_id', 'mountedActionSchema0', fn (Select $field) => $field->getOptions() === [
                $owes->id => 'Olivia Arenas · debe S/ 120.00',
                $urgency->id => 'Luis Mamani · debe S/ 90.00',
                $inChair->id => 'Marta Torres · en atención · debe S/ 300.00',
                $waiting->id => 'Hugo Torres · en sala · debe S/ 45.00',
            ])
            // A los demás se les sigue encontrando al escribir.
            ->assertFormFieldExists('patient_id', 'mountedActionSchema0', fn (Select $field) => array_keys($field->getSearchResults('Quispe')) === [$earlier->id])
            ->setActionData(['patient_id' => $owes->id])
            ->assertActionDataSet(['amount' => 120.0]);

        // Los de hoy son los mismos que cuenta "Cobrado hoy" y lista el cierre del día, a donde lleva ese número. Los que
        // están en la clínica no cuentan ahí: su cita sale en "Citas sin cerrar".
        Livewire::test(TodayStats::class)
            ->assertSee('Falta cobrar S/ 210.00 a 2 pacientes')
            ->assertSeeHtml('href="'.e(CashClosing::getUrl()).'"');
        Livewire::test(CashClosingCheck::class)
            ->assertSeeInOrder(['Se fueron debiendo', 'Olivia Arenas', 'S/ 120.00', 'Luis Mamani', 'S/ 90.00'])
            ->assertDontSee(['Pedro Huamán', 'Rosa Quispe']);

        // Si a quien está en sala ya se le cargó algo hoy, sale con los de hoy y se dice que sigue aquí.
        Charge::factory()->for($waiting)->create(['amount' => 30]);
        Livewire::test(Dashboard::class)
            ->mountAction('registerPayment')
            ->assertFormFieldExists('patient_id', 'mountedActionSchema0', fn (Select $field) => $field->getOptions() === [
                $owes->id => 'Olivia Arenas · debe S/ 120.00',
                $urgency->id => 'Luis Mamani · debe S/ 90.00',
                $waiting->id => 'Hugo Torres · en sala · debe S/ 75.00',
                $inChair->id => 'Marta Torres · en atención · debe S/ 300.00',
            ]);
    }

    public function test_the_receipt_says_what_was_paid_and_what_is_left(): void
    {
        $this->freezeMonday();
        $account = Account::factory()->create(['phone' => '+51987654321']);
        $patient = Patient::factory()->for($account)->create(['first_name' => 'Rosa', 'last_name' => 'Huamán', 'document_number' => '07654321']);
        Charge::factory()->for($patient)->create(['description' => 'Limpieza dental', 'amount' => 120, 'charged_at' => now()->subDays(10)]);
        Charge::factory()->for($patient)->create(['description' => 'Curación con resina', 'amount' => 150, 'charged_at' => now()->subDays(2)]);
        Payment::factory()->for($patient)->create(['amount' => 100, 'method' => PaymentMethod::Cash, 'paid_at' => now()->subDays(5)]);
        $payment = Payment::factory()->for($patient)->create(['amount' => 200, 'method' => PaymentMethod::Yape, 'reference' => '0081', 'paid_at' => now()]);
        $receipts = app(PaymentReceipt::class);

        // El pago anterior cubrió 100 de la limpieza: este termina la limpieza y cubre la curación.
        $this->assertSame([['description' => 'Limpieza dental', 'amount' => 20.0], ['description' => 'Curación con resina', 'amount' => 150.0], ['description' => 'A cuenta', 'amount' => 30.0]], $receipts->lines($payment));
        $this->assertSame(-30.0, $receipts->balanceAfter($payment));

        $reception = User::factory()->reception()->create();
        $this->actingAs($reception);
        $url = Filament::getPanel('admin')->route('print.receipt', ['payment' => $payment]);

        $this->get($url)
            ->assertOk()
            ->assertSee(['Constancia de pago', 'N.º '.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT), 'Rosa Huamán', 'Curación con resina', 'Total pagado', 'S/ 200.00', 'Yape · 0081', 'Saldo después de este pago: A favor S/ 30.00', 'Enviar por WhatsApp', 'no reemplaza la boleta ni la factura']);

        $location = $this->post(Filament::getPanel('admin')->route('print.receipt.whatsapp', ['payment' => $payment]))
            ->assertRedirectContains('https://wa.me/51987654321')
            ->headers->get('Location');
        $this->assertStringContainsString(rawurlencode('Recibimos tu pago de S/ 200.00 (yape) el '.now()->format('d/m/Y')), $location);
        $this->assertStringContainsString(rawurlencode('Saldo: A favor S/ 30.00'), $location);
        $this->assertSame(MessageStatus::Sent, WhatsappMessage::sole()->status);

        // Anulado: la hoja lo dice y ya no se envía.
        $payment->update(['voided_at' => now(), 'voided_by_user_id' => $reception->id, 'void_reason' => 'Monto mal escrito']);
        $this->get($url)->assertOk()->assertSee(['ANULADO', 'Monto mal escrito'])->assertDontSee('Enviar por WhatsApp');

        // Los doctores no ven la caja.
        $this->actingAs($this->doctorOffering(Treatment::factory()->create())->user);
        $this->get($url)->assertForbidden();
    }

    public function test_the_receipt_counts_what_was_charged_while_the_window_was_open(): void
    {
        $this->freezeMonday();
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'doctor_id' => Doctor::factory()->create()->id, 'title' => 'Blanqueamiento', 'status' => TreatmentPlanStatus::Accepted, 'payment_mode' => PlanPaymentMode::Installments, 'accepted_at' => now()->subWeek()]);
        $plan->installments()->create(['number' => 1, 'due_on' => '2026-11-02', 'amount' => 225]);
        $this->actingAs(User::factory()->reception()->create());

        // Se abre la ventana a las 8:00 y se elige al paciente un rato después: ahí entra su cuota de hoy.
        $window = Livewire::test(Dashboard::class)->mountAction('registerPayment');
        $this->travel(90)->seconds();
        $window->setActionData(['patient_id' => $patient->id])
            ->assertActionDataSet(['amount' => 225.0])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        // Antes el pago quedaba a las 8:00, antes de la cuota, y la constancia decía "A favor S/ 225.00".
        $payment = Payment::sole();
        $this->assertSame('2026-11-02 08:01:30', $payment->paid_at->format('Y-m-d H:i:s'));
        $this->assertSame(0.0, app(PaymentReceipt::class)->balanceAfter($payment));

        // Una fecha elegida a mano se respeta.
        Charge::factory()->for($patient)->create(['amount' => 50, 'charged_at' => now()->subDays(3)]);
        Livewire::test(Dashboard::class)
            ->mountAction('registerPayment')
            ->setActionData(['patient_id' => $patient->id, 'paid_at' => '2026-11-02 07:15'])
            ->callMountedAction()
            ->assertHasNoActionErrors();
        $this->assertSame('2026-11-02 07:15', Payment::query()->latest('id')->first()->paid_at->format('Y-m-d H:i'));
    }

    public function test_caja_and_the_patient_file_link_each_payment_to_its_receipt(): void
    {
        $payment = Payment::factory()->create(['amount' => 80]);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(ManagePayments::class)
            ->assertActionHasUrl(TestAction::make('receipt')->table($payment), Filament::getPanel('admin')->route('print.receipt', ['payment' => $payment]))
            ->mountAction('registerPayment')
            ->assertActionDataSet(['patient_id' => null, 'amount' => null, 'method' => PaymentMethod::Cash]);
    }
}
