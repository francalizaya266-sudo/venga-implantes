<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Filament\Pages\Agenda;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\ScheduleBlock;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/**
 * Arrastrar una cita en la Agenda: que se pueda se dice a la vista, la grilla sabe dónde no cabe para avisar
 * "ocupado" antes de soltarla, y soltada donde no cabe se explica ahí mismo, sin preguntar antes "¿Mover la cita?".
 */
class AgendaDropTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
    }

    public function test_dropping_on_another_appointment_says_who_is_there_without_asking_first(): void
    {
        [$doctor, $treatment] = $this->doctorWithCleaning();
        $appointment = Appointment::factory()->for($doctor)->for($treatment)->at('2026-11-04 10:00')->confirmed()->create();
        Appointment::factory()->for($doctor)->for($treatment)->for(Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']))->at('2026-11-04 16:00', buffer: 0)->create();
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(Agenda::class, ['date' => '2026-11-04'])
            ->mountAction('move', arguments: $this->droppedAt($appointment, $doctor, '2026-11-04', '16:15'))
            ->assertNotified('Ese horario se cruza con la cita de Rosa Quispe, de 16:00 a 16:30. Elige otro.')
            ->assertActionNotMounted('move');

        $this->assertSame('2026-11-04 10:00', $appointment->fresh()->starts_at->format('Y-m-d H:i'));
    }

    public function test_dropping_right_after_another_appointment_respects_the_time_to_prepare_the_chair(): void
    {
        [$doctor, $treatment] = $this->doctorWithCleaning();
        $appointment = Appointment::factory()->for($doctor)->for($treatment)->at('2026-11-04 10:00')->confirmed()->create();
        Appointment::factory()->for($doctor)->for($treatment)->for(Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']))->at('2026-11-04 16:00', buffer: 10)->create();
        $this->actingAs(User::factory()->reception()->create());

        // A las 16:30 termina Rosa, pero el sillón se prepara hasta las 16:40.
        Livewire::test(Agenda::class, ['date' => '2026-11-04'])
            ->mountAction('move', arguments: $this->droppedAt($appointment, $doctor, '2026-11-04', '16:30'))
            ->assertNotified('Ese horario se cruza con la cita de Rosa Quispe, de 16:00 a 16:30 (más 10 min para preparar el sillón). Elige otro.')
            ->assertActionNotMounted('move');

        Livewire::test(Agenda::class, ['date' => '2026-11-04'])
            ->mountAction('move', arguments: $this->droppedAt($appointment, $doctor, '2026-11-04', '16:45'))
            ->assertNotNotified()
            ->assertActionMounted('move');
    }

    public function test_dropping_on_a_block_says_which_block_without_asking_first(): void
    {
        [$doctor, $treatment] = $this->doctorWithCleaning();
        $appointment = Appointment::factory()->for($doctor)->for($treatment)->at('2026-11-04 10:00')->confirmed()->create();
        ScheduleBlock::query()->create(['doctor_id' => $doctor->id, 'type' => 'course', 'starts_at' => '2026-11-04 15:00', 'ends_at' => '2026-11-04 19:00', 'reason' => 'Congreso']);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(Agenda::class, ['date' => '2026-11-04'])
            ->mountAction('move', arguments: $this->droppedAt($appointment, $doctor, '2026-11-04', '16:00'))
            ->assertNotified('Ese horario cae en un bloqueo de la agenda: Congreso, del 04/11 15:00 al 04/11 19:00. Elige otro.')
            ->assertActionNotMounted('move');

        $this->assertSame('2026-11-04 10:00', $appointment->fresh()->starts_at->format('Y-m-d H:i'));
    }

    public function test_dropping_on_a_time_held_for_someone_else_says_for_whom_and_the_one_it_is_held_for_can_take_it(): void
    {
        [$doctor, $treatment] = $this->doctorWithCleaning();
        $bruno = Patient::factory()->create(['first_name' => 'Bruno', 'last_name' => 'Salas']);
        $other = Appointment::factory()->for($doctor)->for($treatment)->at('2026-11-04 10:00')->confirmed()->create();
        $brunos = Appointment::factory()->for($doctor)->for($treatment)->for($bruno)->at('2026-11-05 10:00')->confirmed()->create();
        WaitlistEntry::factory()->for($treatment)->for($bruno)->offered()->create([
            'offered_starts_at' => '2026-11-04 16:00',
            'offered_until' => '2026-11-04 16:40',
            'offered_doctor_id' => $doctor->id,
            'held_until' => '2026-11-02 10:00',
        ]);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(Agenda::class, ['date' => '2026-11-04'])
            ->mountAction('move', arguments: $this->droppedAt($other, $doctor, '2026-11-04', '16:00'))
            ->assertNotified('Ese horario está apartado para Bruno Salas (lista de espera) hasta las 10:00, mientras responde. Si ya no lo quiere, libéralo en la lista de espera.')
            ->assertActionNotMounted('move');

        // A Bruno ese horario se le ofreció: su cita sí puede ir ahí.
        Livewire::test(Agenda::class, ['date' => '2026-11-04'])
            ->mountAction('move', arguments: $this->droppedAt($brunos, $doctor, '2026-11-04', '16:00'))
            ->assertNotNotified()
            ->assertActionMounted('move');
    }

    public function test_dropping_where_the_patient_is_already_in_another_chair_says_which_appointment(): void
    {
        [$doctor, $treatment] = $this->doctorWithCleaning();
        $ramos = Doctor::factory()->withSchedule()->create(['name' => 'Dr. Ramos']);
        $ramos->treatments()->attach($treatment);
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']);
        $appointment = Appointment::factory()->for($doctor)->for($treatment)->for($rosa)->at('2026-11-04 10:00')->confirmed()->create();
        Appointment::factory()->for($ramos)->for($treatment)->for($rosa)->at('2026-11-04 16:00')->confirmed()->create();
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(Agenda::class, ['date' => '2026-11-04'])
            ->mountAction('move', arguments: $this->droppedAt($appointment, $doctor, '2026-11-04', '16:00'))
            ->assertNotified('Rosa Quispe ya tiene cita a esa hora: Limpieza con Dr. Ramos, de 16:00 a 16:30. Elige otro horario.')
            ->assertActionNotMounted('move');
    }

    public function test_the_time_of_someone_who_did_not_show_up_is_free_to_drop_on(): void
    {
        [$doctor, $treatment] = $this->doctorWithCleaning();
        $appointment = Appointment::factory()->for($doctor)->for($treatment)->at('2026-11-02 10:00')->confirmed()->create();
        Appointment::factory()->for($doctor)->for($treatment)->at('2026-11-02 09:00')->create(['status' => AppointmentStatus::NoShow]);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(Agenda::class)
            ->mountAction('move', arguments: $this->droppedAt($appointment, $doctor, '2026-11-02', '09:00'))
            ->assertNotNotified()
            ->assertActionMounted('move');
    }

    public function test_a_doctor_is_not_asked_about_moving_another_doctors_appointment(): void
    {
        [$mine, $treatment] = $this->doctorWithCleaning();
        $other = $this->doctorOffering($treatment);
        $foreign = Appointment::factory()->for($other)->for($treatment)->for(Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']))->at('2026-11-04 10:00')->confirmed()->create();
        // En la agenda propia hay una cita a esa hora: tampoco se dice con quién se cruzaría.
        Appointment::factory()->for($mine)->for($treatment)->at('2026-11-04 16:00')->confirmed()->create();
        $this->actingAs($mine->user);

        Livewire::test(Agenda::class)
            ->mountAction('move', arguments: $this->droppedAt($foreign, $mine, '2026-11-04', '11:00'))
            ->assertNotNotified()
            ->assertActionNotMounted('move')
            ->mountAction('move', arguments: $this->droppedAt($foreign, $mine, '2026-11-04', '16:00'))
            ->assertNotNotified()
            ->assertActionNotMounted('move');

        $this->assertSame([$other->id, '2026-11-04 10:00'], [$foreign->fresh()->doctor_id, $foreign->fresh()->starts_at->format('Y-m-d H:i')]);
    }

    public function test_the_grid_knows_where_a_dragged_appointment_does_not_fit(): void
    {
        [$doctor, $treatment] = $this->doctorWithCleaning();
        $rosa = Appointment::factory()->for($doctor)->for($treatment)->at('2026-11-02 10:00', buffer: 10)->confirmed()->create();
        // Quien no asistió dejó libre su horario; el feriado es de todo el consultorio; lo apartado, de quien espera.
        Appointment::factory()->for($doctor)->for($treatment)->at('2026-11-02 11:00')->create(['status' => AppointmentStatus::NoShow]);
        ScheduleBlock::query()->create(['doctor_id' => null, 'type' => 'holiday', 'starts_at' => '2026-11-02 16:00', 'ends_at' => '2026-11-02 17:00', 'reason' => 'Fumigación']);
        $hold = WaitlistEntry::factory()->for($treatment)->offered()->create([
            'offered_starts_at' => '2026-11-02 18:00',
            'offered_until' => '2026-11-02 18:40',
            'offered_doctor_id' => $doctor->id,
            'held_until' => '2026-11-02 10:00',
        ]);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(Agenda::class)
            ->assertSeeHtml('data-taken="'.e(json_encode([
                ['from' => 600, 'to' => 640, 'id' => $rosa->id, 'doctor' => $doctor->id, 'patient' => 0, 'buffer' => true],
                ['from' => 960, 'to' => 1020, 'id' => 0, 'doctor' => 0, 'patient' => 0, 'buffer' => false],
                ['from' => 1080, 'to' => 1120, 'id' => 0, 'doctor' => $doctor->id, 'patient' => $hold->patient_id, 'buffer' => true],
            ])).'"')
            // La que se arrastra lleva lo que ocupa: su tiempo para preparar el sillón, su doctor y su paciente.
            ->assertSeeHtml('data-minutes="30"')
            ->assertSeeHtml('data-buffer="10"')
            ->assertSeeHtml('data-doctor="'.$doctor->id.'"')
            ->assertSeeHtml('data-patient="'.$rosa->patient_id.'"');
    }

    public function test_the_whole_clinic_week_knows_whose_each_taken_time_is(): void
    {
        [$ana, $treatment] = $this->doctorWithCleaning();
        $luis = $this->doctorOffering($treatment);
        // Los doctores van en su orden de la agenda: Ana primero.
        $ana->update(['sort_order' => 1]);
        $luis->update(['sort_order' => 2]);
        $withAna = Appointment::factory()->for($ana)->for($treatment)->at('2026-11-03 10:00', buffer: 0)->confirmed()->create();
        $withLuis = Appointment::factory()->for($luis)->for($treatment)->at('2026-11-03 10:00', buffer: 0)->confirmed()->create();
        ScheduleBlock::query()->create(['doctor_id' => null, 'type' => 'holiday', 'starts_at' => '2026-11-03 16:00', 'ends_at' => '2026-11-03 17:00']);
        $this->actingAs(User::factory()->reception()->create());

        // El feriado de todo el consultorio va una sola vez, no una por doctor.
        Livewire::test(Agenda::class)
            ->set('mode', 'week')
            ->set('doctor', 0)
            ->assertSeeHtml('data-taken="'.e(json_encode([
                ['from' => 600, 'to' => 630, 'id' => $withAna->id, 'doctor' => $ana->id, 'patient' => 0, 'buffer' => true],
                ['from' => 960, 'to' => 1020, 'id' => 0, 'doctor' => 0, 'patient' => 0, 'buffer' => false],
                ['from' => 600, 'to' => 630, 'id' => $withLuis->id, 'doctor' => $luis->id, 'patient' => 0, 'buffer' => true],
            ])).'"');
    }

    public function test_the_agenda_says_in_plain_sight_that_an_appointment_is_moved_by_dragging_it(): void
    {
        [$doctor, $treatment] = $this->doctorWithCleaning();
        Appointment::factory()->for($doctor)->for($treatment)->at('2026-11-02 10:00')->confirmed()->create();
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(Agenda::class)
            ->assertSeeHtml('<span class="agenda-tip-mouse">Arrastra una cita para cambiarla de horario.</span>')
            ->assertSeeHtml('<span class="agenda-tip-touch">Mantén presionada una cita y arrástrala para cambiarla de horario.</span>')
            // En la lista no se arrastra.
            ->set('mode', 'list')
            ->assertDontSeeHtml('agenda-tip');
    }

    public function test_the_agenda_does_not_invite_to_drag_when_nothing_on_screen_can_be_moved(): void
    {
        [$doctor, $treatment] = $this->doctorWithCleaning();
        // Ya atendida: no se mueve.
        Appointment::factory()->for($doctor)->for($treatment)->at('2026-11-02 07:00')->create(['status' => AppointmentStatus::Completed]);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(Agenda::class)
            ->assertSee('Colores y ayuda')
            ->assertDontSeeHtml('agenda-tip');
    }

    /** @return array{0: Doctor, 1: Treatment} Un doctor de lunes a sábado que hace limpiezas de media hora, con 10 min para preparar el sillón. */
    private function doctorWithCleaning(): array
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza', 'duration_minutes' => 30, 'buffer_minutes' => 10]);

        return [$this->doctorOffering($treatment), $treatment];
    }

    /**
     * Lo que la grilla manda al soltar una cita en la columna de ese doctor.
     *
     * @return array{id: int, column: string, time: string}
     */
    private function droppedAt(Appointment $appointment, Doctor $doctor, string $date, string $time): array
    {
        return ['id' => $appointment->id, 'column' => "{$doctor->id}|{$date}", 'time' => $time];
    }
}
