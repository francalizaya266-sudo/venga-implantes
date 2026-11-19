<?php

namespace Tests\Feature\Filament;

use App\Enums\DocumentType;
use App\Filament\Pages\Agenda;
use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** La lupa junto al DNI: trae de RENIEC los nombres y apellidos para no escribirlos a mano al registrar un paciente. */
class DniLookupTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const URL = 'https://api.decolecta.com/v1/reniec/dni*';

    protected function setUp(): void
    {
        parent::setUp();

        config(['consultorio.dni_lookup.token' => 'clave-de-prueba']);
        $this->actingAs(User::factory()->reception()->create());
    }

    public function test_the_magnifying_glass_fills_the_names_as_reniec_has_them(): void
    {
        Http::fake([self::URL => Http::response([
            'first_name' => 'ROSA MARÍA',
            'first_last_name' => 'DE LA CRUZ',
            'second_last_name' => 'ÑAUPARI',
            'full_name' => 'DE LA CRUZ ÑAUPARI ROSA MARÍA',
            'document_number' => '07654321',
        ])]);

        Livewire::test(CreatePatient::class)
            ->fillForm(['first_name' => 'rosa', 'document_number' => '0765 4321'])
            ->callAction(TestAction::make('lookupDni')->schemaComponent('document_number'))
            ->assertSchemaStateSet(['first_name' => 'Rosa María', 'last_name' => 'De la Cruz Ñaupari'])
            ->assertNotified('Rosa María De la Cruz Ñaupari');

        // Sale el número limpio con la clave, y nada más del paciente.
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.decolecta.com/v1/reniec/dni?numero=07654321'
            && $request->hasHeader('Authorization', 'Bearer clave-de-prueba'));
    }

    public function test_it_also_works_for_a_new_patient_in_the_new_appointment_window(): void
    {
        Http::fake([self::URL => Http::response(['first_name' => 'JUAN', 'first_last_name' => 'QUISPE', 'second_last_name' => 'MAMANI'])]);

        Livewire::test(Agenda::class)
            ->mountAction('createAppointment')
            ->setActionData(['new_patient' => true, 'patient' => ['document_number' => '45678912']])
            ->callAction(TestAction::make('lookupDni')->schemaComponent('patient.document_number'))
            ->assertActionDataSet(['patient.first_name' => 'Juan', 'patient.last_name' => 'Quispe Mamani']);
    }

    public function test_when_reniec_does_not_have_it_or_does_not_answer_the_names_stay_to_be_written(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push(['error' => 'Invalid request'], 400)
            ->push(['message' => 'Too Many Requests'], 429)
            ->push('Server Error', 500)
            ->push(['first_name' => null], 200),
        ]);

        $form = Livewire::test(CreatePatient::class)
            ->fillForm(['first_name' => 'Rosa', 'document_number' => '07654321']);

        $form->callAction(TestAction::make('lookupDni')->schemaComponent('document_number'))
            ->assertNotified('RENIEC no tiene el DNI 07654321.');

        $form->callAction(TestAction::make('lookupDni')->schemaComponent('document_number'))
            ->assertNotified('Se acabaron las consultas de DNI del plan. Escribe los nombres a mano.');

        $form->callAction(TestAction::make('lookupDni')->schemaComponent('document_number'))
            ->assertNotified('La consulta de DNI no respondió. Escribe los nombres a mano o inténtalo en un rato.');

        $form->callAction(TestAction::make('lookupDni')->schemaComponent('document_number'))
            ->assertNotified('RENIEC no tiene el DNI 07654321.')
            ->assertSchemaStateSet(['first_name' => 'Rosa', 'last_name' => null]);
    }

    public function test_an_incomplete_dni_is_not_looked_up(): void
    {
        Http::fake();

        Livewire::test(CreatePatient::class)
            ->fillForm(['document_number' => '0765432'])
            ->callAction(TestAction::make('lookupDni')->schemaComponent('document_number'))
            ->assertNotified('Escribe primero el DNI completo: tiene 8 dígitos.');

        Http::assertNothingSent();
    }

    public function test_the_magnifying_glass_only_shows_for_a_dni_and_with_the_key_set(): void
    {
        Livewire::test(CreatePatient::class)
            ->assertActionVisible(TestAction::make('lookupDni')->schemaComponent('document_number'))
            ->fillForm(['document_type' => DocumentType::Passport->value])
            ->assertActionHidden(TestAction::make('lookupDni')->schemaComponent('document_number'));

        config(['consultorio.dni_lookup.token' => null]);

        Livewire::test(CreatePatient::class)
            ->assertActionHidden(TestAction::make('lookupDni')->schemaComponent('document_number'));
    }
}
