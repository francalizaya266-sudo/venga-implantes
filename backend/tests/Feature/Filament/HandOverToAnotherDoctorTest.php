<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\Pages\ViewAppointment;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/**
 * Ya está en sala y su doctor no puede atenderlo (no vino, se atrasó): recepción lo pasa con otro doctor libre. Antes
 * había que deshacer la llegada, mover la cita y volver a marcar "Llegó".
 */
class HandOverToAnotherDoctorTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private User $reception;

    private Doctor $torres;

    private Doctor $ramos;

    /** Rosa tiene Limpieza con la Dra. Torres a las 10:30 y ya está en sala; son las 10:10 y la doctora no llegó. */
    private Appointment $rosa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->reception = User::factory()->reception()->create();
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza', 'duration_minutes' => 30]);
        $this->torres = $this->doctorOffering($cleaning);
        $this->torres->update(['name' => 'Dra. Ana Torres']);
        $this->ramos = $this->doctorOffering($cleaning);
        $this->ramos->update(['name' => 'Dr. Luis Ramos']);
        // El Dr. Ramos atiende hasta las 10:30.
        Appointment::factory()->for($this->ramos)->for($cleaning)->confirmed()->at('2026-11-02 10:00')->create();
        $this->rosa = Appointment::factory()->for($this->torres)->for($cleaning)
            ->for(Patient::factory()->state(['first_name' => 'Rosa', 'last_name' => 'Quispe']))
            ->at('2026-11-02 10:30')->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => '2026-11-02 10:05']);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:10', 'America/Lima'));
        $this->actingAs($this->reception);
    }

    public function test_reception_passes_a_waiting_patient_to_another_free_doctor_who_then_sees_them(): void
    {
        WaitlistEntry::factory()->for(Patient::factory()->state(['first_name' => 'Ana', 'last_name' => 'Soto']))->create(['treatment_id' => $this->rosa->treatment_id]);

        Livewire::test(ViewAppointment::class, ['record' => $this->rosa->getKey()])
            ->mountAction('handOver')
            ->assertMountedActionModalSee('Dr. Luis Ramos · libre desde las 10:40')
            ->setActionData(['doctor_id' => $this->ramos->id])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Rosa Quispe pasa con Dr. Luis Ramos a las 10:40.');

        $rosa = $this->rosa->fresh();
        $this->assertSame([$this->ramos->id, '10:40', AppointmentStatus::CheckedIn], [$rosa->doctor_id, $rosa->starts_at->format('H:i'), $rosa->status]);

        // El nuevo doctor recibe "Llegó" y ya la ve; la Dra. Torres se entera y deja de verla (no tiene otra cita con ella).
        $this->assertTrue($this->ramos->user->notifications()->where('data->title', 'Llegó Rosa Quispe')->exists());
        $this->assertTrue($this->torres->user->notifications()->where('data->title', 'Rosa Quispe pasó con Dr. Luis Ramos')->exists());
        $this->assertTrue($this->ramos->user->can('view', $rosa->patient));
        $this->assertFalse($this->torres->user->can('view', $rosa->patient));

        // Su hora con la Dra. Torres quedó libre: desde cuando alguien de la lista alcanza a llegar, recepción se entera.
        $alert = $this->reception->notifications()->where('data->title', 'Se liberó un horario')->sole()->data;
        $this->assertStringContainsString('10:45 a. m. con Dra. Ana Torres. Ana Soto, de la lista de espera, puede venir a esa hora.', $alert['body']);
    }

    public function test_it_is_only_for_reception_while_the_patient_waits_in_the_room(): void
    {
        $this->rosa->update(['status' => AppointmentStatus::InProgress]);
        Livewire::test(ViewAppointment::class, ['record' => $this->rosa->getKey()])->assertActionHidden('handOver');

        $this->rosa->update(['status' => AppointmentStatus::CheckedIn]);
        $this->actingAs($this->torres->user);
        Livewire::test(ViewAppointment::class, ['record' => $this->rosa->getKey()])->assertActionHidden('handOver');
    }

    public function test_it_says_so_when_no_other_doctor_has_room_today(): void
    {
        $this->ramos->treatments()->detach();

        Livewire::test(ViewAppointment::class, ['record' => $this->rosa->getKey()])
            ->mountAction('handOver')
            ->assertMountedActionModalSee('Ningún otro doctor que haga limpieza tiene un hueco hoy.');
    }
}
