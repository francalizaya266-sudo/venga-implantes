<?php

namespace Tests\Feature\Filament;

use App\Enums\PaymentMethod;
use App\Filament\Widgets\CashClosingTables;
use App\Filament\Widgets\Reports\EarningsTables;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Las tablas del cierre y de Ingresos: sin media fila vacía, con los pagos del día como tarjetas en el celular y con
 * la zona que se desliza al alcance del teclado.
 */
class ReportTablesOnPhonesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_narrow_table_left_alone_in_its_row_takes_the_whole_width(): void
    {
        $tables = fn (string ...$keys) => array_map(fn (string $key) => ['key' => $key], $keys);

        // Ingresos con un solo doctor: "Por medio de pago" quedaba sola, con media fila vacía.
        $this->assertSame(['period', 'method'], EarningsTables::fullWidth($tables('period', 'profit', 'treatment', 'method'), ['period']));
        // Con "Por doctor" las angostas van de a dos.
        $this->assertSame(['period'], EarningsTables::fullWidth($tables('period', 'profit', 'doctor', 'treatment', 'method'), ['period']));
        // El cierre de una persona: sin "Por persona", el medio de pago va solo; los gastos, entre dos anchas, también.
        $this->assertSame(['method', 'payments', 'expenses', 'voided'], EarningsTables::fullWidth($tables('method', 'payments', 'expenses', 'voided'), ['payments', 'voided']));
        $this->assertSame(['payments'], EarningsTables::fullWidth($tables('method', 'person', 'payments'), ['payments', 'voided']));
    }

    public function test_the_payments_of_the_day_carry_what_their_phone_cards_need(): void
    {
        $this->travelTo(now()->parse('2026-11-02 19:30'));
        $reception = User::factory()->reception()->create(['name' => 'Rosa Pérez']);
        Payment::factory()->for(Patient::factory()->create(['first_name' => 'Ana', 'last_name' => 'Flores']))->create([
            'amount' => 120, 'method' => PaymentMethod::Yape, 'reference' => '0012345', 'paid_at' => '2026-11-02 10:40', 'received_by_user_id' => $reception->id,
        ]);
        $this->actingAs($reception);

        Livewire::test(CashClosingTables::class, ['pageFilters' => ['date' => '2026-11-02']])
            // Los pagos van como tarjetas; el resumen por medio y por persona sigue como tabla.
            ->assertSeeHtml('class="report-table cc-cards"')
            ->assertSeeHtml('data-label="N.º de operación">0012345</td>')
            ->assertSeeHtml('data-label="Monto">S/ 120.00</td>')
            // Cada zona que se desliza se alcanza con el teclado y dice qué tabla es.
            ->assertSeeHtml('tabindex="0" role="region" aria-label="Pagos del día"')
            ->assertSeeHtml('tabindex="0" role="region" aria-label="Por medio de pago"');
    }

    public function test_the_earnings_tables_can_be_reached_with_the_keyboard(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(EarningsTables::class, ['pageFilters' => ['period' => 'month']])
            ->assertSeeHtml('tabindex="0" role="region" aria-label="Por día"')
            ->assertSeeHtml('tabindex="0" role="region" aria-label="Por medio de pago"');
    }
}
