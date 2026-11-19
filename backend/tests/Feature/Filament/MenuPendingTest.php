<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Agenda;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Consultation;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Booking\RescheduleRequestService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** Cada opción principal del menú dice cuánto espera adentro, aunque esté en una de sus opciones de adentro. */
class MenuPendingTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    public function test_the_agenda_counts_the_appointments_to_confirm_and_the_changes_patients_asked_for(): void
    {
        $this->freezeMonday();
        $treatment = Treatment::factory()->create(['duration_minutes' => 30]);
        $doctor = $this->doctorOffering($treatment);
        // Sin confirmar: mañana cuenta; la del jueves, todavía no.
        Appointment::factory()->for($doctor)->for($treatment)->at('2026-11-03 10:00')->create();
        Appointment::factory()->for($doctor)->for($treatment)->at('2026-11-05 10:00')->create();
        $moving = Appointment::factory()->for($doctor)->for($treatment)->at('2026-11-04 11:00')->confirmed()->create();
        app(RescheduleRequestService::class)->requestByPatient($moving, [CarbonImmutable::parse('2026-11-06 10:00')]);

        $this->actingAs(User::factory()->reception()->create());

        $this->assertSame('2', Agenda::getNavigationBadge());
        $this->assertSame('1 cita por confirmar (hoy o mañana) · 1 cambio de horario pedido', Agenda::getNavigationBadgeTooltip());

        // En el menú, junto a "Agenda", aunque "Cambios pedidos" esté adentro.
        $this->get(Dashboard::getUrl())->assertOk()->assertSee('1 cita por confirmar (hoy o mañana) · 1 cambio de horario pedido');
    }

    public function test_patients_count_the_unfinished_visits_and_the_lab_work_that_needs_something(): void
    {
        $this->freezeMonday();
        $doctor = $this->doctorOffering(Treatment::factory()->create());
        Consultation::factory()->for($doctor)->create(['closed_at' => null]);
        Consultation::factory()->for($doctor)->create(['closed_at' => now()]);
        // Atrasado: tenía que llegar ayer.
        LabOrder::factory()->create(['doctor_id' => $doctor->id, 'due_on' => today()->subDay()]);
        LabOrder::factory()->create(['doctor_id' => $doctor->id]);

        $this->actingAs($doctor->user);

        $this->assertSame('2', PatientResource::getNavigationBadge());
        $this->assertSame('1 atención sin terminar · 1 trabajo de laboratorio por revisar', PatientResource::getNavigationBadgeTooltip());
    }

    public function test_the_cash_desk_counts_who_still_owes_for_today(): void
    {
        $this->freezeMonday();
        $owes = Patient::factory()->create();
        Charge::factory()->for($owes)->create(['amount' => 50]);
        $paid = Patient::factory()->create();
        Charge::factory()->for($paid)->create(['amount' => 80]);
        Payment::factory()->for($paid)->create(['amount' => 80]);

        $this->actingAs(User::factory()->reception()->create());

        $this->assertSame('1', PaymentResource::getNavigationBadge());
        $this->assertSame('1 paciente por cobrar (de hoy)', PaymentResource::getNavigationBadgeTooltip());

        // Mañana ya es otro día: lo de ayer queda en Deudas.
        $this->travelTo(now()->addDay());
        $this->get(Dashboard::getUrl());
        $this->assertNull(PaymentResource::getNavigationBadge());
    }

    public function test_nothing_waiting_shows_no_number(): void
    {
        $this->freezeMonday();
        $this->actingAs(User::factory()->reception()->create());

        $this->assertNull(Agenda::getNavigationBadge());
        $this->assertNull(PatientResource::getNavigationBadge());
        $this->assertNull(PaymentResource::getNavigationBadge());
        $this->assertNull(Agenda::getNavigationBadgeTooltip());
    }
}
