<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Booking;

use App\Enums\AppointmentOrigin;
use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Enums\DiscountType;
use App\Enums\MessageTemplateKey;
use App\Enums\PatientRelationship;
use App\Enums\TreatmentPlanStatus;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Campaign;
use App\Models\Patient;
use App\Models\RememberedDevice;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Access\DeviceService;
use App\Services\WhatsApp\WhatsAppOutbox;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El frontend vive en el mismo dominio: sus peticiones llevan las cookies (el celular recordado).
        $this->withCredentials();
    }

    /** Los datos de una persona nueva que reserva para sí misma, el lunes a las 10:00. */
    private function booking(Treatment $treatment, array $overrides = []): array
    {
        return $overrides + [
            'treatment_id' => $treatment->id,
            'date' => '2026-11-02',
            'time' => '10:00',
            'phone' => '987 654 321',
            'first_name' => 'María',
            'last_name' => 'Quispe Rojas',
            'who' => 'me',
            'relationship' => PatientRelationship::Child->value,
            'accepts_privacy' => true,
        ];
    }

    private function book(array $data): TestResponse
    {
        return $this->postJson(route('api.v1.booking.store'), $data);
    }

    /** El celular recordado de una familia: la madre (titular) y su hijo. */
    private function rememberedFamily(): array
    {
        $account = Account::factory()->verified()->create(['phone' => '+51987654321']);
        $mother = Patient::factory()->for($account)->create(['first_name' => 'Rosa']);
        $child = Patient::factory()->dependentOf($account)->create(['first_name' => 'Mateo']);
        RememberedDevice::create(['account_id' => $account->id, 'token_hash' => hash('sha256', 'celular-de-rosa'), 'expires_at' => now()->addMonth(), 'verified_at' => now()]);
        $this->withCookie(DeviceService::COOKIE, 'celular-de-rosa');

        return [$account, $mother, $child];
    }

    public function test_the_first_step_offers_only_what_is_booked_online_with_its_price_and_duration(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->evaluation()->create(['slug' => 'evaluacion', 'price_from' => 50, 'summary' => 'Revisión completa y presupuesto.']);
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'slug' => 'limpieza-dental', 'price_from' => 120, 'duration_minutes' => 45]);
        $endodontics = Treatment::factory()->clinicOnly()->create(['name' => 'Endodoncia']);
        $this->doctorOffering($evaluation, $cleaning, $endodontics);

        $this->getJson(route('api.v1.booking.options'))
            ->assertOk()
            ->assertJsonCount(2, 'data.treatments')
            ->assertJsonPath('data.treatments.0.label', 'No sé qué necesito: '.mb_strtolower($evaluation->name))
            ->assertJsonPath('data.treatments.0.price', ['was' => null, 'now' => 'desde S/ 50'])
            ->assertJsonPath('data.treatments.0.summary', 'Revisión completa y presupuesto.')
            ->assertJsonPath('data.treatments.1.label', 'Limpieza dental')
            ->assertJsonPath('data.treatments.1.art', 'limpieza')
            ->assertJsonPath('data.treatments.1.duration_minutes', 45)
            ->assertJsonPath('data.initial_treatment_id', null)
            ->assertJsonPath('data.device', null)
            ->assertJsonPath('meta.og_title', 'Reservar cita');

        // Desde la página de un servicio o de un doctor, llega con eso ya elegido.
        $doctor = $cleaning->doctors->first();
        $this->getJson(route('api.v1.booking.options', ['tratamiento' => 'limpieza-dental', 'doctor' => $doctor->slug, 'origen' => 'instagram']))
            ->assertOk()
            ->assertJsonPath('data.initial_treatment_id', $cleaning->id)
            ->assertJsonPath('data.preferred_doctor_id', $doctor->id)
            ->assertJsonPath('data.origin', 'instagram');
    }

    public function test_a_new_patient_books_with_spanish_errors(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->evaluation()->create();
        $doctor = $this->doctorOffering($evaluation);

        // Con 2 horas de anticipación mínima, lo primero es hoy a las 10:00.
        $this->getJson(route('api.v1.booking.days', ['treatment' => $evaluation->id]))
            ->assertOk()
            ->assertJsonPath('data.date', '2026-11-02')
            ->assertJsonPath('data.days.0', ['date' => '2026-11-02', 'weekday' => 'Hoy', 'number' => 2, 'month' => 'nov.', 'label' => 'Lunes 2 de noviembre'])
            ->assertJsonPath('data.slots.0.time', '10:00')
            ->assertJsonPath('data.doctors.*.id', [$doctor->id]);

        $this->book(['treatment_id' => $evaluation->id, 'date' => '2026-11-02', 'time' => '10:00', 'relationship' => PatientRelationship::Child->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'first_name', 'last_name'])
            ->assertJsonValidationErrors(['accepts_privacy' => 'Para reservar, acepta la política de privacidad.']);

        $this->book($this->booking($evaluation, ['phone' => '98765']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => 'El celular tiene 9 dígitos y escribiste 5.']);

        $this->book($this->booking($evaluation))
            ->assertCreated()
            ->assertJsonPath('data.treatment', $evaluation->name)
            ->assertJsonPath('data.phone', '987 654 321')
            ->assertJsonPath('data.remembered', true)
            ->assertJsonPath('data.who', 'María Quispe Rojas · con '.$doctor->name);

        $appointment = Appointment::with('patient.account')->sole();
        $this->assertTrue($appointment->doctor->is($doctor));
        $this->assertSame('2026-11-02 10:00', $appointment->starts_at->format('Y-m-d H:i'));
        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertSame(BookingChannel::Web, $appointment->booked_via);
        $this->assertSame('María', $appointment->patient->first_name);
        $this->assertSame('+51987654321', $appointment->patient->account->phone);
        $this->assertSame(PatientRelationship::Self, $appointment->patient->relationship);
    }

    public function test_a_first_booking_with_a_new_number_enters_my_account_without_waiting_for_the_link(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->evaluation()->create();
        $this->doctorOffering($evaluation);

        $response = $this->book($this->booking($evaluation))
            ->assertCreated()
            ->assertJsonPath('data.remembered', true)
            ->assertCookie(DeviceService::COOKIE);
        $phone = $response->getCookie(DeviceService::COOKIE)->getValue();

        $appointment = Appointment::with('patient.account')->sole();
        $device = RememberedDevice::sole();
        $this->assertTrue($device->account->is($appointment->patient->account));
        // Nadie tocó todavía un enlace del número: lo que reserva no cuenta como hecho desde el celular de la familia.
        $this->assertFalse($device->isVerified());
        $this->assertFalse($appointment->from_verified_device);

        // Mi cuenta ya lo reconoce: ve su cita. Sus citas pasadas y sus datos siguen pidiendo el enlace.
        $this->withCookie(DeviceService::COOKIE, $phone)
            ->getJson(route('api.v1.account.show'))
            ->assertOk()
            ->assertJsonPath('data.guest', false)
            ->assertJsonPath('data.upcoming.0.id', $appointment->id)
            ->assertJsonPath('data.elevated', false)
            ->assertJsonPath('data.past', null);

        // Y todavía puede contar cómo nos conoció, aunque ya quedó recordado.
        $this->withCookie(DeviceService::COOKIE, $phone)
            ->postJson($response->json('data.origin_url'), ['origin' => AppointmentOrigin::Facebook->value])
            ->assertOk();
        $this->assertSame(AppointmentOrigin::Facebook, $appointment->fresh()->origin);
    }

    public function test_a_number_the_clinic_already_has_is_not_remembered_without_its_link(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->evaluation()->create();
        $this->doctorOffering($evaluation);
        Patient::factory()->for(Account::factory()->create(['phone' => '+51987654321']))->create(['first_name' => 'Rosa']);

        // Ese número puede no ser de quien lo escribe: su cuenta no se abre hasta que toque un enlace que llega a ese WhatsApp.
        $this->book($this->booking($evaluation))
            ->assertCreated()
            ->assertJsonPath('data.remembered', false)
            ->assertCookieMissing(DeviceService::COOKIE);

        $this->assertSame(0, RememberedDevice::count());
    }

    public function test_the_birth_date_comes_in_three_fields_and_an_incomplete_one_is_rejected(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->evaluation()->create();
        $this->doctorOffering($evaluation);

        $this->book($this->booking($evaluation, ['birth_day' => '31', 'birth_month' => '2', 'birth_year' => '1985']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['birth_date' => 'Completa el día, el mes y el año de nacimiento.']);

        $this->book($this->booking($evaluation, ['birth_day' => '7', 'birth_month' => '2', 'birth_year' => '1985']))->assertCreated();

        $this->assertSame('1985-02-07', Patient::sole()->birth_date->toDateString());
    }

    public function test_the_first_available_doctor_takes_the_time_and_a_chosen_doctor_only_offers_theirs(): void
    {
        $this->freezeMonday();
        $cleaning = Treatment::factory()->create(['duration_minutes' => 30]);
        $busy = $this->doctorOffering($cleaning);
        $free = $this->doctorOffering($cleaning);
        Appointment::factory()->for($busy)->at('2026-11-02 10:00')->create();

        $this->book($this->booking($cleaning))->assertCreated();
        $this->assertTrue(Appointment::latest('id')->first()->doctor->is($free));

        // Con un doctor elegido solo se ven sus horarios: a las 10:00 ya no tiene.
        $this->getJson(route('api.v1.booking.slots', ['treatment' => $cleaning->id, 'doctor' => $busy->id, 'date' => '2026-11-02']))
            ->assertOk()
            ->assertJsonMissing(['time' => '10:00'])
            ->assertJsonPath('data.slots.0.doctor_id', $busy->id);

        $this->book($this->booking($cleaning, ['doctor_id' => $busy->id, 'phone' => '912 345 678']))
            ->assertConflict()
            ->assertJsonPath('code', 'slot_taken');
    }

    public function test_a_day_tapped_on_the_website_arrives_chosen_unless_it_has_no_free_times(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->evaluation()->create();
        $this->doctorOffering($evaluation);

        $this->getJson(route('api.v1.booking.days', ['treatment' => $evaluation->id, 'date' => '2026-11-03']))
            ->assertOk()
            ->assertJsonPath('data.date', '2026-11-03')
            ->assertJsonPath('data.day', 'Martes 3 de noviembre');

        // Un día sin atención (domingo) no se impone: se ofrece el primero libre.
        $this->getJson(route('api.v1.booking.days', ['treatment' => $evaluation->id, 'date' => '2026-11-08']))
            ->assertOk()
            ->assertJsonPath('data.date', '2026-11-02');

        // Un servicio que no se reserva en la web no tiene horarios que ofrecer.
        $this->getJson(route('api.v1.booking.days', ['treatment' => Treatment::factory()->clinicOnly()->create()->id]))->assertNotFound();
    }

    public function test_arriving_from_a_campaign_shows_its_price_books_with_it_and_the_whatsapp_says_it(): void
    {
        $this->freezeMonday();
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'price_from' => 150]);
        $evaluation = Treatment::factory()->evaluation()->create(['price_from' => 50]);
        $this->doctorOffering($cleaning, $evaluation);
        $campaign = Campaign::query()->create(['name' => 'Limpieza de verano', 'slug' => 'limpieza-verano', 'treatment_id' => $cleaning->id, 'discount_type' => DiscountType::Amount, 'discount_value' => 30, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);

        $options = $this->getJson(route('api.v1.booking.options', ['promo' => 'limpieza-verano']))
            ->assertOk()
            ->assertJsonPath('data.initial_treatment_id', $cleaning->id)
            ->assertJsonPath('data.campaign.name', 'Limpieza de verano')
            ->assertJsonPath('data.campaign_slug', 'limpieza-verano');

        // En el primer paso, el precio normal va tachado junto al de la promoción.
        $offered = collect($options->json('data.treatments'))->firstWhere('id', $cleaning->id);
        $this->assertSame(['was' => 'S/ 150', 'now' => 'desde S/ 120'], $offered['price']);
        $this->assertSame('Precio con la promoción Limpieza de verano: desde S/ 120 (normal desde S/ 150).', $offered['price_line']);

        $this->book($this->booking($cleaning, ['campaign_slug' => 'limpieza-verano']))
            ->assertCreated()
            ->assertJsonPath('data.price_line', 'Precio con la promoción Limpieza de verano: desde S/ 120 (normal desde S/ 150).');

        $this->assertTrue(Appointment::sole()->campaign->is($campaign));

        $confirmation = app(WhatsAppOutbox::class)->render(WhatsappMessage::where('template_key', MessageTemplateKey::BookingConfirmation)->sole());
        $this->assertStringContainsString("a las 10:00\u{00A0}a.\u{00A0}m. con {$cleaning->doctors->first()->name}.\nPrecio con la promoción Limpieza de verano: desde S/ 120 (normal desde S/ 150). Pagas en el consultorio con Yape, Plin, tarjeta o efectivo.\n\nConfirma tu asistencia", $confirmation);
    }

    public function test_a_promotion_for_a_treatment_that_is_not_booked_online_starts_with_the_evaluation(): void
    {
        $whitening = Treatment::factory()->clinicOnly()->create(['name' => 'Blanqueamiento']);
        $evaluation = Treatment::factory()->evaluation()->create();
        $this->doctorOffering($whitening, $evaluation);
        Campaign::query()->create(['name' => 'Blanqueamiento de verano', 'slug' => 'blanqueamiento-verano', 'treatment_id' => $whitening->id, 'discount_type' => DiscountType::Percent, 'discount_value' => 20, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);

        $this->getJson(route('api.v1.booking.options', ['promo' => 'blanqueamiento-verano']))
            ->assertOk()
            ->assertJsonPath('data.initial_treatment_id', $evaluation->id)
            ->assertJsonPath('data.campaign.discount', "20\u{00A0}% de descuento")
            ->assertJsonPath('data.campaign.treatment', ['id' => $whitening->id, 'name' => 'Blanqueamiento']);
    }

    public function test_a_promo_code_that_does_not_exist_does_not_stop_the_booking_and_a_valid_one_is_applied(): void
    {
        $this->freezeMonday();
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'price_from' => 150]);
        $this->doctorOffering($cleaning);
        $campaign = Campaign::query()->create(['name' => 'Limpieza de verano', 'slug' => 'limpieza-verano', 'promo_code' => 'VERANO30', 'treatment_id' => $cleaning->id, 'discount_type' => DiscountType::Amount, 'discount_value' => 30, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        User::factory()->reception()->create();

        // Mientras escribe el código, la web pregunta si existe: si no, avisa sin impedir reservar.
        $this->getJson(route('api.v1.booking.options', ['codigo' => 'sonrisa50']))
            ->assertOk()
            ->assertJsonPath('data.campaign', null)
            ->assertJsonPath('data.promo_problem', 'No reconocimos el código SONRISA50. Puedes reservar igual; lo revisamos en el consultorio.');
        $this->getJson(route('api.v1.booking.options', ['codigo' => 'verano30']))
            ->assertOk()
            ->assertJsonPath('data.promo_code', 'VERANO30')
            ->assertJsonPath('data.promo_problem', null)
            ->assertJsonPath('data.campaign.name', 'Limpieza de verano');

        // Con una letra equivocada reserva igual: el código queda en la cita para que recepción lo revise.
        $this->book($this->booking($cleaning, ['promo_code' => ' verano3o ']))
            ->assertCreated()
            ->assertJsonPath('data.promo_notice', 'No reconocimos el código VERANO3O. Igual reservamos tu cita; lo revisamos en el consultorio.');
        $mistyped = Appointment::sole();
        $this->assertSame(['VERANO3O', null], [$mistyped->promo_code, $mistyped->campaign_id]);
        $this->assertTrue($mistyped->hasUnknownPromoCode());
        $this->assertStringContainsString('Escribió el código VERANO3O, que no es de ninguna promoción vigente', $this->receptionAlertBody());

        $mistyped->delete();
        $this->book($this->booking($cleaning, ['promo_code' => ' verano30 ']))
            ->assertCreated()
            ->assertJsonPath('data.promo_notice', null);
        $this->assertTrue(Appointment::sole()->campaign->is($campaign));
        $this->assertFalse(Appointment::sole()->hasUnknownPromoCode());
    }

    public function test_a_code_with_an_enye_is_recognized_in_lowercase_and_without_accents_in_any_database(): void
    {
        $this->freezeMonday();
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'price_from' => 150]);
        $this->doctorOffering($cleaning);
        $campaign = Campaign::query()->create(['name' => 'Año nuevo', 'slug' => 'ano-nuevo', 'promo_code' => 'AÑONUEVO', 'treatment_id' => $cleaning->id, 'discount_type' => DiscountType::Amount, 'discount_value' => 30, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        User::factory()->reception()->create();

        // MySQL no distingue mayúsculas ni tildes al buscar el código: la web dice lo mismo que aplica.
        foreach (['añonuevo', 'anonuevo'] as $written) {
            $this->getJson(route('api.v1.booking.options', ['codigo' => $written]))
                ->assertOk()
                ->assertJsonPath('data.campaign.name', 'Año nuevo')
                ->assertJsonPath('data.promo_problem', null);
        }
        $this->getJson(route('api.v1.booking.options', ['codigo' => 'piñata']))
            ->assertOk()
            ->assertJsonPath('data.promo_problem', 'No reconocimos el código PIÑATA. Puedes reservar igual; lo revisamos en el consultorio.');

        $this->book($this->booking($cleaning, ['promo_code' => 'añonuevo']))
            ->assertCreated()
            ->assertJsonPath('data.promo_notice', null);
        $appointment = Appointment::sole();
        $this->assertSame('AÑONUEVO', $appointment->promo_code);
        $this->assertTrue($appointment->campaign->is($campaign));
        $this->assertFalse($appointment->hasUnknownPromoCode());
    }

    public function test_a_mistyped_code_keeps_the_promotion_of_the_page_the_patient_came_from(): void
    {
        $this->freezeMonday();
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'price_from' => 150]);
        $this->doctorOffering($cleaning);
        $campaign = Campaign::query()->create(['name' => 'Limpieza de verano', 'slug' => 'limpieza-verano', 'treatment_id' => $cleaning->id, 'discount_type' => DiscountType::Amount, 'discount_value' => 30, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);

        $this->getJson(route('api.v1.booking.options', ['promo' => 'limpieza-verano', 'codigo' => 'amigo']))
            ->assertOk()
            ->assertJsonPath('data.campaign.name', 'Limpieza de verano')
            ->assertJsonPath('data.promo_problem', 'No reconocimos el código AMIGO. Puedes reservar igual; lo revisamos en el consultorio.');

        $this->book($this->booking($cleaning, ['promo_code' => 'amigo', 'campaign_slug' => 'limpieza-verano']))
            ->assertCreated()
            ->assertJsonPath('data.promo_notice', 'No reconocimos el código AMIGO. Igual reservamos tu cita; lo revisamos en el consultorio.');
        $appointment = Appointment::sole();
        $this->assertTrue($appointment->campaign->is($campaign));
        $this->assertSame([AppointmentOrigin::Campaign, 'AMIGO'], [$appointment->origin, $appointment->promo_code]);
    }

    public function test_a_code_whose_promotion_ran_out_of_slots_books_with_the_promotion_the_page_showed(): void
    {
        $this->freezeMonday();
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'price_from' => 150]);
        $this->doctorOffering($cleaning);
        $page = Campaign::query()->create(['name' => 'Limpieza de verano', 'slug' => 'limpieza-verano', 'treatment_id' => $cleaning->id, 'discount_type' => DiscountType::Amount, 'discount_value' => 30, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $full = Campaign::query()->create(['name' => 'Primeros diez', 'slug' => 'primeros-diez', 'promo_code' => 'DIEZ', 'treatment_id' => $cleaning->id, 'discount_type' => DiscountType::Amount, 'discount_value' => 50, 'max_redemptions' => 1, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        Appointment::factory()->for($full)->create();
        $this->assertFalse($full->hasSlotsLeft());

        // La web muestra la promoción de la página (con su precio) y avisa que ese código no se aplicó.
        $this->getJson(route('api.v1.booking.options', ['promo' => 'limpieza-verano', 'codigo' => 'diez']))
            ->assertOk()
            ->assertJsonPath('data.campaign.name', 'Limpieza de verano')
            ->assertJsonPath('data.promo_problem', 'No reconocimos el código DIEZ. Puedes reservar igual; lo revisamos en el consultorio.');

        // La cita queda con esa misma promoción, no con la que ya no tiene cupos ni sin ninguna.
        $this->book($this->booking($cleaning, ['promo_code' => 'diez', 'campaign_slug' => 'limpieza-verano']))
            ->assertCreated()
            ->assertJsonPath('data.promo_notice', 'No reconocimos el código DIEZ. Igual reservamos tu cita; lo revisamos en el consultorio.');
        $appointment = Appointment::query()->latest('id')->firstOrFail();
        $this->assertTrue($appointment->campaign->is($page));
        $this->assertSame('DIEZ', $appointment->promo_code);
        $this->assertTrue($appointment->hasUnknownPromoCode());
    }

    /** Lo que dice el último aviso de "Nueva cita web" en la campana de recepción. */
    private function receptionAlertBody(): string
    {
        return (string) User::query()->where('role', UserRole::Reception)->firstOrFail()
            ->notifications()->latest()->firstOrFail()->data['body'];
    }

    public function test_a_time_taken_meanwhile_sends_the_patient_back_to_choose_another(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->evaluation()->create();
        $doctor = $this->doctorOffering($evaluation);
        Appointment::factory()->for($doctor)->at('2026-11-02 10:00')->create();

        $this->book($this->booking($evaluation))
            ->assertConflict()
            ->assertExactJson(['message' => 'Ese horario se acaba de ocupar. Por favor elige otro.', 'code' => 'slot_taken']);

        $this->assertSame(1, Appointment::count());
    }

    public function test_a_treatment_that_is_not_booked_online_cannot_be_booked(): void
    {
        $this->freezeMonday();
        $endodontics = Treatment::factory()->clinicOnly()->create();
        $this->doctorOffering($endodontics);

        $this->book($this->booking($endodontics))->assertUnprocessable();
        // Tampoco ofrece días ni horarios para él.
        $this->getJson(route('api.v1.booking.days', ['treatment' => $endodontics->id]))->assertNotFound();
        $this->getJson(route('api.v1.booking.slots', ['treatment' => $endodontics->id, 'date' => '2026-11-02']))->assertNotFound();

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_the_free_times_are_asked_for_a_day_written_as_a_date(): void
    {
        $cleaning = Treatment::factory()->create();

        foreach ([[], ['date' => 'mañana'], ['date' => '02/11/2026']] as $day) {
            $this->getJson(route('api.v1.booking.slots', ['treatment' => $cleaning->id, ...$day]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('date');
        }
    }

    public function test_a_remembered_phone_books_for_the_family_without_typing_the_number(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->evaluation()->create();
        $this->doctorOffering($evaluation);
        [$account, $mother, $child] = $this->rememberedFamily();

        $this->getJson(route('api.v1.booking.options'))
            ->assertOk()
            ->assertJsonPath('data.device.phone', '987 654 321')
            ->assertJsonPath('data.device.family.*.first_name', ['Rosa', 'Mateo'])
            ->assertJsonPath('data.device.family.0.is_self', true)
            ->assertJsonPath('data.device.has_holder', true)
            // El titular va primero; desde "Reservar su control" llega a nombre de quien le toca, y de nadie fuera de la familia.
            ->assertJsonPath('data.device.patient_id', $mother->id);
        $this->getJson(route('api.v1.booking.options', ['para' => $child->id]))->assertJsonPath('data.device.patient_id', $child->id);
        $this->getJson(route('api.v1.booking.options', ['para' => Patient::factory()->create()->id]))->assertJsonPath('data.device.patient_id', $mother->id);

        // Alguien de otra familia no se puede elegir.
        $this->book(['treatment_id' => $evaluation->id, 'date' => '2026-11-02', 'time' => '10:00', 'patient_id' => Patient::factory()->create()->id, 'relationship' => PatientRelationship::Child->value])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No encontramos a esa persona en este celular. Elige de nuevo para quién es la cita.');

        $this->book(['treatment_id' => $evaluation->id, 'date' => '2026-11-02', 'time' => '10:00', 'patient_id' => $child->id, 'relationship' => PatientRelationship::Child->value])
            ->assertCreated()
            ->assertJsonPath('data.remembered', true)
            // Una cita desde el celular recordado no pregunta cómo nos conoció.
            ->assertJsonPath('data.origins', [])
            ->assertJsonPath('data.origin_url', null);

        $appointment = Appointment::sole();
        $this->assertTrue($appointment->patient->is($child));
        $this->assertTrue($appointment->from_verified_device);

        // "Otra persona": un familiar nuevo queda en el mismo celular.
        $this->book(['treatment_id' => $evaluation->id, 'date' => '2026-11-02', 'time' => '11:00', 'who' => 'family', 'relationship' => PatientRelationship::Parent->value, 'first_name' => 'Juana', 'last_name' => 'Rojas'])
            ->assertCreated();

        $this->assertSame(PatientRelationship::Parent, $account->patients()->where('first_name', 'Juana')->sole()->relationship);
    }

    public function test_someone_in_treatment_is_told_and_cannot_book_but_the_family_can(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->evaluation()->create();
        $doctor = $this->doctorOffering($evaluation);
        [, $mother, $child] = $this->rememberedFamily();
        TreatmentPlan::create(['patient_id' => $mother->id, 'doctor_id' => $doctor->id, 'status' => TreatmentPlanStatus::InProgress]);

        $this->getJson(route('api.v1.booking.options'))
            ->assertOk()
            ->assertJsonPath('data.device.family.*.in_treatment', [true, false]);

        $this->book(['treatment_id' => $evaluation->id, 'date' => '2026-11-02', 'time' => '10:00', 'patient_id' => $mother->id, 'relationship' => PatientRelationship::Child->value])
            ->assertUnprocessable();
        $this->assertDatabaseCount('appointments', 0);

        $this->book(['treatment_id' => $evaluation->id, 'date' => '2026-11-02', 'time' => '10:00', 'patient_id' => $child->id, 'relationship' => PatientRelationship::Child->value])
            ->assertCreated();
        $this->assertTrue(Appointment::sole()->patient->is($child));
    }

    public function test_someone_who_books_with_a_familys_number_from_another_phone_is_not_offered_until_the_family_books_for_them(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->evaluation()->create();
        $this->doctorOffering($evaluation);
        $account = Account::factory()->verified()->create(['phone' => '+51987654321']);
        $mother = Patient::factory()->for($account)->create(['first_name' => 'Rosa']);
        Appointment::factory()->for($mother)->at('2026-10-20 10:00')->create(['status' => AppointmentStatus::Completed]);
        RememberedDevice::create(['account_id' => $account->id, 'token_hash' => hash('sha256', 'celular-de-rosa'), 'expires_at' => now()->addMonth(), 'verified_at' => now()]);

        // Desde otro celular, con el número de Rosa: la web no pide códigos, así que la cita se crea.
        $this->book($this->booking($evaluation, ['first_name' => 'Carlos', 'last_name' => 'Mamani Soto']))->assertCreated();

        $carlos = $account->patients()->where('first_name', 'Carlos')->sole();
        $this->assertFalse($carlos->appointments()->sole()->from_verified_device);

        // En el celular de Rosa no aparece como familiar ni se puede elegir.
        $this->withCookie(DeviceService::COOKIE, 'celular-de-rosa');
        $this->getJson(route('api.v1.booking.options'))->assertOk()->assertJsonPath('data.device.family.*.id', [$mother->id]);
        $this->book(['treatment_id' => $evaluation->id, 'date' => '2026-11-02', 'time' => '11:00', 'patient_id' => $carlos->id, 'relationship' => PatientRelationship::Child->value])
            ->assertUnprocessable();

        // Si Rosa reserva para él con su nombre, es de la familia: no se crea otro Carlos.
        $this->book(['treatment_id' => $evaluation->id, 'date' => '2026-11-02', 'time' => '11:00', 'who' => 'family', 'relationship' => PatientRelationship::Other->value, 'first_name' => 'carlos', 'last_name' => 'Mamani  Soto'])
            ->assertCreated();

        $this->assertSame(2, $carlos->appointments()->count());
        $this->assertSame(2, $account->patients()->count());
        $this->getJson(route('api.v1.booking.options'))->assertOk()->assertJsonPath('data.device.family.*.id', [$mother->id, $carlos->id]);
    }

    public function test_relatives_with_the_same_first_name_are_told_apart_by_their_surname(): void
    {
        $account = Account::factory()->verified()->create(['phone' => '+51987654321']);
        Patient::factory()->for($account)->create(['first_name' => 'María', 'last_name' => 'Quispe Huamán']);
        Patient::factory()->dependentOf($account)->create(['first_name' => 'Maria', 'last_name' => 'Flores Ticona']);
        Patient::factory()->dependentOf($account)->create(['first_name' => 'Mateo', 'last_name' => 'Quispe Flores']);
        RememberedDevice::create(['account_id' => $account->id, 'token_hash' => hash('sha256', 'celular-de-maria'), 'expires_at' => now()->addMonth(), 'verified_at' => now()]);

        $this->withCookie(DeviceService::COOKIE, 'celular-de-maria')
            ->getJson(route('api.v1.booking.options'))
            ->assertOk()
            ->assertJsonPath('data.device.family.*.name', ['María Quispe', 'Maria Flores', 'Mateo']);
    }

    public function test_a_family_that_already_accepted_the_privacy_policy_and_the_promotions_is_not_asked_again(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->evaluation()->create();
        $this->doctorOffering($evaluation);
        [$account, $mother] = $this->rememberedFamily();
        $account->update(['accepts_promotions' => false, 'privacy_accepted_at' => null]);

        $this->getJson(route('api.v1.booking.options'))
            ->assertOk()
            ->assertJsonPath('data.device.privacy_accepted', false)
            ->assertJsonPath('data.device.promotions_accepted', false);
        $this->book(['treatment_id' => $evaluation->id, 'date' => '2026-11-02', 'time' => '10:00', 'patient_id' => $mother->id, 'relationship' => PatientRelationship::Child->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accepts_privacy');

        $account->update(['accepts_promotions' => true, 'privacy_accepted_at' => now()]);

        $this->getJson(route('api.v1.booking.options'))
            ->assertOk()
            ->assertJsonPath('data.device.privacy_accepted', true)
            ->assertJsonPath('data.device.promotions_accepted', true);
        $this->book(['treatment_id' => $evaluation->id, 'date' => '2026-11-02', 'time' => '10:00', 'patient_id' => $mother->id, 'relationship' => PatientRelationship::Child->value])
            ->assertCreated();

        $this->assertTrue($account->fresh()->accepts_promotions);
    }

    public function test_after_booking_the_appointment_can_be_saved_to_the_calendar(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->evaluation()->create();
        $this->doctorOffering($evaluation);

        $response = $this->book($this->booking($evaluation))
            ->assertCreated()
            ->assertJsonPath('data.when', "Lunes 2 de noviembre, 10:00\u{00A0}a.\u{00A0}m.")
            ->assertJsonPath('data.whatsapp_message', "Hola, acabo de reservar {$evaluation->name} para el lunes 2 de noviembre a las 10:00\u{00A0}a.\u{00A0}m. a nombre de María Quispe Rojas.");

        // El archivo del calendario es el de la cita recién reservada.
        $this->get($response->json('data.calendar_url'))
            ->assertOk()
            ->assertSee('DTSTART:20261102T150000Z');
    }

    public function test_the_booked_screen_has_its_own_signed_address_that_survives_a_reload(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->evaluation()->create();
        $this->doctorOffering($evaluation);

        $booked = $this->book($this->booking($evaluation))->assertCreated();
        $appointment = Appointment::sole();
        $pageUrl = $booked->json('data.page_url');
        $signature = (string) str($pageUrl)->after('signature=');

        $this->assertStringStartsWith("/reservar/cita/{$appointment->id}?signature=", $pageUrl);

        // Al recargar (en el mismo celular, que quedó recordado), la página y la API muestran lo mismo que al reservar.
        $this->withCookie(DeviceService::COOKIE, $booked->getCookie(DeviceService::COOKIE)->getValue());
        $page = $this->get($pageUrl)->assertOk()->assertSee('<meta name="robots" content="noindex">', false);
        $this->assertStringContainsString('Lunes 2 de noviembre', $page->getContent());
        $again = $this->getJson(route('api.v1.booking.show', ['appointment' => $appointment, 'signature' => $signature]))
            ->assertOk()
            ->assertJsonPath('data.page_url', $pageUrl)
            ->assertJsonPath('meta.noindex', true);
        $this->assertSame(
            collect($booked->json('data'))->except(['origins', 'origin_url'])->all(),
            collect($again->json('data'))->except(['origins', 'origin_url'])->all(),
        );

        // Sin la firma, con otra o la de otra cita, no se ve.
        $other = Appointment::factory()->at('2026-11-03 10:00')->create();
        $this->getJson(route('api.v1.booking.show', ['appointment' => $appointment]))->assertNotFound();
        $this->getJson(route('api.v1.booking.show', ['appointment' => $appointment, 'signature' => 'abc']))->assertNotFound();
        $this->getJson(route('api.v1.booking.show', ['appointment' => $other, 'signature' => $signature]))->assertNotFound();
        $this->get(route('site.booking.done', $other).'?signature='.$signature)->assertNotFound();

        // Una cita cancelada ya no se muestra como reservada.
        $appointment->update(['status' => AppointmentStatus::Cancelled, 'cancelled_at' => now()]);
        $this->getJson(route('api.v1.booking.show', ['appointment' => $appointment, 'signature' => $signature]))->assertNotFound();
    }

    public function test_how_they_found_us_is_asked_once_after_booking(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->evaluation()->create();
        $this->doctorOffering($evaluation);

        $response = $this->book($this->booking($evaluation))->assertCreated();
        $originUrl = $response->json('data.origin_url');

        // "Campaña" y "referido" los pone el sistema: no se ofrecen ni se aceptan desde aquí.
        $offered = collect($response->json('data.origins'))->pluck('value');
        $this->assertContains(AppointmentOrigin::Facebook->value, $offered);
        $this->assertNotContains(AppointmentOrigin::Campaign->value, $offered);
        $this->postJson($originUrl, ['origin' => AppointmentOrigin::Campaign->value])->assertUnprocessable();

        $this->postJson($originUrl, ['origin' => AppointmentOrigin::Facebook->value])
            ->assertOk()
            ->assertJsonPath('message', '¡Gracias por contarnos!');
        // Ya contestó: otro toque no lo cambia.
        $this->postJson($originUrl, ['origin' => AppointmentOrigin::Google->value])->assertOk();

        $appointment = Appointment::with('patient')->sole();
        $this->assertSame(AppointmentOrigin::Facebook, $appointment->origin);
        $this->assertSame(AppointmentOrigin::Facebook, $appointment->patient->origin);

        // La dirección va firmada: sin la firma no se puede contestar por otra cita.
        $this->postJson(route('api.v1.booking.origin', $appointment), ['origin' => AppointmentOrigin::Google->value])->assertForbidden();

        // Quien llega desde un anuncio ya trae la respuesta en el enlace: no se le pregunta.
        $this->book($this->booking($evaluation, ['time' => '11:00', 'phone' => '912 345 678', 'origin' => 'instagram']))
            ->assertCreated()
            ->assertJsonPath('data.origins', [])
            ->assertJsonPath('data.origin_url', null);
        $this->assertSame(AppointmentOrigin::Instagram, Appointment::query()->latest('id')->first()->origin);
    }

    public function test_many_bookings_in_a_row_from_the_same_connection_are_stopped(): void
    {
        $this->freezeMonday();
        $evaluation = Treatment::factory()->evaluation()->create(['duration_minutes' => 15]);
        $this->doctorOffering($evaluation);
        $nextFreeTime = fn (): string => $this->getJson(route('api.v1.booking.slots', ['treatment' => $evaluation->id, 'date' => '2026-11-03']))->json('data.slots.0.time');

        foreach (range(1, 8) as $number) {
            $this->book($this->booking($evaluation, ['date' => '2026-11-03', 'time' => $nextFreeTime(), 'phone' => '98765432'.$number]))->assertCreated();
        }

        $this->book($this->booking($evaluation, ['date' => '2026-11-03', 'time' => $nextFreeTime(), 'phone' => '912 345 678']))
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Hiciste varias reservas seguidas. Espera un rato o escríbenos por WhatsApp.');

        $this->assertDatabaseCount('appointments', 8);
    }
}
