<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Filament\Pages\Agenda;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\ConsentTemplate;
use App\Models\Doctor;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** Lo que la agenda dice de un vistazo: cómo está cada doctor ahora, los minutos en sala, el consentimiento, el laboratorio y "Enfoque". */
class AgendaAtAGlanceTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Treatment $cleaning;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'duration_minutes' => 30]);
        $this->doctor = $this->doctorOffering($this->cleaning);
    }

    public function test_the_doctor_header_says_who_they_are_seeing_and_who_waits_today(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:20', 'America/Lima'));
        $this->visit('Rosa', '2026-11-02 10:00', ['status' => AppointmentStatus::InProgress, 'checked_in_at' => '2026-11-02 09:55', 'started_at' => '2026-11-02 10:02']);
        $this->visit('Lucía', '2026-11-02 10:30', ['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => '2026-11-02 10:08']);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(Agenda::class)
            ->assertSeeHtml('agenda-head-live is-busy has-waiting')
            ->assertSee("Atendiendo a Rosa Prueba · 18\u{00A0}min · 1 en sala")
            // En la tarjeta, cuánto lleva cada uno.
            ->assertSee(["En atención · 18\u{00A0}min", "En sala · 12\u{00A0}min"]);

        // Otro día, o en la semana, no se dice: es lo de ahora.
        Livewire::test(Agenda::class, ['date' => '2026-11-03'])->assertDontSeeHtml('agenda-head-live');
        Livewire::test(Agenda::class)->set('mode', 'week')->set('doctor', $this->doctor->id)->assertDontSeeHtml('agenda-head-live');
    }

    public function test_a_free_doctor_in_working_hours_says_so_and_outside_them_nothing(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 11:00', 'America/Lima'));
        $this->actingAs(User::factory()->reception()->create());

        // El texto va en su propia caja, para que en una columna angosta termine en "…" y no a media palabra.
        Livewire::test(Agenda::class)->assertSeeHtml('<span class="agenda-head-live" title="Libre"><i aria-hidden="true"></i><span>Libre</span></span>');

        $this->travelTo(CarbonImmutable::parse('2026-11-02 14:00', 'America/Lima'));
        Livewire::test(Agenda::class)->assertDontSeeHtml('agenda-head-live');
    }

    public function test_whoever_attends_sees_on_the_card_when_the_consent_is_missing(): void
    {
        ConsentTemplate::create(['name' => 'Consentimiento de limpieza', 'treatment_id' => $this->cleaning->id, 'body' => 'Limpieza de {paciente}.']);
        $this->visit('Rosa', '2026-11-02 10:00');
        $this->visit('Bruno', '2026-11-02 09:00', ['status' => AppointmentStatus::Completed]);

        $this->actingAs($this->doctor->user);
        // Solo la que viene: la atendida ya no tiene nada que preparar.
        $html = Livewire::test(Agenda::class)->set('mode', 'day')->html();
        $this->assertSame(1, substr_count($html, '<span class="tag tag-consent">Falta consentimiento</span>'));

        $this->actingAs(User::factory()->reception()->create());
        Livewire::test(Agenda::class)->assertDontSeeHtml('tag-consent');
    }

    public function test_the_card_says_what_the_patient_waits_for_from_the_lab(): void
    {
        $rosa = $this->visit('Rosa', '2026-11-02 10:00');
        $lucia = $this->visit('Lucía', '2026-11-02 11:00');
        $bruno = $this->visit('Bruno', '2026-11-02 12:00');
        LabOrder::factory()->for($rosa->patient)->create(['doctor_id' => $this->doctor->id, 'description' => 'Corona de zirconio', 'sent_on' => '2026-10-20', 'due_on' => '2026-10-30']);
        LabOrder::factory()->for($lucia->patient)->create(['doctor_id' => $this->doctor->id, 'description' => 'Férula de descarga', 'received_on' => '2026-10-31']);
        LabOrder::factory()->for($bruno->patient)->create(['doctor_id' => $this->doctor->id, 'description' => 'Prótesis parcial', 'due_on' => '2026-11-05']);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(Agenda::class)
            ->assertSeeHtmlInOrder([
                'Rosa Prueba', 'title="Corona de zirconio: no llegó, debía llegar el 30 de octubre">Lab atrasado<',
                'Lucía Prueba', 'title="Férula de descarga: ya llegó, el 31 de octubre">Lab listo<',
                'Bruno Prueba', 'title="Prótesis parcial: sigue en el laboratorio, llega el 5 de noviembre">Lab pendiente<',
            ]);
    }

    public function test_focus_dims_what_was_attended_and_paid(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Lima'));
        $paid = $this->visit('Rosa', '2026-11-02 09:00', ['status' => AppointmentStatus::Completed]);
        $owes = $this->visit('Lucía', '2026-11-02 10:00', ['status' => AppointmentStatus::Completed]);
        Charge::factory()->for($owes->patient)->create(['appointment_id' => $owes->id, 'amount' => 80]);
        $this->actingAs(User::factory()->reception()->create());

        $html = Livewire::test(Agenda::class)
            ->assertSeeHtml('class="agenda-nav agenda-focus-toggle"')
            ->html();

        // Solo la atendida que no debe nada: la que debe sigue a la vista para cobrarle.
        $this->assertSame(1, substr_count($html, 'is-settled'));
        $this->assertMatchesRegularExpression('/wire:key="appt-'.$paid->id.'"[^>]*is-settled/s', $html);
    }

    public function test_the_colours_are_inside_the_help(): void
    {
        $this->actingAs(User::factory()->reception()->create());

        $html = Livewire::test(Agenda::class)
            ->assertSeeHtmlInOrder(['<details class="agenda-help">', 'Colores y ayuda', 'class="agenda-legend"', 'Pendiente', 'Toca una cita'])
            ->html();

        $this->assertSame(1, substr_count($html, 'class="agenda-legend"'));
    }

    /** @param  array<string, mixed>  $attributes */
    private function visit(string $firstName, string $startsAt, array $attributes = []): Appointment
    {
        return Appointment::factory()
            ->for($this->doctor)
            ->for($this->cleaning)
            ->for(Patient::factory()->state(['first_name' => $firstName, 'last_name' => 'Prueba']))
            ->confirmed()
            ->at($startsAt)
            ->create($attributes);
    }
}
