<?php

namespace Tests\Feature\Services\Database;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PlanPaymentMode;
use App\Enums\StockMovementType;
use App\Enums\SupplyCategory;
use App\Enums\TreatmentPlanStatus;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Charge;
use App\Models\DailyClosing;
use App\Models\Doctor;
use App\Models\Expense;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Supply;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanInstallment;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Billing\CashClosing;
use App\Services\Database\DatabaseObjects;
use App\Services\Reports\EarningsReport;
use App\Services\Reports\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Las vistas, funciones, procedimientos y triggers de database/sql/mysql dan lo mismo que la aplicación con los
 * mismos datos. Solo corren con MySQL (el job "mysql" de CI): `php artisan test --group=mysql`.
 * Sin viajar en el tiempo: "hoy" lo calcula MySQL con su reloj, que es el mismo de PHP.
 */
#[Group('mysql')]
class MysqlDatabaseObjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Las vistas, funciones, procedimientos y triggers solo existen en MySQL.');
        }
    }

    public function test_the_migrations_leave_every_object_in_place(): void
    {
        $this->assertSame([], (new DatabaseObjects)->missing());
    }

    public function test_the_names_match_the_ones_the_clinic_sees(): void
    {
        foreach (AppointmentStatus::cases() as $status) {
            $this->assertSame($status->getLabel(), $this->value('select fn_estado_cita(?)', [$status->value]));
        }

        foreach (PaymentMethod::cases() as $method) {
            $this->assertSame($method->getLabel(), $this->value('select fn_medio_pago(?)', [$method->value]));
        }

        foreach (SupplyCategory::cases() as $category) {
            $this->assertSame($category->getLabel(), $this->value('select fn_categoria_material(?)', [$category->value]));
        }
    }

    public function test_today_is_the_clinic_day_in_lima(): void
    {
        $this->assertSame(today()->toDateString(), $this->value('select fn_hoy_lima()'));
        $this->assertLessThan(120, abs(CarbonImmutable::parse($this->value('select fn_ahora_lima()'))->diffInSeconds(now())));
    }

    public function test_the_balance_is_the_same_as_in_the_patient_file(): void
    {
        $patient = Patient::factory()->create();
        Charge::factory()->for($patient)->create(['amount' => 300, 'discount' => 50]);
        Charge::factory()->for($patient)->create(['amount' => 120, 'voided_at' => now()]);
        Payment::factory()->for($patient)->create(['amount' => 100]);
        Payment::factory()->for($patient)->create(['amount' => 999, 'voided_at' => now()]);
        $inCredit = Patient::factory()->create();
        Payment::factory()->for($inCredit)->create(['amount' => 80]);
        $deleted = Patient::factory()->create();
        Charge::factory()->for($deleted)->create(['amount' => 70]);
        $deleted->delete();

        $this->assertSame(150.0, $patient->balance());
        $this->assertSame($patient->balance(), (float) $this->value('select fn_saldo_paciente(?)', [$patient->id]));
        $this->assertSame(-80.0, (float) $this->value('select fn_saldo_paciente(?)', [$inCredit->id]));

        $view = DB::table('v_saldo_paciente')->pluck('saldo', 'paciente_id')->map(fn ($balance) => (float) $balance)->all();
        $this->assertSame([$patient->id => 150.0, $inCredit->id => -80.0], $view);

        // Lo que la página "Por cobrar" suma: solo quien debe.
        $this->assertSame(
            Patient::query()->owing()->withBalance()->pluck('balance_due')->map(fn ($due) => (float) $due)->all(),
            DB::table('v_saldo_paciente')->where('saldo', '>', 0)->pluck('saldo')->map(fn ($due) => (float) $due)->all(),
        );
    }

    public function test_payments_cover_the_oldest_charges_first(): void
    {
        $patient = Patient::factory()->create();
        $old = Charge::factory()->for($patient)->create(['amount' => 200, 'charged_at' => now()->subDays(20)]);
        $sameDay = Charge::factory()->for($patient)->create(['amount' => 150, 'discount' => 30, 'charged_at' => now()->subDays(5)]);
        Charge::factory()->for($patient)->create(['amount' => 40, 'discount' => 60, 'charged_at' => now()->subDays(4)]);
        $latest = Charge::factory()->for($patient)->create(['amount' => 90, 'charged_at' => now()->subDay()]);
        Charge::factory()->for($patient)->create(['amount' => 500, 'charged_at' => now()->subDays(30), 'voided_at' => now()]);
        Payment::factory()->for($patient)->create(['amount' => 250]);

        // Quien devolvió más de lo que pagó: su primer cargo carga la diferencia, como en PHP.
        $refunded = Patient::factory()->create();
        $first = Charge::factory()->for($refunded)->create(['amount' => 100, 'charged_at' => now()->subDays(3)]);
        Payment::factory()->for($refunded)->create(['amount' => -20]);

        $billing = app(BillingService::class);

        foreach ([$patient, $refunded] as $who) {
            $expected = $billing->unpaidCharges($who)->mapWithKeys(fn (array $row) => [$row['charge']->id => $row['due']])->all();
            $view = DB::table('v_cargos_pendientes')->where('paciente_id', $who->id)->orderBy('cargado_el')->orderBy('cargo_id')
                ->pluck('por_pagar', 'cargo_id')->map(fn ($due) => (float) $due)->all();

            $this->assertSame($expected, $view);
        }

        $this->assertSame([$sameDay->id => 70.0, $latest->id => 90.0], DB::table('v_cargos_pendientes')->where('paciente_id', $patient->id)
            ->pluck('por_pagar', 'cargo_id')->map(fn ($due) => (float) $due)->all());
        $this->assertNotContains($old->id, DB::table('v_cargos_pendientes')->pluck('cargo_id')->all());
        $this->assertSame(120.0, (float) DB::table('v_cargos_pendientes')->where('cargo_id', $first->id)->value('por_pagar'));
    }

    public function test_overdue_debt_lists_the_late_installments_like_the_installments_page(): void
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();
        $today = CarbonImmutable::today();
        $plan = $this->planInInstallments($patient, $doctor, TreatmentPlanStatus::InProgress);
        $down = $this->installment($plan, 0, $today->subDays(40), 300, charged: true);
        $first = $this->installment($plan, 1, $today->subDays(10), 250, charged: true);
        $second = $this->installment($plan, 2, $today->subDay(), 250, discount: 20);
        $this->installment($plan, 3, $today, 250);
        $this->installment($plan, 4, $today->addDays(20), 250);
        // La inicial se pagó entera y de la cuota 1 se pagaron 100.
        Payment::factory()->for($patient)->create(['amount' => 400]);

        $cancelled = $this->planInInstallments($patient, $doctor, TreatmentPlanStatus::Cancelled);
        $this->installment($cancelled, 1, $today->subDays(15), 500);
        $deleted = $this->planInInstallments(Patient::factory()->create(), $doctor, TreatmentPlanStatus::InProgress);
        $this->installment($deleted, 1, $today->subDays(15), 500);
        $deleted->delete();

        $view = DB::table('v_deuda_vencida')->orderBy('vencio')->get()->keyBy('cuota_id');

        $this->assertSame(app(BillingService::class)->installmentsIn(['late']), $view->map(fn (object $row) => (float) $row->por_pagar)->all());
        $this->assertSame([$first->id => 150.0, $second->id => 230.0], $view->map(fn (object $row) => (float) $row->por_pagar)->all());
        $this->assertNotContains($down->id, $view->keys()->all());
        $this->assertSame([$first->fresh()->label(), $second->fresh()->label()], $view->pluck('cuota')->all());
        $this->assertSame(['Cuota 1 de 4', 'Cuota 2 de 4'], $view->pluck('cuota')->all());
        $this->assertSame([10, 1], $view->pluck('dias_de_atraso')->map(fn ($days) => (int) $days)->all());
    }

    public function test_the_day_schedule_has_today_appointments_like_the_dashboard(): void
    {
        $morning = Appointment::factory()->confirmed()->at(today()->setTime(9, 0)->toDateTimeString())->create();
        $afternoon = Appointment::factory()->at(today()->setTime(16, 30)->toDateTimeString())->create();
        Appointment::factory()->cancelled()->at(today()->setTime(11, 0)->toDateTimeString())->create();
        Appointment::factory()->at(today()->addDay()->setTime(9, 0)->toDateTimeString())->create();
        Appointment::factory()->at(today()->subDay()->setTime(9, 0)->toDateTimeString())->create();
        Appointment::factory()->at(today()->setTime(12, 0)->toDateTimeString())->create()->delete();
        Charge::factory()->for($afternoon->patient)->create(['amount' => 180]);

        $dashboard = Appointment::query()->whereDate('starts_at', today())->where('status', '!=', AppointmentStatus::Cancelled)->orderBy('starts_at')->pluck('id')->all();
        $view = DB::table('v_agenda_del_dia')->orderBy('inicio')->get();

        $this->assertSame([$morning->id, $afternoon->id], $dashboard);
        $this->assertSame($dashboard, $view->pluck('cita_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(['09:00', '16:30'], $view->pluck('hora')->all());
        $this->assertSame(['Confirmada', 'Pendiente'], $view->pluck('estado')->all());
        $this->assertSame([0.0, 180.0], $view->pluck('saldo_paciente')->map(fn ($balance) => (float) $balance)->all());
    }

    public function test_materials_consumption_costs_the_same_as_the_earnings_report(): void
    {
        $resin = Supply::factory()->create(['category' => SupplyCategory::Restorative, 'unit_cost' => 2.5]);
        $september = '2026-09-15 10:00:00';
        $this->movement($resin, StockMovementType::Receipt, 50, $september);
        $this->movement($resin, StockMovementType::Use, -4, $september);
        $this->movement($resin, StockMovementType::Return, 1, $september);
        $this->movement($resin, StockMovementType::WriteOff, -2, $september);
        $this->movement($resin, StockMovementType::Adjustment, -1, $september);
        $this->movement($resin, StockMovementType::Use, -3, '2026-10-02 09:00:00');

        $row = DB::table('v_consumo_materiales')->where('mes', '2026-09')->sole();
        $report = new EarningsReport(new ReportPeriod(CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30')));

        $this->assertSame(['Resinas y restauración', 3.0, 2.0, 12.5, 3], [$row->categoria, (float) $row->usado, (float) $row->dado_de_baja, (float) $row->costo, (int) $row->movimientos]);
        $this->assertSame($report->materialsCost(), (float) $row->costo);
    }

    public function test_the_cash_closing_procedure_gives_the_same_figures_as_the_cash_closing_screen(): void
    {
        $day = CarbonImmutable::parse('2026-10-14');
        $rosa = User::factory()->create(['name' => 'Rosa']);
        $luis = User::factory()->create(['name' => 'Luis']);

        // Pagó parte de lo de ese día: falta 100.
        $ana = Patient::factory()->create();
        Charge::factory()->for($ana)->create(['amount' => 300, 'charged_at' => $day->setTime(10, 0)]);
        $this->payment($ana, 200, PaymentMethod::Cash, $day->setTime(10, 40), $rosa);
        // Con descuento y un cargo de días después: de ese día faltan 40.
        $beto = Patient::factory()->create();
        Charge::factory()->for($beto)->create(['amount' => 150, 'discount' => 50, 'charged_at' => $day->setTime(11, 0)]);
        Charge::factory()->for($beto)->create(['amount' => 80, 'charged_at' => $day->addDays(2)->setTime(9, 0)]);
        $this->payment($beto, 60, PaymentMethod::Yape, $day->setTime(11, 30), $luis);
        // Debía de antes: lo que pagó ese día cubre lo viejo y lo de ese día queda pendiente.
        $carla = Patient::factory()->create();
        Charge::factory()->for($carla)->create(['amount' => 500, 'charged_at' => $day->subDays(5)]);
        Charge::factory()->for($carla)->create(['amount' => 70, 'charged_at' => $day->setTime(12, 0)]);
        $this->payment($carla, 70, PaymentMethod::Card, $day->setTime(12, 10), $rosa);
        // No suman: un pago anulado y uno de otro día.
        $this->payment($carla, 999, PaymentMethod::Cash, $day->setTime(13, 0), $rosa)->update(['voided_at' => now(), 'void_reason' => 'Se registró dos veces']);
        $this->payment($carla, 40, PaymentMethod::Cash, $day->subDay()->setTime(17, 0), $rosa);
        Expense::factory()->create(['spent_at' => $day->setTime(14, 0), 'amount' => 30, 'method' => PaymentMethod::Cash, 'paid_by_user_id' => $rosa->id]);
        Expense::factory()->create(['spent_at' => $day->setTime(15, 0), 'amount' => 45, 'method' => PaymentMethod::Card, 'paid_by_user_id' => $luis->id]);
        Expense::factory()->create(['spent_at' => $day->setTime(16, 0), 'amount' => 500, 'method' => PaymentMethod::Cash, 'voided_at' => now()]);
        DailyClosing::factory()->create(['date' => $day, 'opening_cash' => 100, 'expected_cash' => 270, 'counted_cash' => 265]);

        foreach ([null, $rosa->id] as $person) {
            $closing = new CashClosing($day, $person);
            [[$summary], $methods] = $this->callProcedure('call sp_cierre_caja(?, ?)', [$day->toDateString(), $person]);

            $this->assertSame(
                [$closing->payments()->count(), $closing->total(), $closing->cash(), $closing->cashExpenses(), $closing->cashInDrawer(), $closing->pendingTotal(), $closing->expected(), $closing->voided()->count()],
                [(int) $summary['pagos'], (float) $summary['cobrado'], (float) $summary['efectivo'], (float) $summary['gastos_en_efectivo'], (float) $summary['efectivo_en_caja'], (float) $summary['pendiente'], (float) $summary['esperado'], (int) $summary['anulados']],
            );
            $this->assertSame(
                collect($closing->tables()[0]['rows'])->map(fn (array $row) => [$row[0], $row[1], $row[2]])->all(),
                collect($methods)->map(fn (array $row) => [$row['medio_de_pago'], (int) $row['pagos'], (float) $row['cobrado']])->all(),
            );
        }

        // Todo el día: 330 cobrados (200 en efectivo), 30 de gastos en efectivo y 210 pendientes (100 + 40 + 70).
        [[$summary]] = $this->callProcedure('call sp_cierre_caja(?, ?)', [$day->toDateString(), null]);
        $this->assertSame([330.0, 170.0, 210.0, 540.0, -5.0], [(float) $summary['cobrado'], (float) $summary['efectivo_en_caja'], (float) $summary['pendiente'], (float) $summary['esperado'], (float) $summary['diferencia']]);
    }

    public function test_the_monthly_earnings_procedure_gives_the_same_figures_as_the_earnings_page(): void
    {
        $vega = Doctor::factory()->create(['name' => 'Dr. Vega']);
        $rojas = Doctor::factory()->create(['name' => 'Dra. Rojas']);
        $patient = Patient::factory()->create();
        $october = CarbonImmutable::parse('2026-10-10 10:00');
        Charge::factory()->for($patient)->create(['doctor_id' => $vega->id, 'amount' => 1200, 'discount' => 200, 'charged_at' => $october]);
        Charge::factory()->for($patient)->create(['doctor_id' => $rojas->id, 'amount' => 350, 'charged_at' => $october->addDays(5)]);
        Charge::factory()->for($patient)->create(['amount' => 90, 'charged_at' => $october->addDays(6)]);
        Charge::factory()->for($patient)->create(['doctor_id' => $vega->id, 'amount' => 800, 'charged_at' => $october, 'voided_at' => now()]);
        Charge::factory()->for($patient)->create(['doctor_id' => $vega->id, 'amount' => 700, 'charged_at' => $october->addMonth()]);
        Payment::factory()->for($patient)->create(['doctor_id' => $vega->id, 'amount' => 600, 'method' => PaymentMethod::Yape, 'paid_at' => $october]);
        Payment::factory()->for($patient)->create(['doctor_id' => $vega->id, 'amount' => 250, 'method' => PaymentMethod::Cash, 'paid_at' => $october->endOfMonth()]);
        Payment::factory()->for($patient)->create(['doctor_id' => $rojas->id, 'amount' => 150, 'method' => PaymentMethod::Cash, 'paid_at' => $october->startOfMonth()]);
        Payment::factory()->for($patient)->create(['amount' => 999, 'method' => PaymentMethod::Card, 'paid_at' => $october, 'voided_at' => now()]);
        Payment::factory()->for($patient)->create(['amount' => 50, 'method' => PaymentMethod::Card, 'paid_at' => $october->subMonth()]);
        Expense::factory()->create(['spent_at' => $october, 'amount' => 120]);
        Expense::factory()->create(['spent_at' => $october, 'amount' => 300, 'voided_at' => now()]);
        $this->movement(Supply::factory()->create(['unit_cost' => 12]), StockMovementType::Use, -5, $october->toDateTimeString());

        $report = new EarningsReport(new ReportPeriod($october->startOfMonth(), $october->endOfMonth()));
        [[$summary], $doctors, $methods] = $this->callProcedure('call sp_reporte_mensual_ingresos(?, ?)', [2026, 10]);

        $this->assertSame(
            ['2026-10', $report->collected(), $report->paymentsCount(), $report->produced(), $report->spent(), $report->materialsCost(), $report->profit(), EarningsReport::outstanding()],
            [$summary['mes'], (float) $summary['cobrado'], (int) $summary['pagos'], (float) $summary['realizado'], (float) $summary['gastos'], (float) $summary['materiales'], (float) $summary['queda'], (float) $summary['por_cobrar_hoy']],
        );
        $this->assertSame([1000.0, 1440.0, 120.0, 60.0, 820.0], [(float) $summary['cobrado'], (float) $summary['realizado'], (float) $summary['gastos'], (float) $summary['materiales'], (float) $summary['queda']]);
        $this->assertEqualsCanonicalizing(
            collect($report->byDoctor())->map(fn (array $row) => [$row['name'], $row['produced'], $row['collected']])->all(),
            collect($doctors)->map(fn (array $row) => [$row['doctor'], (float) $row['realizado'], (float) $row['cobrado']])->all(),
        );
        $this->assertSame(
            collect($report->byMethod())->map(fn (array $row) => [$row['label'], $row['collected'], $row['payments'], $row['share']])->all(),
            collect($methods)->map(fn (array $row) => [$row['medio_de_pago'], (float) $row['cobrado'], (int) $row['pagos'], (float) $row['porcentaje']])->all(),
        );
    }

    public function test_the_trigger_leaves_alone_what_the_application_already_audits(): void
    {
        $this->actingAs($user = User::factory()->create());
        $payment = Payment::factory()->create(['amount' => 150]);

        // Como la acción "Anular" de Caja (PaymentsTable).
        $payment->update(['voided_at' => now(), 'voided_by_user_id' => $user->id, 'void_reason' => 'Se registró dos veces']);

        $this->assertSame(0, DB::table('payment_audit_logs')->count());
        $this->assertTrue(AuditLog::query()->where('auditable_id', $payment->id)->where('event', 'updated')->exists());
    }

    public function test_the_trigger_records_what_is_changed_or_deleted_outside_the_application(): void
    {
        $payment = Payment::factory()->create(['amount' => 150, 'method' => PaymentMethod::Yape]);
        $voided = Payment::factory()->create(['voided_at' => '2026-10-01 10:00:00', 'void_reason' => 'Duplicado']);

        DB::table('payments')->where('id', $payment->id)->update(['amount' => 15, 'method' => 'cash', 'updated_at' => now()]);
        DB::table('payments')->where('id', $voided->id)->update(['voided_at' => null]);
        DB::table('payments')->where('id', $payment->id)->delete();

        $logs = DB::table('payment_audit_logs')->orderBy('id')->get();

        $this->assertSame(['updated', 'updated', 'deleted'], $logs->pluck('event')->all());
        $this->assertSame([$payment->id, $voided->id, $payment->id], $logs->pluck('payment_id')->map(fn ($id) => (int) $id)->all());
        $this->assertEquals(['amount' => 150, 'method' => 'yape'], json_decode($logs[0]->old_values, true));
        $this->assertEquals(['amount' => 15, 'method' => 'cash'], json_decode($logs[0]->new_values, true));
        $this->assertEquals(['voided_at' => null], json_decode($logs[1]->new_values, true));
        $this->assertEquals(15, json_decode($logs[2]->old_values, true)['amount']);
        $this->assertNull($logs[2]->new_values);
        $this->assertStringContainsString('@', $logs[0]->database_user);
    }

    public function test_the_frequent_queries_can_use_the_new_indexes(): void
    {
        $this->assertContains('appointments_starts_on_index', $this->possibleKeys(Appointment::query()->whereDate('starts_at', today())));
        $this->assertContains('payments_paid_on_index', $this->possibleKeys(Payment::query()->whereDate('paid_at', today())));
        $this->assertContains('charges_charged_at_index', $this->possibleKeys(Charge::query()->whereBetween('charged_at', [today()->startOfMonth(), today()->endOfMonth()])));
        $this->assertContains('charges_patient_balance_index', $this->possibleKeys(Charge::query()->where('patient_id', 1)->whereNull('voided_at')));
        $this->assertContains('payments_patient_balance_index', $this->possibleKeys(Payment::query()->where('patient_id', 1)->whereNull('voided_at')));
    }

    /** @param  list<mixed>  $bindings */
    private function value(string $sql, array $bindings = []): mixed
    {
        return array_values((array) DB::selectOne($sql, $bindings))[0];
    }

    /**
     * Todos los resultados de un procedimiento (Laravel solo devuelve el primero).
     *
     * @param  list<mixed>  $bindings
     * @return list<list<array<string, mixed>>>
     */
    private function callProcedure(string $sql, array $bindings): array
    {
        $statement = DB::connection()->getPdo()->prepare($sql);
        $statement->execute($bindings);
        $results = [];

        do {
            if ($statement->columnCount() > 0) {
                $results[] = $statement->fetchAll(PDO::FETCH_ASSOC);
            }
        } while ($statement->nextRowset());

        return $results;
    }

    /** @return list<string> */
    private function possibleKeys(Builder $query): array
    {
        $plan = (array) DB::selectOne('explain '.$query->toRawSql());

        return explode(',', (string) ($plan['possible_keys'] ?? ''));
    }

    private function planInInstallments(Patient $patient, Doctor $doctor, TreatmentPlanStatus $status): TreatmentPlan
    {
        return TreatmentPlan::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'title' => 'Implante y corona', 'status' => $status, 'payment_mode' => PlanPaymentMode::Installments, 'accepted_at' => now()->subMonths(2)]);
    }

    private function installment(TreatmentPlan $plan, int $number, CarbonImmutable $dueOn, float $amount, float $discount = 0, bool $charged = false): TreatmentPlanInstallment
    {
        $charge = $charged ? Charge::factory()->create(['patient_id' => $plan->patient_id, 'amount' => $amount, 'discount' => $discount, 'charged_at' => $dueOn->setTime(9, 0)]) : null;

        return $plan->installments()->create(['number' => $number, 'due_on' => $dueOn, 'amount' => $amount, 'discount' => $discount, 'charge_id' => $charge?->id]);
    }

    private function payment(Patient $patient, float $amount, PaymentMethod $method, CarbonImmutable $paidAt, User $receivedBy): Payment
    {
        return Payment::factory()->for($patient)->create(['amount' => $amount, 'method' => $method, 'paid_at' => $paidAt, 'received_by_user_id' => $receivedBy->id]);
    }

    private function movement(Supply $supply, StockMovementType $type, float $quantity, string $createdAt): void
    {
        DB::table('stock_movements')->insert([
            'supply_id' => $supply->id,
            'type' => $type->value,
            'quantity' => $quantity,
            'unit_cost' => $supply->unit_cost,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
