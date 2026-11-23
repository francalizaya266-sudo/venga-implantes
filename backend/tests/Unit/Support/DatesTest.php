<?php

namespace Tests\Unit\Support;

use App\Support\Dates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatesTest extends TestCase
{
    public static function durations(): array
    {
        return [
            'minutos' => [45, "45\u{00A0}min"],
            'una hora justa' => [60, "1\u{00A0}h"],
            'horas y minutos' => [150, "2\u{00A0}h 30\u{00A0}min"],
            'nada' => [0, "0\u{00A0}min"],
        ];
    }

    #[DataProvider('durations')]
    public function test_a_duration_reads_in_hours_and_minutes(int $minutes, string $expected): void
    {
        $this->assertSame($expected, Dates::duration($minutes));
    }

    public static function times(): array
    {
        return [
            'de mañana' => ['09:30', "9:30\u{00A0}a.\u{00A0}m."],
            'de tarde' => ['15:00', "3:00\u{00A0}p.\u{00A0}m."],
            'mediodía' => ['12:00', "12:00\u{00A0}p.\u{00A0}m."],
        ];
    }

    /** Una sola forma de escribir la hora, la de la web: también la usan los avisos de WhatsApp y el panel. */
    #[DataProvider('times')]
    public function test_a_time_reads_like_on_the_website(string $time, string $expected): void
    {
        $this->assertSame($expected, Dates::time($time));
    }
}
