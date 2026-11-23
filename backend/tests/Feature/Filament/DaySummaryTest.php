<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Widgets\CashClosingCheck;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\DailyClosing;
use App\Models\Doctor;
use App\Models\MessageTemplate;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Billing\BillingService;
use App\Services\Billing\DaySummary;
use App\Services\WhatsApp\WhatsAppOutbox;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/**
 * Al cerrar la caja, a quien administra le llega el resumen del día: en la campana y en Avisos, listo para enviarlo a
 * su WhatsApp (o al del consultorio, si nadie que administra tiene celular).
 */
class DaySummaryTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Treatment $treatment;

    private Doctor $doctor;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        Setting::set('clinic.name', 'Clínica Sonrisa');
        $this->treatment = Treatment::factory()->create(['name' => 'Limpieza dental', 'price_from' => 120]);
        $this->doctor = $this->doctorOffering($this->treatment);
        $this->reception = User::factory()->reception()->create(['name' => 'Rosa Pérez']);
    }

    public function test_closing_the_day_sends_its_summary_to_whoever_runs_the_clinic(): void
    {
        $owner = User::factory()->admin()->create(['name' => 'Elena Ríos', 'phone' => '+51987654321']);
        $former = User::factory()->admin()->create(['is_active' => false, 'phone' => '+51987111222']);

        // El lunes: Ana y Luis se atendieron, Pedro no vino y la cita de María quedó sin cerrar.
        $ana = $this->visit('Ana', '2026-11-02 10:00', AppointmentStatus::Completed)->patient;
        $luis = $this->visit('Luis', '2026-11-02 11:00', AppointmentStatus::Completed)->patient;
        $this->visit('Pedro', '2026-11-02 12:00', AppointmentStatus::NoShow);
        $this->visit('María', '2026-11-02 09:00', AppointmentStatus::Confirmed);
        // El martes: dos citas por confirmar y una confirmada.
        $this->visit('Sofía', '2026-11-03 10:00', AppointmentStatus::Pending);
        $this->visit('Juan', '2026-11-03 11:00', AppointmentStatus::Pending);
        $this->visit('Marta', '2026-11-03 12:00', AppointmentStatus::Confirmed);

        Charge::factory()->for($ana)->create(['description' => 'Limpieza dental', 'amount' => 200, 'discount' => 50, 'discount_reason' => 'Familiar', 'charged_at' => '2026-11-02 10:30']);
        // A Luis se le cobró la limpieza a S/ 80.00 en vez de S/ 120.00, con su motivo (PriceAdjustments).
        Charge::factory()->for($luis)->create(['description' => 'Limpieza dental', 'amount' => 80, 'charged_at' => '2026-11-02 11:30', 'original_amount' => 120, 'adjustment_reason' => 'Paciente frecuente', 'adjusted_by_user_id' => $this->reception->id, 'adjusted_at' => '2026-11-02 11:35']);
        // Un cargo anulado: su descuento no cuenta.
        Charge::factory()->for($luis)->create(['amount' => 100, 'discount' => 20, 'charged_at' => '2026-11-02 11:40', 'voided_at' => '2026-11-02 11:45']);
        Payment::factory()->for($ana)->create(['amount' => 150, 'method' => PaymentMethod::Cash, 'paid_at' => '2026-11-02 10:40', 'received_by_user_id' => $this->reception->id]);
        Payment::factory()->for($luis)->create(['amount' => 80, 'method' => PaymentMethod::Yape, 'paid_at' => '2026-11-02 11:40', 'received_by_user_id' => $this->reception->id]);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Lima'));
        $this->actingAs($this->reception);

        Livewire::test(CashClosingCheck::class, ['pageFilters' => ['date' => '2026-11-02']])
            ->callAction('closeDay', ['opening_cash' => 100, 'counted_cash' => 250])
            ->assertHasNoErrors()
            ->assertNotified(Notification::make()->success()->title('Día cerrado')->body('Cuadra: el efectivo contado es el que debía haber.<br>El resumen del día para quien administra quedó en Avisos, listo para enviar por WhatsApp.'));

        $summary = [
            'Cobrado: S/ 230.00 (Efectivo S/ 150.00 · Yape S/ 80.00)',
            'Arqueo: cuadra · cerró Rosa Pérez',
            'Pacientes atendidos: 2 · Faltas: 1 · Citas sin cerrar: 1',
            'Descuentos puestos antes de cobrar: S/ 50.00 en 1 cargo (Ana Prueba: Familiar)',
            'Precios cambiados y descuentos al cobrar: 1 cambio, se cobró S/ 40.00 menos (Luis Prueba: Paciente frecuente)',
            'Citas del martes 3 sin confirmar: 2',
        ];

        // En la campana de quien administra; no en la de quien ya no entra al panel ni en la de recepción.
        $bell = $owner->notifications()->sole();
        $this->assertSame(['Resumen del lunes 2 de noviembre', str_replace('S/ ', "S/\u{00A0}", implode('<br>', $summary))], [$bell->data['title'], $bell->data['body']]);
        $this->assertSame(0, $former->notifications()->count());
        $this->assertSame(0, $this->reception->notifications()->count());

        // En Avisos, para su celular, con el texto de la plantilla (se cambia en Textos de los avisos).
        $message = WhatsappMessage::sole();
        $this->assertSame([MessageTemplateKey::DaySummary, '+51987654321', MessageStatus::Pending], [$message->template_key, $message->phone, $message->status]);
        $this->assertSame("Resumen del lunes 2 de noviembre en Clínica Sonrisa\n".implode("\n", $summary), app(WhatsAppOutbox::class)->draft($message));
    }

    public function test_a_discount_put_at_checkout_is_counted_once_apart_from_the_ones_the_charges_came_with(): void
    {
        $billing = app(BillingService::class);
        $promotion = ['description' => 'Limpieza dental', 'amount' => 120, 'discount' => 30, 'discount_reason' => 'Promoción: Sonrisa de noviembre'];

        // Ana pagó la limpieza con el descuento de la promoción, tal como llegó a caja.
        $ana = $this->visit('Ana', '2026-11-02 10:00', AppointmentStatus::Completed);
        Charge::factory()->for($ana->patient)->for($ana)->create([...$promotion, 'charged_at' => '2026-11-02 10:30']);
        // A Luis se le puso un descuento al cobrar.
        $luis = $this->visit('Luis', '2026-11-02 11:00', AppointmentStatus::Completed);
        $luisCleaning = Charge::factory()->for($luis->patient)->for($luis)->create(['description' => 'Limpieza dental', 'amount' => 120, 'charged_at' => '2026-11-02 11:30']);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 11:35', 'America/Lima'));
        $billing->reviseCharges($luis, [['id' => $luisCleaning->id, 'description' => 'Limpieza dental', 'amount' => 120, 'discount' => 20]], $this->reception, 'Paciente frecuente');
        // María llegó con la promoción y al cobrar se le subió el descuento de S/ 30.00 a S/ 50.00.
        $maria = $this->visit('María', '2026-11-02 12:00', AppointmentStatus::Completed);
        $mariaCleaning = Charge::factory()->for($maria->patient)->for($maria)->create([...$promotion, 'charged_at' => '2026-11-02 12:30']);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:35', 'America/Lima'));
        $billing->reviseCharges($maria, [['id' => $mariaCleaning->id, 'description' => 'Limpieza dental', 'amount' => 120, 'discount' => 50]], $this->reception, 'Cumpleaños');

        User::factory()->admin()->create(['phone' => '+51987654321']);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Lima'));
        $this->actingAs($this->reception);

        Livewire::test(CashClosingCheck::class, ['pageFilters' => ['date' => '2026-11-02']])
            ->callAction('closeDay', ['opening_cash' => 100, 'counted_cash' => 100])
            ->assertHasNoErrors();

        // S/ 100.00 de descuento: S/ 60.00 que traían los cargos y S/ 40.00 puestos al cobrar, cada sol en una sola línea.
        $this->assertStringContainsString(implode("\n", [
            'Descuentos puestos antes de cobrar: S/ 60.00 en 2 cargos (Ana Prueba: Promoción: Sonrisa de noviembre · María Prueba)',
            'Precios cambiados y descuentos al cobrar: 2 cambios, se cobró S/ 40.00 menos (Luis Prueba: Paciente frecuente · María Prueba: Cumpleaños)',
        ]), app(WhatsAppOutbox::class)->draft(WhatsappMessage::sole()));
    }

    public function test_what_is_left_of_a_discount_lowered_at_checkout_counts_and_a_concept_added_there_does_not(): void
    {
        // Pedro llegó con S/ 30.00 de la promoción; al cobrar quedó en S/ 10.00 y se agregó una radiografía con S/ 5.00 menos.
        $pedro = $this->visit('Pedro', '2026-11-02 10:00', AppointmentStatus::Completed);
        $cleaning = Charge::factory()->for($pedro->patient)->for($pedro)->create(['description' => 'Limpieza dental', 'amount' => 120, 'discount' => 30, 'discount_reason' => 'Promoción: Sonrisa de noviembre', 'charged_at' => '2026-11-02 10:30']);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:35', 'America/Lima'));
        app(BillingService::class)->reviseCharges($pedro, [
            ['id' => $cleaning->id, 'description' => 'Limpieza dental', 'amount' => 120, 'discount' => 10],
            ['description' => 'Radiografía', 'amount' => 50, 'discount' => 5],
        ], $this->reception, 'La promoción era hasta ayer');

        User::factory()->admin()->create(['phone' => '+51987654321']);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Lima'));
        $this->actingAs($this->reception);

        Livewire::test(CashClosingCheck::class, ['pageFilters' => ['date' => '2026-11-02']])
            ->callAction('closeDay', ['opening_cash' => 100, 'counted_cash' => 100])
            ->assertHasNoErrors();

        // De la promoción quedan S/ 10.00; los S/ 20.00 que se quitaron y la radiografía son cambios al cobrar.
        $this->assertStringContainsString(implode("\n", [
            'Descuentos puestos antes de cobrar: S/ 10.00 en 1 cargo (Pedro Prueba)',
            'Precios cambiados y descuentos al cobrar: 2 cambios, se cobró S/ 5.00 menos, S/ 20.00 más por precios subidos (Pedro Prueba: La promoción era hasta ayer · Pedro Prueba: La promoción era hasta ayer)',
        ]), app(WhatsAppOutbox::class)->draft(WhatsappMessage::sole()));
    }

    public function test_the_saved_summary_text_tells_apart_the_discounts_put_before_checkout_unless_the_clinic_changed_it(): void
    {
        $firstBody = "Resumen del {fecha} en {consultorio}\nCobrado: {cobrado}\nArqueo: {arqueo} · cerró {cerro}\nPacientes atendidos: {atendidos} · Faltas: {faltas} · Citas sin cerrar: {sin_cerrar}\nDescuentos: {descuentos}\nCitas del {siguiente} sin confirmar: {sin_confirmar}";
        $oldBody = "Resumen del {fecha} en {consultorio}\nCobrado: {cobrado}\nArqueo: {arqueo} · cerró {cerro}\nPacientes atendidos: {atendidos} · Faltas: {faltas} · Citas sin cerrar: {sin_cerrar}\nDescuentos: {descuentos}\nPrecios cambiados y descuentos al cobrar: {precios}\nCitas del {siguiente} sin confirmar: {sin_confirmar}";
        $migration = require database_path('migrations/2026_11_23_090607_update_day_summary_template_counts_each_discount_once.php');
        $savedBody = fn () => MessageTemplate::query()->where('key', MessageTemplateKey::DaySummary)->value('body');

        // Con el texto de antes, y con el primero, que no traía los precios cambiados al cobrar, pasa al de ahora.
        foreach ([$oldBody, $firstBody] as $body) {
            MessageTemplate::query()->where('key', MessageTemplateKey::DaySummary)->update(['body' => $body]);
            $migration->up();
            $this->assertSame(MessageTemplateKey::DaySummary->defaultBody(), $savedBody());
        }

        // Si el consultorio ya lo había cambiado, se respeta.
        MessageTemplate::query()->where('key', MessageTemplateKey::DaySummary)->update(['body' => 'Resumen del {fecha}: {cobrado}']);
        $migration->up();
        $this->assertSame('Resumen del {fecha}: {cobrado}', $savedBody());
    }

    public function test_without_a_phone_of_their_own_it_goes_to_the_clinic_and_closing_again_replaces_it(): void
    {
        Setting::set('clinic.whatsapp', '+51918874568');
        $owner = User::factory()->admin()->create(['name' => 'Elena Ríos']);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Lima'));
        $this->actingAs($owner);

        $closing = Livewire::test(CashClosingCheck::class, ['pageFilters' => ['date' => '2026-11-02']])
            ->callAction('closeDay', ['opening_cash' => 100, 'counted_cash' => 90, 'notes' => 'Se dio vuelto de más'])
            ->assertHasNoErrors();

        // Un día sin pagos ni citas también se resume: en ceros.
        $first = WhatsappMessage::sole();
        $this->assertSame('+51918874568', $first->phone);
        $this->assertSame(implode("\n", [
            'Resumen del lunes 2 de noviembre en Clínica Sonrisa',
            'Cobrado: S/ 0.00',
            'Arqueo: faltan S/ 10.00 (Se dio vuelto de más) · cerró Elena Ríos',
            'Pacientes atendidos: 0 · Faltas: 0 · Citas sin cerrar: 0',
            'Descuentos puestos antes de cobrar: ninguno',
            'Precios cambiados y descuentos al cobrar: ninguno',
            'Citas del martes 3 sin confirmar: 0',
        ]), app(WhatsAppOutbox::class)->draft($first));

        // Se reabre y se vuelve a cerrar: el resumen que no se envió queda reemplazado por el nuevo.
        $closing->callAction('reopenDay')
            ->callAction('closeDay', ['opening_cash' => 100, 'counted_cash' => 100])
            ->assertHasNoErrors();

        $this->assertSame(MessageStatus::Cancelled, $first->fresh()->status);
        $this->assertStringContainsString('Arqueo: cuadra · cerró Elena Ríos', app(WhatsAppOutbox::class)->draft(WhatsappMessage::query()->pending()->sole()));
        $this->assertSame(2, $owner->notifications()->count());
    }

    public function test_on_saturday_it_counts_the_unconfirmed_appointments_of_the_next_day_with_visits(): void
    {
        $owner = User::factory()->admin()->create(['phone' => '+51987654321']);
        // El domingo no se atiende: lo que importa el sábado son las citas del lunes.
        $this->visit('Sofía', '2026-11-09 10:00', AppointmentStatus::Pending);
        $this->visit('Juan', '2026-11-09 11:00', AppointmentStatus::Pending);
        $this->visit('Marta', '2026-11-09 12:00', AppointmentStatus::Confirmed);
        $this->visit('Pedro', '2026-11-08 10:00', AppointmentStatus::Cancelled);
        $this->travelTo(CarbonImmutable::parse('2026-11-07 14:00', 'America/Lima'));
        $this->actingAs($this->reception);

        Livewire::test(CashClosingCheck::class, ['pageFilters' => ['date' => '2026-11-07']])
            ->callAction('closeDay', ['opening_cash' => 100, 'counted_cash' => 100])
            ->assertHasNoErrors();

        $this->assertStringEndsWith('Citas del lunes 9 sin confirmar: 2', app(WhatsAppOutbox::class)->draft(WhatsappMessage::sole()));
        $this->assertStringEndsWith('Citas del lunes 9 sin confirmar: 2', $owner->notifications()->sole()->data['body']);
    }

    public function test_if_the_summary_fails_the_day_is_closed_anyway(): void
    {
        $this->mock(DaySummary::class)->shouldReceive('send')->andThrow(new RuntimeException('Sin conexión con la base'));
        $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Lima'));
        $this->actingAs($this->reception);

        Livewire::test(CashClosingCheck::class, ['pageFilters' => ['date' => '2026-11-02']])
            ->callAction('closeDay', ['opening_cash' => 100, 'counted_cash' => 100])
            ->assertHasNoErrors()
            ->assertNotified(Notification::make()->success()->title('Día cerrado')->body('Cuadra: el efectivo contado es el que debía haber.'));

        $this->assertTrue(DailyClosing::isClosed('2026-11-02'));
    }

    public function test_the_phone_of_whoever_runs_the_clinic_is_set_in_users(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $partner = User::factory()->admin()->create();

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($partner), data: ['phone' => '98765'])
            ->assertHasActionErrors(['phone' => 'El celular tiene 9 dígitos y escribiste 5.'])
            ->setActionData(['phone' => '987 654 321'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('+51987654321', $partner->fresh()->phone);
    }

    private function visit(string $firstName, string $when, AppointmentStatus $status): Appointment
    {
        return Appointment::factory()
            ->for($this->doctor)
            ->for($this->treatment)
            ->for(Patient::factory()->create(['first_name' => $firstName, 'last_name' => 'Prueba']))
            ->at($when)
            ->create(['status' => $status]);
    }
}
