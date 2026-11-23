<?php

namespace Tests\Feature\Services\Database;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\MySqlConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Los índices de las consultas frecuentes se crean en cualquier motor (aquí SQLite; en CI, también MySQL) y la
 * migración que los arregla deja bien una base que tenga los viejos de date(…), como una de MySQL 8.
 */
class FrequentQueryIndexesTest extends TestCase
{
    use RefreshDatabase;

    private const FIX = '2026_11_22_214412_fix_frequent_query_indexes_for_every_database.php';

    private function migration(string $file): Migration
    {
        return require database_path('migrations/'.$file);
    }

    /** Un índice sobre date(columna) como el de la versión vieja. MariaDB no los tiene: ahí basta uno normal con su nombre. */
    private function oldDateIndex(string $name, string $table, string $column): void
    {
        $connection = DB::connection();

        DB::statement(match (true) {
            $connection->getDriverName() === 'sqlite' => "create index {$name} on {$table} (date({$column}))",
            $connection instanceof MySqlConnection && ! $connection->isMaria() => "create index {$name} on {$table} ((date({$column})))",
            default => "create index {$name} on {$table} ({$column})",
        });
    }

    public function test_a_new_installation_gets_the_plain_indexes_in_any_database(): void
    {
        $this->assertTrue(Schema::hasIndex('payments', 'payments_patient_balance_index'));
        $this->assertTrue(Schema::hasIndex('charges', 'charges_charged_at_index'));
        $this->assertTrue(Schema::hasIndex('charges', 'charges_patient_balance_index'));
        $this->assertTrue(Schema::hasIndex('appointments', 'appointments_starts_at_index'));
        $this->assertTrue(Schema::hasIndex('payments', 'payments_paid_at_index'));
        $this->assertFalse(Schema::hasIndex('appointments', 'appointments_starts_on_index'));
        $this->assertFalse(Schema::hasIndex('payments', 'payments_paid_on_index'));
    }

    public function test_the_fix_cleans_up_a_database_that_ran_the_old_version_and_can_run_twice(): void
    {
        // Como quedó una base de MySQL 8 con la versión vieja: los de date(…) y sin el de starts_at.
        $this->migration(self::FIX)->down();
        $this->oldDateIndex('appointments_starts_on_index', 'appointments', 'starts_at');
        $this->oldDateIndex('payments_paid_on_index', 'payments', 'paid_at');
        Schema::table('charges', fn ($table) => $table->dropIndex('charges_patient_balance_index'));

        $this->migration(self::FIX)->up();
        $this->migration(self::FIX)->up();

        $this->assertFalse(Schema::hasIndex('appointments', 'appointments_starts_on_index'));
        $this->assertFalse(Schema::hasIndex('payments', 'payments_paid_on_index'));
        $this->assertTrue(Schema::hasIndex('appointments', 'appointments_starts_at_index'));
        $this->assertTrue(Schema::hasIndex('charges', 'charges_patient_balance_index'));
    }

    public function test_the_appointments_of_a_day_are_found_by_a_range_of_hours(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:00', 'America/Lima'));
        $first = Appointment::factory()->create(['starts_at' => '2026-11-23 00:00:00', 'ends_at' => '2026-11-23 00:30:00', 'status' => AppointmentStatus::Pending]);
        $last = Appointment::factory()->create(['starts_at' => '2026-11-23 23:59:59', 'ends_at' => '2026-11-24 00:30:00', 'status' => AppointmentStatus::Pending]);
        Appointment::factory()->create(['starts_at' => '2026-11-22 23:59:59', 'ends_at' => '2026-11-23 00:30:00', 'status' => AppointmentStatus::Pending]);
        Appointment::factory()->create(['starts_at' => '2026-11-24 00:00:00', 'ends_at' => '2026-11-24 00:30:00', 'status' => AppointmentStatus::Pending]);

        $this->assertEqualsCanonicalizing([$first->id, $last->id], Appointment::query()->startsOn(today())->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$first->id, $last->id], Appointment::query()->startsOn('2026-11-23')->pluck('id')->all());

        // Lo mismo que daba whereDate(), pero sin date(starts_at), que no usa ningún índice.
        $this->assertEqualsCanonicalizing(Appointment::query()->whereDate('starts_at', today())->pluck('id')->all(), Appointment::query()->startsOn(today())->pluck('id')->all());
        $this->assertStringNotContainsString('strftime', Appointment::query()->startsOn(today())->toSql());
    }
}
