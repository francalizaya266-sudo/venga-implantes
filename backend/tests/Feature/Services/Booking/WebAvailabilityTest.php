<?php

namespace Tests\Feature\Services\Booking;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\ScheduleBlock;
use App\Models\Setting;
use App\Models\Treatment;
use App\Services\Agenda\AvailabilityService;
use App\Services\Agenda\Slot;
use App\Services\Booking\WebAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

class WebAvailabilityTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private function availability(): WebAvailability
    {
        return app(WebAvailability::class);
    }

    public function test_offers_only_what_a_doctor_takes_online_with_the_evaluation_first(): void
    {
        $cleaning = Treatment::factory()->create(['sort_order' => 1]);
        $evaluation = Treatment::factory()->evaluation()->create(['sort_order' => 5]);
        $endodontics = Treatment::factory()->clinicOnly()->create();
        $orthodontics = Treatment::factory()->create();
        $this->doctorOffering($cleaning, $evaluation, $endodontics);
        Doctor::factory()->create(['accepts_online_booking' => false])->treatments()->attach($orthodontics);

        $this->assertSame([$evaluation->id, $cleaning->id], $this->availability()->treatments()->pluck('id')->all());
    }

    public function test_lists_days_with_free_times_within_the_clinic_horizon(): void
    {
        $this->freezeMonday();
        $treatment = Treatment::factory()->create(['duration_minutes' => 30]);
        $doctor = $this->doctorOffering($treatment);

        $days = fn () => array_map(fn (CarbonImmutable $day) => $day->format('D d'), $this->availability()->days($treatment, collect([$doctor]), count: 7));

        // El domingo no atiende.
        $this->assertSame(['Mon 02', 'Tue 03', 'Wed 04', 'Thu 05', 'Fri 06', 'Sat 07', 'Mon 09'], $days());

        Setting::set('booking.horizon_days', 3);
        $this->assertSame(['Mon 02', 'Tue 03', 'Wed 04', 'Thu 05'], $days());
    }

    public function test_shows_first_the_times_that_leave_no_useless_gaps(): void
    {
        $slot = fn (string $time, int $waste) => ['time' => $time, 'doctor_id' => 1, 'recommended' => false, 'waste' => $waste];
        $tidy = collect([$slot('09:00', 0), $slot('09:15', 15), $slot('10:00', 0), $slot('11:00', 0)]);
        $few = collect([$slot('09:00', 0), $slot('09:15', 15)]);

        $this->assertSame(['09:00', '10:00', '11:00'], $this->availability()->suggested($tidy)->pluck('time')->all());
        $this->assertSame(['09:00', '09:15'], $this->availability()->suggested($few)->pluck('time')->all());
    }

    public function test_the_web_offers_only_round_hours_while_the_panel_offers_any_gap(): void
    {
        $this->freezeMonday();
        $treatment = Treatment::factory()->create(['duration_minutes' => 30, 'buffer_minutes' => 10]);
        $doctor = $this->doctorOffering($treatment);
        // Ocupa hasta las 9:40 (con la esterilización) y deja la tarde libre solo hasta las 4:20.
        Appointment::factory()->for($doctor)->at('2026-11-03 09:00')->create();
        Appointment::factory()->for($doctor)->at('2026-11-03 17:00')->create();
        $day = CarbonImmutable::parse('2026-11-03');

        $panel = app(AvailabilityService::class)->slotsFor($doctor, $treatment, $day)->map(fn (Slot $slot) => $slot->label());
        $web = $this->availability()->slots($treatment, collect([$doctor]), $day)->pluck('time');

        $this->assertContains('09:40', $panel);
        $this->assertContains('16:20', $panel);
        $this->assertSame(['09:45', '10:00', '10:15'], $web->take(3)->all());
        // La última de la tarde antes de esa cita: 4:15, no 4:20.
        $this->assertSame('16:15', $web->filter(fn (string $time) => $time < '17:00')->last());
        $this->assertSame([], $web->reject(fn (string $time) => in_array(substr($time, 3), ['00', '15', '30', '45'], true))->values()->all());
    }

    public function test_a_day_whose_only_gap_starts_at_an_odd_time_offers_the_next_round_hour_that_fits(): void
    {
        $this->freezeMonday();
        $treatment = Treatment::factory()->create(['duration_minutes' => 30]);
        $doctor = $this->doctorOffering($treatment);
        $this->onlyGap($doctor, '2026-11-04', '10:05', '10:45');

        $this->assertSame(['10:15'], $this->availability()->slots($treatment, collect([$doctor]), CarbonImmutable::parse('2026-11-04'))->pluck('time')->all());
    }

    public function test_a_day_where_no_round_hour_fits_still_offers_its_gap(): void
    {
        $this->freezeMonday();
        $treatment = Treatment::factory()->create(['duration_minutes' => 30]);
        $doctor = $this->doctorOffering($treatment);
        $this->onlyGap($doctor, '2026-11-04', '10:05', '10:40');

        // Redondear no hace desaparecer el día: ese hueco es lo único que tiene.
        $this->assertSame(['10:05', '10:10'], $this->availability()->slots($treatment, collect([$doctor]), CarbonImmutable::parse('2026-11-04'))->pluck('time')->all());
        $this->assertContains('2026-11-04', array_map(fn (CarbonImmutable $day) => $day->toDateString(), $this->availability()->days($treatment, collect([$doctor]), count: 4)));
    }

    public function test_the_clinic_chooses_hours_every_15_or_30_minutes(): void
    {
        $this->freezeMonday();
        $treatment = Treatment::factory()->create(['duration_minutes' => 30]);
        $doctor = $this->doctorOffering($treatment);
        $times = fn () => $this->availability()->slots($treatment, collect([$doctor]), CarbonImmutable::parse('2026-11-03'))->pluck('time')->take(4)->all();

        $this->assertSame(15, $this->availability()->step());
        $this->assertSame(['09:00', '09:15', '09:30', '09:45'], $times());

        Setting::set('booking.web_slot_step_minutes', 30);
        $this->assertSame(['09:00', '09:30', '10:00', '10:30'], $times());

        // Un valor que no es de la lista (puesto a mano) no deja la web con horas raras.
        Setting::set('booking.web_slot_step_minutes', 20);
        $this->assertSame(15, $this->availability()->step());
    }

    /** El día del doctor bloqueado salvo un hueco: lo único libre de ese día. */
    private function onlyGap(Doctor $doctor, string $date, string $from, string $until): void
    {
        foreach ([["{$date} 08:00", "{$date} {$from}"], ["{$date} {$until}", "{$date} 20:00"]] as [$start, $end]) {
            ScheduleBlock::query()->create(['doctor_id' => $doctor->id, 'type' => 'other', 'starts_at' => $start, 'ends_at' => $end, 'reason' => 'Curso']);
        }
    }
}
