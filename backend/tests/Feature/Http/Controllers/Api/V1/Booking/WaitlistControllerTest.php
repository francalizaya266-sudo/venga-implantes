<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Booking;

use App\Enums\DayPeriod;
use App\Enums\WaitlistStatus;
use App\Filament\Resources\WaitlistEntries\Pages\ManageWaitlistEntries;
use App\Models\Account;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\RememberedDevice;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\Access\DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** "Avísame si se libera un horario": quien no encuentra horario en la web queda en la lista de espera. */
class WaitlistControllerTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Treatment $cleaning;

    private Doctor $doctor;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        // El frontend vive en el mismo dominio: sus peticiones llevan las cookies (el celular recordado).
        $this->withCredentials();
        $this->cleaning = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $this->doctor = $this->doctorOffering($this->cleaning);
        $this->doctor->update(['name' => 'Dra. Vega']);
        $this->reception = User::factory()->reception()->create();
    }

    /** Una persona nueva que no encontró horario: quiere la limpieza con la Dra. Vega, en la mañana. */
    private function asking(array $overrides = []): array
    {
        return $overrides + [
            'treatment_id' => $this->cleaning->id,
            'doctor_id' => $this->doctor->id,
            'phone' => '987 654 321',
            'first_name' => 'María',
            'last_name' => 'Quispe Rojas',
            'preferred_period' => DayPeriod::Morning->value,
            'days' => 'lunes o sábados',
            'accepts_privacy' => true,
        ];
    }

    private function ask(array $data): TestResponse
    {
        return $this->postJson(route('api.v1.booking.waitlist'), $data);
    }

    public function test_who_finds_no_time_leaves_their_details_and_reception_sees_them_from_the_web(): void
    {
        $this->ask($this->asking())
            ->assertCreated()
            ->assertExactJson(['message' => 'Listo, te anotamos. Si se libera un horario, te escribimos por WhatsApp al 987 654 321.']);

        $entry = WaitlistEntry::with('patient.account')->sole();
        $this->assertTrue($entry->from_web);
        $this->assertSame(WaitlistStatus::Waiting, $entry->status);
        $this->assertSame(DayPeriod::Morning, $entry->preferred_period);
        $this->assertSame([$this->cleaning->id, $this->doctor->id], [$entry->treatment_id, $entry->doctor_id]);
        $this->assertSame('Días que le sirven: lunes o sábados', $entry->notes);
        $this->assertSame('2026-11-02', $entry->earliest_date->toDateString());
        $this->assertSame(['María Quispe Rojas', '+51987654321'], [$entry->patient->full_name, $entry->patient->account->phone]);
        $this->assertNotNull($entry->patient->account->privacy_accepted_at);

        // Recepción recibe el aviso en la campana, como con las reservas web.
        $alert = $this->reception->notifications()->sole();
        $this->assertSame('Lista de espera desde la web', $alert->data['title']);
        $this->assertSame('María Quispe Rojas · Limpieza dental con Dra. Vega · en la mañana. No encontró un horario que le sirva: ofrécele uno cuando se libere.', $alert->data['body']);

        // Y en la lista de espera sale marcado.
        $this->actingAs($this->reception);
        Livewire::test(ManageWaitlistEntries::class)
            ->assertCanSeeTableRecords([$entry])
            ->assertSee('987 654 321 · Desde la web');
    }

    public function test_asking_again_updates_what_they_wait_for_instead_of_adding_them_twice(): void
    {
        $this->ask($this->asking())->assertCreated();
        // Con otra forma de escribir el nombre es la misma persona.
        $this->ask($this->asking(['first_name' => 'maria', 'doctor_id' => null, 'preferred_period' => DayPeriod::Any->value, 'days' => null]))->assertCreated();

        $entry = WaitlistEntry::sole();
        $this->assertSame([null, DayPeriod::Any, 'Días que le sirven: lunes o sábados'], [$entry->doctor_id, $entry->preferred_period, $entry->notes]);
        $this->assertSame(1, Patient::count());
        $this->assertSame(1, $this->reception->notifications()->count());
    }

    public function test_it_asks_for_the_same_details_as_booking(): void
    {
        $this->ask($this->asking(['phone' => '12345', 'first_name' => '', 'accepts_privacy' => false, 'preferred_period' => 'night']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'first_name', 'preferred_period'])
            ->assertJsonValidationErrors(['accepts_privacy' => 'Para anotarte, acepta la política de privacidad.']);

        $evaluationOnly = Treatment::factory()->clinicOnly()->create();
        $this->ask($this->asking(['treatment_id' => $evaluationOnly->id]))
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'Este tratamiento se agenda después de una evaluación. Puedes reservar una evaluación.']);

        Account::factory()->create(['phone' => '+51987654321', 'blocked_at' => now()]);
        $this->ask($this->asking())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No podemos reservar desde la web para este número. Escríbenos por WhatsApp y te ayudamos.');

        $this->assertDatabaseCount('waitlist_entries', 0);
    }

    public function test_a_doctor_who_does_not_do_the_treatment_is_not_kept(): void
    {
        $other = Doctor::factory()->create();

        $this->ask($this->asking(['doctor_id' => $other->id]))->assertCreated();

        $this->assertNull(WaitlistEntry::sole()->doctor_id);
    }

    public function test_many_requests_from_the_same_connection_are_stopped_for_a_while(): void
    {
        foreach (range(1, 5) as $try) {
            $this->ask($this->asking(['first_name' => 'Persona '.$try]))->assertCreated();
        }

        $this->ask($this->asking(['first_name' => 'Persona 6']))
            ->assertTooManyRequests()
            ->assertExactJson(['message' => 'Te anotaste varias veces seguidas. Espera un rato o escríbenos por WhatsApp.']);
        $this->assertDatabaseCount('waitlist_entries', 5);
    }

    public function test_from_a_remembered_phone_it_does_not_ask_the_number_and_can_be_for_a_relative(): void
    {
        $account = Account::factory()->verified()->create(['phone' => '+51912345678', 'privacy_accepted_at' => now()->subMonth()]);
        Patient::factory()->for($account)->create(['first_name' => 'Rosa']);
        $child = Patient::factory()->dependentOf($account)->create(['first_name' => 'Mateo']);
        $stranger = Patient::factory()->create();
        RememberedDevice::create(['account_id' => $account->id, 'token_hash' => hash('sha256', 'celular-de-rosa'), 'expires_at' => now()->addMonth(), 'verified_at' => now()]);
        $this->withCookie(DeviceService::COOKIE, 'celular-de-rosa');

        $this->ask(['treatment_id' => $this->cleaning->id, 'patient_id' => $child->id, 'preferred_period' => DayPeriod::Afternoon->value])
            ->assertCreated()
            ->assertJsonPath('message', 'Listo, te anotamos. Si se libera un horario, te escribimos por WhatsApp al 912 345 678.');
        $this->assertTrue(WaitlistEntry::sole()->patient->is($child));

        $this->ask(['treatment_id' => $this->cleaning->id, 'patient_id' => $stranger->id, 'preferred_period' => DayPeriod::Any->value])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No encontramos a esa persona en este celular. Elige de nuevo para quién es.');
        $this->assertDatabaseCount('waitlist_entries', 1);
    }

    public function test_choosing_a_registered_relative_needs_the_remembered_phone(): void
    {
        $account = Account::factory()->verified()->create(['phone' => '+51987654321']);
        $mother = Patient::factory()->for($account)->create();

        $this->ask($this->asking(['patient_id' => $mother->id]))
            ->assertForbidden()
            ->assertJsonPath('message', 'Para elegir a un familiar registrado, abre la web desde tu enlace de WhatsApp.');
        $this->assertDatabaseCount('waitlist_entries', 0);
    }
}
