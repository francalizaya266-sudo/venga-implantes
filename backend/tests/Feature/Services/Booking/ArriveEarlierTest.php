<?php

namespace Tests\Feature\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WhatsappMessage;
use App\Services\Booking\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** Quien llega antes de su hora puede pasar antes si su doctor está libre, y su hora queda para otro. */
class ArriveEarlierTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private User $reception;

    private Treatment $cleaning;

    private Doctor $doctor;

    /** Rosa tiene Limpieza el lunes a las 11:30; son las 10:05 y el doctor atiende a Luis de 10:00 a 10:30. */
    private Appointment $rosa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->reception = User::factory()->reception()->create();
        $this->cleaning = Treatment::factory()->create(['name' => 'Limpieza', 'duration_minutes' => 30, 'buffer_minutes' => 10]);
        $this->doctor = $this->doctorOffering($this->cleaning);
        Appointment::factory()->for($this->doctor)->for($this->cleaning)->confirmed()->at('2026-11-02 10:00')->create();
        $this->rosa = Appointment::factory()->for($this->doctor)->for($this->cleaning)
            ->for(Patient::factory()->state(['first_name' => 'Rosa', 'last_name' => 'Quispe']))
            ->confirmed()->at('2026-11-02 11:30')->create();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:05', 'America/Lima'));
    }

    public function test_the_first_time_their_doctor_is_free_before_theirs_is_offered(): void
    {
        // Luis termina a las 10:30 y la esterilización a las 10:40: desde ahí está libre.
        $this->assertSame('10:40', $this->service()->earlierRoomToday($this->rosa)?->label());
    }

    public function test_nothing_is_offered_when_their_doctor_is_busy_until_their_time_or_it_is_already_their_time(): void
    {
        Appointment::factory()->for($this->doctor)->for($this->cleaning)->confirmed()->at('2026-11-02 10:40', 40)->create();

        $this->assertNull($this->service()->earlierRoomToday($this->rosa), 'Su doctor está ocupado hasta su hora.');

        $this->travelTo(CarbonImmutable::parse('2026-11-02 11:30', 'America/Lima'));

        $this->assertNull($this->service()->earlierRoomToday($this->rosa), 'Ya es su hora.');
    }

    public function test_it_is_offered_only_when_they_would_go_in_at_least_a_quarter_of_an_hour_earlier(): void
    {
        // El doctor queda libre a las 11:20 (10:40 a 11:10 y la esterilización): 10 minutos antes no vale la pena.
        $before = Appointment::factory()->for($this->doctor)->for($this->cleaning)->confirmed()->at('2026-11-02 10:40', 30)->create();

        $this->assertNull($this->service()->earlierRoomToday($this->rosa));

        // Libre a las 11:15: un cuarto de hora antes, sí.
        $before->update(['ends_at' => '2026-11-02 11:05']);

        $this->assertSame('11:15', $this->service()->earlierRoomToday($this->rosa)?->label());
    }

    public function test_nothing_is_offered_for_another_day_or_when_they_are_already_in_the_room(): void
    {
        $tomorrow = Appointment::factory()->for($this->doctor)->for($this->cleaning)->confirmed()->at('2026-11-03 11:30')->create();
        $this->rosa->update(['status' => AppointmentStatus::CheckedIn]);

        $this->assertNull($this->service()->earlierRoomToday($tomorrow));
        $this->assertNull($this->service()->earlierRoomToday($this->rosa));
    }

    public function test_going_in_earlier_moves_the_appointment_marks_the_arrival_and_tells_reception_who_can_take_the_freed_time(): void
    {
        WaitlistEntry::factory()->for($this->cleaning)->for(Patient::factory()->state(['first_name' => 'Ana', 'last_name' => 'Soto']))->create();

        $moved = $this->service()->arriveEarlier($this->rosa, CarbonImmutable::parse('2026-11-02 10:40'), $this->reception);

        $this->assertSame(
            ['10:40', '11:10', AppointmentStatus::CheckedIn, '10:05'],
            [$moved->starts_at->format('H:i'), $moved->ends_at->format('H:i'), $moved->status, $moved->checked_in_at->format('H:i')],
        );
        // Ya lo sabe: está delante. No se le manda "tu cita cambió".
        $this->assertSame(0, WhatsappMessage::query()->count());

        $alert = $this->reception->notifications()->where('data->title', 'Se liberó un horario')->sole()->data;
        $this->assertStringContainsString('11:30', $alert['body']);
        $this->assertStringContainsString('Ana Soto, de la lista de espera, puede venir a esa hora.', $alert['body']);
    }

    public function test_if_that_time_was_taken_meanwhile_it_says_so_and_nothing_changes(): void
    {
        Appointment::factory()->for($this->doctor)->for($this->cleaning)->confirmed()->at('2026-11-02 10:40')->create();

        try {
            $this->service()->arriveEarlier($this->rosa, CarbonImmutable::parse('2026-11-02 10:40'), $this->reception);
            $this->fail('Se adelantó encima de otra cita.');
        } catch (BookingException $e) {
            $this->assertStringStartsWith('Ese horario se cruza con la cita de', $e->getMessage());
        }

        $this->assertSame(['11:30', AppointmentStatus::Confirmed], [$this->rosa->fresh()->starts_at->format('H:i'), $this->rosa->fresh()->status]);
    }

    private function service(): AppointmentService
    {
        return app(AppointmentService::class);
    }
}
