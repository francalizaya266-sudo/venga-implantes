<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Account;

use App\Enums\Sex;
use App\Filament\Support\ClinicalAlerts;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\RememberedDevice;
use App\Services\Access\DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Account $account;

    private Patient $mother;

    private Patient $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        // El frontend vive en el mismo dominio: sus peticiones llevan las cookies (el celular recordado).
        $this->withCredentials();
        $this->account = Account::factory()->verified()->create(['phone' => '+51987654321']);
        $this->mother = Patient::factory()->for($this->account)->create(['first_name' => 'Rosa', 'sex' => Sex::Female]);
        $this->child = Patient::factory()->dependentOf($this->account)->create(['first_name' => 'Mateo', 'birth_date' => '2019-05-01']);
        RememberedDevice::create(['account_id' => $this->account->id, 'token_hash' => hash('sha256', 'este-celular'), 'expires_at' => now()->addMonth()]);
        $this->withCookie(DeviceService::COOKIE, 'este-celular');
    }

    public function test_my_account_asks_once_per_person_on_their_first_appointment(): void
    {
        Appointment::factory()->for($this->mother)->at('2026-11-04 10:00')->create();
        Appointment::factory()->for($this->mother)->at('2026-11-11 10:00')->create();

        $this->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.upcoming.0.ask_health', ['patient_id' => $this->mother->id, 'text' => 'Cuéntanos de tu salud en dos minutos.'])
            ->assertJsonPath('data.upcoming.1.ask_health', null);
    }

    public function test_the_questions_are_worded_for_who_answers_and_fit_the_patient(): void
    {
        $this->getJson(route('api.v1.account.patients.health', $this->mother))
            ->assertOk()
            ->assertJsonPath('data.title', 'Antes de tu cita')
            ->assertJsonPath('data.questions.*.question', ['¿Tienes alguna alergia?', '¿Tomas alguna medicina todos los días?'])
            ->assertJsonPath('data.also.*.label', ['Estoy embarazada', 'Estoy dando de lactar', 'Fumo'])
            ->assertJsonFragment(['key' => 'hypertension', 'label' => 'Hipertensión'])
            ->assertJsonPath('meta.noindex', true);

        // A un niño no se le pregunta por embarazo ni si fuma.
        $this->getJson(route('api.v1.account.patients.health', $this->child))
            ->assertOk()
            ->assertJsonPath('data.title', 'Antes de la cita de Mateo')
            ->assertJsonPath('data.questions.0.question', '¿Mateo tiene alguna alergia?')
            ->assertJsonPath('data.also', []);
    }

    public function test_the_family_tells_the_doctor_about_their_health_once(): void
    {
        // "Sí" sin decir a qué no se acepta.
        $this->postJson(route('api.v1.account.patients.health.store', $this->mother), ['has_allergies' => 'yes', 'takes_medications' => 'no'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['allergies' => 'Escribe a qué.']);
        $this->postJson(route('api.v1.account.patients.health.store', $this->mother), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['has_allergies' => 'Elige No o Sí.', 'takes_medications' => 'Elige No o Sí.']);

        $this->postJson(route('api.v1.account.patients.health.store', $this->mother), [
            'has_allergies' => 'yes',
            'allergies' => ' Penicilina ',
            'takes_medications' => 'no',
            'medications' => 'quedó escrito antes de elegir No',
            'conditions' => ['hypertension'],
            'is_pregnant' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Gracias. El doctor lo tendrá a la vista y lo repasará contigo en la cita.');

        $background = $this->mother->medicalBackground()->sole();
        $this->assertSame('Penicilina', $background->allergies);
        $this->assertSame('Ninguna', $background->medications);
        $this->assertSame(['hypertension'], $background->conditions);
        $this->assertTrue($background->is_pregnant);
        $this->assertFalse($background->smoker);
        $this->assertNull($background->updated_by_user_id);

        // En el consultorio se ve que lo llenó el paciente, con las alertas de siempre.
        $this->assertSame(['Alergia: Penicilina', 'Hipertensión', 'Embarazada'], ClinicalAlerts::for($this->mother->fresh()));
        $this->assertSame('Los llenó el paciente desde la web el 02/11/2026. Confírmalos en la consulta y guárdalos.', ClinicalAlerts::filledByPatient($this->mother->fresh()));

        // Ya no se pide, y lo registrado no se ve ni se cambia desde la web.
        $known = ['message' => 'El consultorio ya tiene los antecedentes de Rosa. Si algo cambió, cuéntaselo al doctor en la cita.', 'code' => 'already_known'];
        $this->getJson(route('api.v1.account.patients.health', $this->mother))->assertConflict()->assertExactJson($known);
        $this->postJson(route('api.v1.account.patients.health.store', $this->mother), ['has_allergies' => 'no', 'takes_medications' => 'no'])->assertConflict();
        $this->assertSame('Penicilina', $this->mother->medicalBackground()->sole()->allergies);
    }

    public function test_a_child_is_not_registered_as_pregnant_or_smoker_whatever_is_sent(): void
    {
        $this->postJson(route('api.v1.account.patients.health.store', $this->child), ['has_allergies' => 'no', 'takes_medications' => 'no', 'is_pregnant' => true, 'smoker' => true])
            ->assertCreated()
            ->assertJsonPath('message', 'Gracias. El doctor lo tendrá a la vista y lo repasará con Mateo en la cita.');

        $background = $this->child->medicalBackground()->sole();
        $this->assertFalse($background->is_pregnant);
        $this->assertNull($background->smoker);
    }

    public function test_another_phone_cannot_see_or_fill_the_health_of_this_family(): void
    {
        $other = Account::factory()->verified()->create(['phone' => '+51912345678']);
        RememberedDevice::create(['account_id' => $other->id, 'token_hash' => hash('sha256', 'otro-celular'), 'expires_at' => now()->addMonth()]);
        $this->withCookie(DeviceService::COOKIE, 'otro-celular');

        $this->getJson(route('api.v1.account.patients.health', $this->child))->assertForbidden();
        $this->postJson(route('api.v1.account.patients.health.store', $this->child), ['has_allergies' => 'no', 'takes_medications' => 'no'])->assertForbidden();

        $this->assertNull($this->child->medicalBackground()->first());
    }
}
