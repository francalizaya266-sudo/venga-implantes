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
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WhatsappMessage;
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
        Charge::factory()->for($luis)->create(['description' => 'Limpieza dental', 'amount' => 80, 'charged_at' => '2026-11-02 11:30']);
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
            'Descuentos: S/ 50.00 en 1 cargo (Ana Prueba: Familiar)',
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
            'Descuentos: ninguno',
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
