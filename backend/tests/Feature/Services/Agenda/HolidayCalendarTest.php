<?php

namespace Tests\Feature\Services\Agenda;

use App\Services\Agenda\HolidayCalendar;
use App\Support\PeruvianHolidays;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Los feriados del año: los que conoce el sistema más los que trae Nager.Date, y qué pasa si no responde. */
class HolidayCalendarTest extends TestCase
{
    public function test_it_adds_the_holidays_only_nager_date_has_and_keeps_the_names_the_system_uses(): void
    {
        Http::fake(['date.nager.at/*' => Http::response([
            ['date' => '2027-01-01', 'localName' => 'Año Nuevo (Nager)', 'name' => "New Year's Day", 'global' => true, 'counties' => null],
            // Uno que una ley agregó y el sistema todavía no tiene.
            ['date' => '2027-10-31', 'localName' => 'Día de la Canción Criolla', 'name' => 'Creole Song Day', 'global' => true, 'counties' => null],
            // Uno de una sola región y uno de otro año: no se suman.
            ['date' => '2027-03-15', 'localName' => 'Fiesta regional', 'name' => 'Regional', 'global' => false, 'counties' => ['PE-CUS']],
            ['date' => '2026-12-31', 'localName' => 'Otro año', 'name' => 'Other year', 'global' => true, 'counties' => null],
        ])]);

        $calendar = app(HolidayCalendar::class);
        $holidays = $calendar->of(2027);

        $this->assertTrue($calendar->includesNagerDate(2027));
        $this->assertSame('Año Nuevo', $holidays['2027-01-01']);
        $this->assertSame('Día de la Canción Criolla', $holidays['2027-10-31']);
        $this->assertArrayNotHasKey('2027-03-15', $holidays);
        $this->assertArrayNotHasKey('2026-12-31', $holidays);
        $this->assertCount(count(PeruvianHolidays::of(2027)) + 1, $holidays);
        $this->assertSame(collect($holidays)->keys()->sort()->values()->all(), array_keys($holidays));

        // Sale solo el año que se pide.
        Http::assertSent(fn (Request $request) => $request->url() === 'https://date.nager.at/api/v3/PublicHolidays/2027/PE');
    }

    public function test_the_list_is_asked_once_a_day(): void
    {
        Http::fake(['date.nager.at/*' => Http::response([['date' => '2027-01-01', 'localName' => 'Año Nuevo', 'global' => true]])]);

        app(HolidayCalendar::class)->of(2027);
        app(HolidayCalendar::class)->of(2027);
        Http::assertSentCount(1);

        $this->travel(25)->hours();
        app(HolidayCalendar::class)->of(2027);
        Http::assertSentCount(2);
    }

    public function test_when_nager_date_does_not_answer_it_is_the_system_list_and_it_tries_again_in_a_few_minutes(): void
    {
        Http::fake(['date.nager.at/*' => Http::sequence()
            ->pushFailedConnection()
            ->push('Server Error', 500)
            ->push([])
            ->push([['date' => '2027-10-31', 'localName' => 'Día de la Canción Criolla', 'global' => true]]),
        ]);

        foreach (range(1, 3) as $attempt) {
            $calendar = app(HolidayCalendar::class);

            $this->assertSame(PeruvianHolidays::of(2027), $calendar->of(2027));
            $this->assertFalse($calendar->includesNagerDate(2027));

            $this->travel(11)->minutes();
        }

        $this->assertTrue(app(HolidayCalendar::class)->includesNagerDate(2027));
        Http::assertSentCount(4);
    }
}
