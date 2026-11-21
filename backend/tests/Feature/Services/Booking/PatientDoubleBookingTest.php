<?php

namespace Tests\Feature\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Agenda\AvailabilityService;
use App\Services\Agenda\Slot;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\RescheduleRequestService;
use App\Services\Booking\WebBookingData;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** El mismo paciente no puede quedar a la misma hora con dos doctores: no puede estar en dos sillones a la vez. */
class PatientDoubleBookingTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private User $reception;

    private Treatment $treatment;

    private Doctor $ana;

    private Doctor $luis;

    private Patient $rosa;

    /** Rosa ya tiene Limpieza con la Dra. Ana el martes de 10:00 a 10:30. */
    private Appointment $withAna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->reception = User::factory()->reception()->create();
        $this->treatment = Treatment::factory()->create(['name' => 'Limpieza', 'duration_minutes' => 30]);
        $this->ana = $this->doctorOffering($this->treatment);
        $this->ana->update(['name' => 'Dra. Ana Torres']);
        $this->luis = $this->doctorOffering($this->treatment);
        $this->rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']);
        $this->withAna = Appointment::factory()->for($this->ana)->for($this->treatment)->for($this->rosa)->confirmed()->at('2026-11-03 10:00')->create();
    }

    public function test_reception_cannot_book_them_with_another_doctor_at_a_time_that_overlaps_not_even_as_an_overbooking(): void
    {
        $busy = 'Rosa Quispe ya tiene cita a esa hora: Limpieza con Dra. Ana Torres, de 10:00 a 10:30. Elige otro horario.';

        $this->assertSame($busy, $this->refusal(fn () => $this->bookWithLuis('2026-11-03 10:15')));
        $this->assertSame($busy, $this->refusal(fn () => $this->bookWithLuis('2026-11-03 09:45')));
        $this->assertSame($busy, $this->refusal(fn () => $this->bookWithLuis('2026-11-03 10:00', ['overbook' => true])));

        // Justo antes o justo después sí: la esterilización es tiempo del doctor, no del paciente.
        $this->bookWithLuis('2026-11-03 09:30');
        $this->bookWithLuis('2026-11-03 10:30');

        $this->assertSame(3, Appointment::query()->where('patient_id', $this->rosa->id)->count());
    }

    public function test_the_agenda_does_not_offer_them_those_times_but_still_offers_them_to_other_patients(): void
    {
        $day = CarbonImmutable::parse('2026-11-03');
        $forRosa = app(AvailabilityService::class)->forPatient($this->rosa->id)->slotsFor($this->luis, $this->treatment, $day)->map(fn (Slot $slot) => $slot->label());
        $forAnyone = app(AvailabilityService::class)->forPatient(Patient::factory()->create()->id)->slotsFor($this->luis, $this->treatment, $day)->map(fn (Slot $slot) => $slot->label());

        $this->assertEmpty($forRosa->intersect(['09:45', '10:00', '10:15'])->all());
        $this->assertContains('09:30', $forRosa);
        $this->assertContains('10:30', $forRosa);
        $this->assertContains('10:00', $forAnyone);
    }

    public function test_a_cancelled_or_attended_appointment_does_not_hold_the_patient(): void
    {
        $this->withAna->update(['status' => AppointmentStatus::Cancelled]);
        $this->bookWithLuis('2026-11-03 10:00');

        // Hoy la atendieron de 9:00 a 9:20 en una cita de una hora y se fue: puede pasar enseguida con otro doctor.
        Appointment::factory()->for($this->ana)->for($this->treatment)->for($this->rosa)->at('2026-11-02 09:00', 60)->create(['status' => AppointmentStatus::Completed]);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:20', 'America/Lima'));

        $walkIn = app(AppointmentService::class)->walkIn($this->reception, $this->rosa, $this->luis, $this->treatment);

        $this->assertSame('09:30', $walkIn->starts_at->format('H:i'));
    }

    public function test_moving_another_of_their_appointments_onto_that_time_is_refused_but_moving_the_same_one_a_little_is_not(): void
    {
        $service = app(AppointmentService::class);
        $withLuis = $this->bookWithLuis('2026-11-03 16:00');

        $this->assertSame(
            'Rosa Quispe ya tiene cita a esa hora: Limpieza con Dra. Ana Torres, de 10:00 a 10:30. Elige otro horario.',
            $this->refusal(fn () => $service->rescheduleByStaff($withLuis, CarbonImmutable::parse('2026-11-03 10:15'), $this->reception)),
        );
        $this->assertSame('16:00', $withLuis->fresh()->starts_at->format('H:i'));

        $moved = $service->rescheduleByStaff($this->withAna, CarbonImmutable::parse('2026-11-03 10:15'), $this->reception);

        $this->assertSame('10:15', $moved->starts_at->format('H:i'));
    }

    public function test_from_the_web_the_patient_is_told_they_already_have_an_appointment_then(): void
    {
        $service = app(AppointmentService::class);
        $web = fn (Doctor $doctor, string $startsAt) => $service->bookFromWeb(new WebBookingData(
            doctorId: $doctor->id,
            treatmentId: $this->treatment->id,
            startsAt: CarbonImmutable::parse($startsAt),
            phone: '987 654 321',
            firstName: 'María',
            lastName: 'Huamán',
            acceptsPrivacy: true,
        ));
        $this->treatment->update(['online_bookable' => true]);
        $first = $web($this->ana, '2026-11-04 10:00');

        $this->assertSame('Ya tienes una cita a esa hora. Elige otro horario.', $this->refusal(fn () => $web($this->luis, '2026-11-04 10:00')));
        $this->assertSame(1, Appointment::query()->where('patient_id', $first->patient_id)->count());

        // Lo mismo al mover su cita o al pedir el cambio desde su enlace.
        $withLuis = $this->bookWithLuis('2026-11-05 16:00');

        $this->assertSame('Ya tienes una cita a esa hora. Elige otro horario.', $this->refusal(fn () => $service->rescheduleByPatient($withLuis, CarbonImmutable::parse('2026-11-03 10:00'))));
        $this->assertSame('Ya tienes una cita a esa hora. Elige otro horario.', $this->refusal(fn () => app(RescheduleRequestService::class)->requestByPatient($withLuis, [CarbonImmutable::parse('2026-11-03 10:00')])));
    }

    public function test_repeated_appointments_take_the_nearest_free_time_in_the_week_they_would_clash(): void
    {
        // Sus controles con el Dr. Luis, los miércoles a las 10:00; el próximo miércoles ya tiene esa hora con la Dra. Ana.
        Appointment::factory()->for($this->ana)->for($this->treatment)->for($this->rosa)->confirmed()->at('2026-11-11 10:00')->create();

        $result = app(AppointmentService::class)->bookSeries($this->reception, $this->rosa, $this->luis, $this->treatment, CarbonImmutable::parse('2026-11-04 10:00'), 1, 2);

        $this->assertSame(
            ['2026-11-04 10:00', '2026-11-11 10:30'],
            array_map(fn (Appointment $appointment) => $appointment->starts_at->format('Y-m-d H:i'), $result['booked']),
        );
    }

    public function test_a_cancelled_appointment_does_not_come_back_onto_a_time_they_took_with_another_doctor(): void
    {
        $service = app(AppointmentService::class);
        $service->cancelByStaff($this->withAna, $this->reception);
        $this->bookWithLuis('2026-11-03 10:00');

        $this->assertSame(
            'Rosa Quispe ya tiene cita a esa hora: Limpieza con '.$this->luis->name.', de 10:00 a 10:30. Elige otro horario.',
            $this->refusal(fn () => $service->undoLastStep($this->withAna->fresh(), $this->reception)),
        );
        $this->assertSame(AppointmentStatus::Cancelled, $this->withAna->fresh()->status);
    }

    /** @param  array<string, mixed>  $options */
    private function bookWithLuis(string $startsAt, array $options = []): Appointment
    {
        return app(AppointmentService::class)->bookFromStaff($this->reception, $this->rosa, $this->luis, $this->treatment, CarbonImmutable::parse($startsAt), $options);
    }

    /** Por qué no se pudo. */
    private function refusal(Closure $attempt): string
    {
        try {
            $attempt();
        } catch (BookingException $e) {
            return $e->getMessage();
        }

        return 'Se hizo.';
    }
}
