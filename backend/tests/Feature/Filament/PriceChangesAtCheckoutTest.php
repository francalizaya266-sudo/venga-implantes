<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\Patients\RelationManagers\ChargesRelationManager;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\Widgets\CashClosingAdjustments;
use App\Filament\Widgets\TodayAppointments;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Charge;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Billing\PriceAdjustments;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/**
 * Al cobrar, un precio distinto al que traía el cargo, un descuento o un concepto agregado a mano piden motivo. Queda
 * en el cierre del día, en la constancia y en el historial de cambios, y quien administra recibe una alerta. Antes una
 * limpieza de S/ 120 se cobraba a S/ 70 sin motivo y el cierre decía "Se cobró todo".
 */
class PriceChangesAtCheckoutTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private User $reception;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->reception = User::factory()->reception()->create(['name' => 'Rosa Pérez']);
        $this->admin = User::factory()->admin()->create(['name' => 'Carla Vega']);
        Repeater::fake();
    }

    /** Una limpieza de S/ 120 ya atendida, con su cargo armado por el sistema. */
    private function completedCleaning(?float $dentistPrice = null): Appointment
    {
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'price_from' => 120]);
        $patient = Patient::factory()->create(['first_name' => 'Ana', 'last_name' => 'Flores']);
        $appointment = Appointment::factory()->for($patient)->for($this->doctorOffering($cleaning))->for($cleaning)
            ->at('2026-11-02 10:00')->create(['status' => AppointmentStatus::Completed]);
        app(BillingService::class)->chargeAppointment($appointment, $this->reception);

        if ($dentistPrice !== null) {
            // El doctor fijó otro precio al terminar: así llega a caja.
            Charge::sole()->update(['amount' => $dentistPrice, 'suggested_amount' => 120]);
        }

        return $appointment;
    }

    /** @param  array<string, mixed>  $data */
    private function checkout(Appointment $appointment, array $data): mixed
    {
        $this->actingAs($this->reception);

        return Livewire::test(TodayAppointments::class)
            ->mountAction(TestAction::make('checkout')->table($appointment))
            ->fillForm($data)
            ->callMountedAction();
    }

    /** @return list<string> */
    private function adminAlerts(): array
    {
        return $this->admin->notifications()->get()->map(fn ($alert) => $alert->data['title'].' | '.$alert->data['body'])->all();
    }

    public function test_a_lower_price_asks_why_and_the_closing_the_receipt_and_the_history_show_it(): void
    {
        $appointment = $this->completedCleaning();
        $charge = Charge::sole();
        $lines = [['id' => $charge->id, 'description' => 'Limpieza dental', 'amount' => 70, 'discount' => 0]];

        $this->checkout($appointment, ['lines' => $lines, 'amount' => 70, 'method' => PaymentMethod::Cash->value])
            ->assertHasFormErrors(['adjustment_reason' => 'required']);
        $this->assertSame('120.00', $charge->fresh()->amount);
        $this->assertSame(0, Payment::count());

        $this->checkout($appointment, ['lines' => $lines, 'adjustment_reason' => 'Paciente frecuente', 'amount' => 70, 'method' => PaymentMethod::Cash->value])
            ->assertHasNoFormErrors()
            ->assertNotified('Pago registrado');

        $charge->refresh();
        $this->assertSame(['70.00', '120.00', '0.00', 'Paciente frecuente'], [$charge->amount, $charge->original_amount, $charge->original_discount, $charge->adjustment_reason]);
        $this->assertTrue($charge->adjustedBy->is($this->reception));

        // El cierre del día: un bloque propio con cuántos, por cuánto y el detalle.
        $this->actingAs($this->admin);
        Livewire::test(CashClosingAdjustments::class, ['pageFilters' => ['date' => '2026-11-02']])
            ->assertSee(['Precios cambiados y descuentos de hoy', '1 cambio', 'S/ 50.00', 'Ana Flores · Limpieza dental', 'Precio de S/ 120.00 a S/ 70.00', 'Motivo: Paciente frecuente', 'Rosa Pérez']);
        $this->get(Filament::getPanel('admin')->route('cash-closing.print', ['date' => '2026-11-02']))
            ->assertOk()
            ->assertSee(['Precios cambiados y descuentos · 1 cambio · S/ 50.00 menos', 'Precio de S/ 120.00 a S/ 70.00']);

        // La constancia dice a qué precio era y por qué se cobró menos.
        $this->get(Filament::getPanel('admin')->route('print.receipt', ['payment' => Payment::sole()]))
            ->assertOk()
            ->assertSee('Precio S/ 120.00, se cobró a S/ 70.00 (Paciente frecuente)');

        // Quien administra lo sabe al momento; a quien cobró no hace falta decírselo.
        $this->assertSame(['Cambio al cobrar a Ana Flores | Limpieza dental: precio de S/ 120.00 a S/ 70.00.<br>Motivo: Paciente frecuente. Lo hizo Rosa Pérez.'], $this->adminAlerts());
        $this->assertFalse($this->reception->notifications()->get()->contains(fn ($alert) => str_starts_with($alert->data['title'], 'Cambio al cobrar')));

        // Y queda en el historial de cambios, sin los cargos que arma el sistema.
        $change = AuditLog::query()->where('auditable_type', Charge::class)->where('event', 'updated')->latest('id')->firstOrFail();
        Livewire::test(ManageAuditLogs::class)
            ->assertCanSeeTableRecords([$change])
            ->assertCanNotSeeTableRecords(AuditLog::query()->where('auditable_type', Charge::class)->where('event', 'created')->get())
            ->assertTableColumnStateSet('section', 'Cobros', $change)
            ->assertTableColumnStateSet('subject', 'Limpieza dental · Ana Flores', $change)
            ->assertTableColumnStateSet('summary', 'Precio: S/ 120.00 → S/ 70.00 · Motivo: Paciente frecuente', $change)
            ->mountAction(TestAction::make('detail')->table($change))
            ->assertMountedActionModalSee(['Limpieza dental · Ana Flores', 'Cobros', 'Rosa Pérez', 'S/ 120.00', 'S/ 70.00', 'Paciente frecuente']);
    }

    public function test_a_discount_or_a_concept_added_by_hand_also_asks_why(): void
    {
        $appointment = $this->completedCleaning();
        $charge = Charge::sole();

        $this->checkout($appointment, [
            'lines' => [
                ['id' => $charge->id, 'description' => 'Limpieza dental', 'amount' => 120, 'discount' => 20],
                ['id' => null, 'description' => 'Flúor', 'amount' => 30, 'discount' => 0],
            ],
            'amount' => 130,
            'method' => PaymentMethod::Cash->value,
        ])->assertHasFormErrors(['adjustment_reason' => 'required']);

        $this->checkout($appointment, [
            'lines' => [
                ['id' => $charge->id, 'description' => 'Limpieza dental', 'amount' => 120, 'discount' => 20],
                ['id' => null, 'description' => 'Flúor', 'amount' => 30, 'discount' => 0],
            ],
            'adjustment_reason' => 'Campaña del colegio',
            'amount' => 130,
            'method' => PaymentMethod::Cash->value,
        ])->assertHasNoFormErrors();

        $this->assertSame(['20.00', 'Campaña del colegio'], [$charge->fresh()->discount, $charge->fresh()->discount_reason]);
        $rows = app(PriceAdjustments::class)->ofDay(today());
        $this->assertSame([PriceAdjustments::DISCOUNT, PriceAdjustments::ADDED], $rows->pluck('kind')->all());
        // Lo agregado a mano no rebaja ni sube un precio: solo se lista.
        $this->assertSame(['count' => 2, 'lowered' => 20.0, 'raised' => 0.0], PriceAdjustments::totals($rows));
        $this->assertSame(['Descuento de S/ 20.00 sobre S/ 120.00', 'Agregado a mano: S/ 30.00'], $rows->map(fn (array $row) => PriceAdjustments::change($row['charge']))->all());
        // El concepto agregado suma: la alerta es por el descuento.
        $this->assertSame(['Cambio al cobrar a Ana Flores | Limpieza dental: descuento de S/ 20.00 sobre S/ 120.00.<br>Motivo: Campaña del colegio. Lo hizo Rosa Pérez.'], $this->adminAlerts());
    }

    public function test_a_price_set_by_the_dentist_arrives_as_it_is_and_needs_no_reason(): void
    {
        $appointment = $this->completedCleaning(dentistPrice: 100);
        $charge = Charge::sole();

        $this->checkout($appointment, [
            'lines' => [['id' => $charge->id, 'description' => 'Limpieza dental', 'amount' => 100, 'discount' => 0]],
            'amount' => 100,
            'method' => PaymentMethod::Cash->value,
        ])->assertHasNoFormErrors()->assertNotified('Pago registrado');

        $this->assertNull($charge->fresh()->adjusted_at);
        $this->assertTrue(app(PriceAdjustments::class)->ofDay(today())->isEmpty());
        $this->assertSame([], $this->adminAlerts());

        $this->actingAs($this->admin);
        Livewire::test(CashClosingAdjustments::class, ['pageFilters' => ['date' => '2026-11-02']])
            ->assertSee('Nadie cambió precios, puso descuentos ni anuló cargos.');
    }

    public function test_putting_the_price_back_as_it_came_is_no_longer_a_change(): void
    {
        $appointment = $this->completedCleaning();
        $charge = Charge::sole();
        $billing = app(BillingService::class);

        $billing->reviseCharges($appointment, [['id' => $charge->id, 'description' => 'Limpieza dental', 'amount' => 70, 'discount' => 0]], $this->reception, 'Paciente frecuente');
        $billing->reviseCharges($appointment, [['id' => $charge->id, 'description' => 'Limpieza dental', 'amount' => 90, 'discount' => 0]], $this->reception, 'Se acordó S/ 90');
        $this->assertSame(['90.00', '120.00', 'Se acordó S/ 90'], [$charge->fresh()->amount, $charge->fresh()->original_amount, $charge->fresh()->adjustment_reason]);

        $billing->reviseCharges($appointment, [['id' => $charge->id, 'description' => 'Limpieza dental', 'amount' => 120, 'discount' => 0]], $this->reception);
        $this->assertNull($charge->fresh()->adjusted_at);
        $this->assertTrue($billing->reviseCharges($appointment, [['id' => $charge->id, 'description' => 'Limpieza dental', 'amount' => 120, 'discount' => 0]], $this->reception)->isEmpty());
    }

    public function test_voiding_a_charge_or_a_payment_and_a_refund_alert_whoever_administers(): void
    {
        $this->completedCleaning();
        $charge = Charge::sole();
        $patient = $charge->patient;
        $payment = Payment::factory()->for($patient)->create(['amount' => 50, 'method' => PaymentMethod::Yape, 'reference' => '4512', 'paid_at' => '2026-11-02 10:40', 'received_by_user_id' => $this->reception->id]);
        $this->actingAs($this->reception);

        Livewire::test(ManagePayments::class)
            ->callAction(TestAction::make('void')->table($payment), ['reason' => 'Monto mal escrito'])
            ->assertHasNoFormErrors();

        Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $patient, 'pageClass' => ViewPatient::class])
            ->callAction(TestAction::make('void')->table($charge), ['reason' => 'Se atendió gratis'])
            ->assertHasNoFormErrors();

        app(BillingService::class)->registerRefund($patient->fresh(), 30, PaymentMethod::Cash, 'Pagó de más', $this->reception);

        // Las tres llegan en el mismo segundo: en la campana no tienen un orden fijo (en MySQL y MariaDB sale otro).
        $this->assertEqualsCanonicalizing([
            'Se anuló un pago de Ana Flores | S/ 50.00 con Yape (lunes 2 de noviembre, 10:40 a. m.). Motivo: Monto mal escrito. Lo anuló Rosa Pérez.',
            'Se anuló un cargo de Ana Flores | Limpieza dental de S/ 120.00. Motivo: Se atendió gratis. Lo anuló Rosa Pérez.',
            'Se devolvió dinero a Ana Flores | S/ 30.00 con Efectivo. Motivo: Pagó de más. Lo devolvió Rosa Pérez.',
        ], array_map(fn (string $alert) => str_replace("\u{00A0}", ' ', $alert), $this->adminAlerts()));
        $this->assertSame(0, $this->reception->notifications()->where('data->title', 'like', 'Se anuló%')->count());

        // El cargo anulado a mano baja lo que se cobra igual que un precio cambiado: sale en el cierre.
        $rows = app(PriceAdjustments::class)->ofDay(today());
        $this->assertSame([[PriceAdjustments::REMOVED, 'Se atendió gratis', 'Rosa Pérez', 120.0]], $rows->map(fn (array $row) => [$row['kind'], $row['reason'], $row['by'], $row['lowered']])->all());
        $this->assertSame('Se anuló el cargo: era S/ 120.00', PriceAdjustments::change($rows->sole()['charge']));
        $this->assertTrue(app(PriceAdjustments::class)->ofDay(today(), $this->admin->id)->isEmpty());
    }
}
