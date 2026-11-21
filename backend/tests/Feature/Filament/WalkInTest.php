<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentOrigin;
use App\Enums\AppointmentStatus;
use App\Enums\ScheduleBlockType;
use App\Enums\TreatmentPlanStatus;
use App\Filament\Pages\Dashboard;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\MedicalBackground;
use App\Models\Patient;
use App\Models\ScheduleBlock;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WhatsappMessage;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\Concerns\ReadsNotifications;
use Tests\TestCase;

class WalkInTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase, ReadsNotifications;

    private Treatment $cleaning;

    private Doctor $busy;

    private Doctor $free;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'duration_minutes' => 30]);
        $this->busy = $this->doctorOffering($this->cleaning);
        $this->free = $this->doctorOffering($this->cleaning);
        // Con nombre fijo: el de la fábrica sale al azar y, cuando tocaba "Pedro", se confundía con el paciente nuevo de estas pruebas.
        $booked = Patient::factory()->create(['first_name' => 'Carmen', 'last_name' => 'Rojas Díaz']);
        Appointment::factory()->for($this->busy)->for($this->cleaning)->for($booked)->at('2026-11-02 10:00')->confirmed()->create();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:07', 'America/Lima'));
        $this->actingAs(User::factory()->reception()->create());
    }

    public function test_who_comes_without_an_appointment_gets_todays_first_room_and_waits_in_the_room(): void
    {
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe', 'origin' => AppointmentOrigin::Recommendation]);
        MedicalBackground::create(['patient_id' => $rosa->id]);

        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $rosa->id])
            ->setActionData(['treatment_id' => $this->cleaning->id])
            // Queda puesto quien se desocupa primero, y se dice a qué hora.
            ->assertActionDataSet(['doctor_id' => $this->free->id])
            ->assertMountedActionModalSee('Lo puede atender hoy a las 10:15.')
            ->setActionData(['internal_notes' => 'Viene con dolor en una muela.'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $appointment = Appointment::query()->whereBelongsTo($rosa)->sole();

        $this->assertSame([$this->free->id, '10:15', AppointmentStatus::CheckedIn], [$appointment->doctor_id, $appointment->starts_at->format('H:i'), $appointment->status]);
        // La nota de recepción le llega al doctor con la cita.
        $this->assertSame('Viene con dolor en una muela.', $appointment->internal_notes);
        $this->assertSame('10:07', $appointment->checked_in_at->format('H:i'));
        // Lo tenemos delante: no se le prepara el aviso de "cita agendada".
        $this->assertSame(0, WhatsappMessage::count());

        $notice = $this->lastNotification();
        $this->assertSame(['Paciente en sala', 'Rosa Quispe pasa con '.$this->free->name.' a las 10:15.'], [$notice['title'], $notice['body']]);
    }

    public function test_a_new_patient_is_registered_in_the_same_window_and_told_what_is_missing(): void
    {
        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['new_patient' => true])
            ->setActionData(['patient' => ['first_name' => 'Pedro', 'last_name' => 'Huamán', 'phone' => '987654321', 'relationship' => 'self', 'document_type' => 'dni']])
            ->setActionData(['origin' => AppointmentOrigin::WalkIn->value, 'doctor_id' => $this->busy->id, 'treatment_id' => $this->cleaning->id])
            // Con quien está ocupado, el primer hueco es al terminar su cita.
            ->assertMountedActionModalSee('Lo puede atender hoy a las 10:')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $pedro = Patient::query()->where('first_name', 'Pedro')->sole();
        $appointment = Appointment::query()->whereBelongsTo($pedro)->sole();

        $this->assertSame([$this->busy->id, AppointmentStatus::CheckedIn, true], [$appointment->doctor_id, $appointment->status, $appointment->is_new_patient]);
        $this->assertTrue($appointment->starts_at->gte('2026-11-02 10:30'));
        // Lo que se preguntó al recibirlo queda en su ficha.
        $this->assertSame(AppointmentOrigin::WalkIn, $pedro->origin);

        $notice = $this->lastNotification();
        $this->assertStringStartsWith('Pedro Huamán pasa con '.$this->busy->name.' a las '.$appointment->starts_at->format('H:i').'.<br>Le falta: DNI y fecha de nacimiento.', $notice['body']);
        $this->assertSame(['Completar su ficha'], array_column($notice['actions'], 'label'));
    }

    public function test_when_the_doctor_has_no_room_left_today_nothing_is_created(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 18:50', 'America/Lima'));

        $window = Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['new_patient' => true])
            ->setActionData(['patient' => ['first_name' => 'Pedro', 'last_name' => 'Huamán', 'relationship' => 'self']])
            ->setActionData(['doctor_id' => $this->busy->id, 'treatment_id' => $this->cleaning->id])
            ->assertMountedActionModalSee(['Hoy ya no hay hueco para ese tratamiento con ningún doctor.', 'Agéndalo otro día o anótalo en la lista de espera: si hoy se libera un hueco, te llega la alerta.']);

        // No hay a dónde pasarlo: el botón no se ofrece. Y si igual llegara el pedido, no se crea nada.
        $this->assertTrue($window->instance()->getMountedAction()->getModalSubmitAction()->isDisabled());

        $window->callMountedAction()
            ->assertNotified($this->busy->name.' ya no tiene un hueco hoy para ese tratamiento. Elige otro doctor, o agéndalo con "Nueva cita" para otro día.');

        $this->assertSame(1, Appointment::count());
        $this->assertFalse(Patient::query()->where('first_name', 'Pedro')->exists());
    }

    public function test_without_room_with_the_chosen_doctor_it_says_who_can_see_them_today(): void
    {
        ScheduleBlock::create(['doctor_id' => $this->busy->id, 'type' => ScheduleBlockType::Course, 'starts_at' => '2026-11-02 06:00', 'ends_at' => '2026-11-02 22:00']);
        $rosa = Patient::factory()->create();

        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $rosa->id, 'doctor_id' => $this->busy->id])
            ->setActionData(['treatment_id' => $this->cleaning->id])
            ->assertMountedActionModalSee(['Ya no tiene un hueco hoy para ese tratamiento.', $this->free->name.' sí puede hoy a las 10:15. Si no, agéndalo otro día o anótalo en la lista de espera']);
    }

    public function test_when_nobody_has_room_today_the_window_still_says_so_and_what_to_do(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 18:50', 'America/Lima'));

        // Antes no quedaba ningún doctor puesto y la ventana no decía nada.
        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => Patient::factory()->create()->id])
            ->setActionData(['treatment_id' => $this->cleaning->id])
            ->assertActionDataSet(fn (array $data) => filled($data['doctor_id']))
            ->assertMountedActionModalSee(['Hoy ya no hay hueco para ese tratamiento con ningún doctor.', 'Agendarlo otro día', 'Anotar en lista de espera para hoy']);
    }

    public function test_without_room_today_they_wait_for_a_freed_time_today_with_what_was_typed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 18:50', 'America/Lima'));
        $waitForToday = TestAction::make('waitForToday')->schemaComponent('roomToday', 'mountedActionSchema0');

        $window = Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['new_patient' => true])
            ->setActionData(['patient' => ['first_name' => 'Pedro', 'last_name' => 'Huamán', 'phone' => '987654321', 'relationship' => 'self']])
            ->setActionData(['doctor_id' => $this->busy->id, 'treatment_id' => $this->cleaning->id])
            ->assertActionVisible(TestAction::make('bookAnotherDay')->schemaComponent('roomToday', 'mountedActionSchema0'))
            ->mountAction($waitForToday)
            // Lo ya escrito del paciente nuevo pasa a la lista, para hoy y con cualquier doctor.
            ->assertSchemaStateSet([
                'new_patient' => true,
                'patient.first_name' => 'Pedro',
                'patient.phone' => '987654321',
                'treatment_id' => $this->cleaning->id,
                'doctor_id' => null,
                'earliest_date' => '2026-11-02',
                'latest_date' => '2026-11-02',
            ], 'mountedActionSchema1')
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Pedro Huamán quedó en la lista de espera');

        $this->assertSame([], $window->get('mountedActions'));
        $entry = WaitlistEntry::sole();
        $this->assertSame(['Pedro Huamán', null, '2026-11-02'], [$entry->patient->full_name, $entry->doctor_id, $entry->latest_date->toDateString()]);
        $this->assertSame(1, Appointment::count());
    }

    public function test_without_room_today_they_are_booked_another_day_with_what_was_chosen(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 18:50', 'America/Lima'));
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']);

        $window = Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $rosa->id])
            ->setActionData(['doctor_id' => $this->busy->id, 'treatment_id' => $this->cleaning->id])
            ->mountAction(TestAction::make('bookAnotherDay')->schemaComponent('roomToday', 'mountedActionSchema0'))
            // "Nueva cita" encima, en el primer día con lugar.
            ->assertSchemaStateSet([
                'patient_id' => $rosa->id,
                'treatment_id' => $this->cleaning->id,
                'doctor_id' => $this->busy->id,
                'date' => '2026-11-03',
            ], 'mountedActionSchema1')
            ->setActionData(['slot' => '09:00'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame([], $window->get('mountedActions'));
        $this->assertSame('2026-11-03 09:00', $rosa->appointments()->sole()->starts_at->format('Y-m-d H:i'));
    }

    public function test_who_already_had_an_appointment_today_is_marked_as_arrived_instead_of_getting_another(): void
    {
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe', 'origin' => AppointmentOrigin::Recommendation]);
        MedicalBackground::create(['patient_id' => $rosa->id]);
        $booked = Appointment::factory()->for($this->busy)->for($this->cleaning)->for($rosa)->at('2026-11-02 12:00')->confirmed()->create(['internal_notes' => 'Trae su radiografía.']);

        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $rosa->id])
            ->assertMountedActionModalSee(['Tiene cita hoy a las 12:00', 'Limpieza dental con '.$this->busy->name.' · Confirmada', 'Llegó antes de su hora.', 'Marcar que llegó a esa cita'])
            // Ya no se elige tratamiento ni doctor: se usa la cita que tenía.
            ->assertMountedActionModalDontSee('Tratamiento')
            ->setActionData(['internal_notes' => 'Ahora le duele más.'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertTrue(Appointment::query()->whereBelongsTo($rosa)->sole()->is($booked));
        $this->assertSame([AppointmentStatus::CheckedIn, '10:07'], [$booked->refresh()->status, $booked->checked_in_at->format('H:i')]);
        // La nota se agrega debajo de la que ya tenía la cita, sin borrarla.
        $this->assertSame("Trae su radiografía.\nAhora le duele más.", $booked->internal_notes);

        $notice = $this->lastNotification();
        $this->assertSame(['Paciente en sala', 'Rosa Quispe llegó a su cita de las 12:00 con '.$this->busy->name.'.'], [$notice['title'], $notice['body']]);
    }

    public function test_who_arrives_early_can_go_in_earlier_when_their_doctor_is_free_and_their_time_is_left_for_another(): void
    {
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe', 'origin' => AppointmentOrigin::Recommendation]);
        MedicalBackground::create(['patient_id' => $rosa->id]);
        $booked = Appointment::factory()->for($this->busy)->for($this->cleaning)->for($rosa)->at('2026-11-02 12:00')->confirmed()->create();
        $this->cleaning->update(['buffer_minutes' => 10]);

        // Su doctor atiende a Carmen hasta las 10:30 y esteriliza hasta las 10:40: desde ahí está libre.
        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $rosa->id])
            ->assertMountedActionModalSee(['Adelantar su cita a las 10:40', 'La agenda de '.$this->busy->name.' está libre desde esa hora. Su hora de las 12:00 queda para otro paciente.'])
            ->setActionData(['advance' => true])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(['10:40', AppointmentStatus::CheckedIn], [$booked->refresh()->starts_at->format('H:i'), $booked->status]);
        // Ya lo sabe: está delante. No se le manda "tu cita cambió".
        $this->assertSame(0, WhatsappMessage::count());
        $this->assertSame('Rosa Quispe llegó antes: su cita pasó de las 12:00 a las 10:40 con '.$this->busy->name.'.', $this->lastNotification()['body']);
    }

    public function test_going_in_earlier_is_left_unmarked_when_another_patient_is_chosen(): void
    {
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']);
        $luis = Patient::factory()->create(['first_name' => 'Luis', 'last_name' => 'Paz']);
        Appointment::factory()->for($this->busy)->for($this->cleaning)->for($rosa)->at('2026-11-02 12:00')->confirmed()->create();
        $his = Appointment::factory()->for($this->free)->for($this->cleaning)->for($luis)->at('2026-11-02 12:30')->confirmed()->create();

        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $rosa->id])
            ->setActionData(['advance' => true])
            ->setActionData(['patient_id' => $luis->id])
            ->assertActionDataSet(['advance' => false, 'advance_to' => null])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(['12:30', AppointmentStatus::CheckedIn], [$his->refresh()->starts_at->format('H:i'), $his->status]);
    }

    public function test_what_is_answered_in_how_they_found_us_is_kept_when_they_arrive_to_their_appointment(): void
    {
        $lucia = Patient::factory()->create(['first_name' => 'Lucía', 'last_name' => 'Rojas', 'first_visit_at' => null, 'origin' => null]);
        $booked = Appointment::factory()->for($this->busy)->for($this->cleaning)->for($lucia)->at('2026-11-02 12:00')->confirmed()->create();

        // Antes se preguntaba, pero al marcar que llegó a su cita se perdía.
        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $lucia->id])
            ->assertMountedActionModalSee(['Marcar que llegó a esa cita', '¿Cómo nos encontró?'])
            ->setActionData(['origin' => AppointmentOrigin::Recommendation->value])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(AppointmentStatus::CheckedIn, $booked->refresh()->status);
        $this->assertSame([AppointmentOrigin::Recommendation, AppointmentOrigin::Recommendation], [$booked->origin, $lucia->refresh()->origin]);
    }

    public function test_it_is_still_a_separate_visit_when_reception_says_it_comes_for_something_else(): void
    {
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']);
        $booked = Appointment::factory()->for($this->busy)->for($this->cleaning)->for($rosa)->at('2026-11-02 12:00')->confirmed()->create();

        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $rosa->id])
            ->setActionData(['use_appointment' => false])
            ->setActionData(['treatment_id' => $this->cleaning->id])
            ->assertMountedActionModalSee('Lo puede atender hoy a las 10:15.')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(AppointmentStatus::Confirmed, $booked->refresh()->status);
        $this->assertSame(['10:15', AppointmentStatus::CheckedIn], [($walkIn = Appointment::query()->whereBelongsTo($rosa)->whereKeyNot($booked->id)->sole())->starts_at->format('H:i'), $walkIn->status]);
    }

    public function test_who_is_already_in_the_room_is_pointed_out_before_opening_another_visit(): void
    {
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']);
        Appointment::factory()->for($this->busy)->for($this->cleaning)->for($rosa)->at('2026-11-02 12:00')->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => now()]);

        $window = Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $rosa->id])
            ->assertMountedActionModalSee(['Ya está en sala desde las 10:07', 'Sigue solo si viene por otra atención aparte'])
            ->assertMountedActionModalDontSee('Marcar que llegó a esa cita')
            // No hay a qué marcarle llegada: si se sigue, es otra atención, y el botón lo dice.
            ->assertMountedActionModalSee('Tratamiento');

        $this->assertSame('Abrir otra atención', $window->instance()->getMountedAction()->getModalSubmitAction()->getLabel());
    }

    public function test_with_two_appointments_today_it_offers_the_one_still_to_come_and_mentions_the_other(): void
    {
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']);
        Appointment::factory()->for($this->busy)->for($this->cleaning)->for($rosa)->at('2026-11-02 09:30')->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => now()->subMinutes(40)]);
        $later = Appointment::factory()->for($this->free)->for($this->cleaning)->for($rosa)->at('2026-11-02 15:00')->confirmed()->create();
        $plan = TreatmentPlan::create(['patient_id' => $rosa->id, 'doctor_id' => $this->free->id, 'title' => 'Plan', 'status' => TreatmentPlanStatus::Accepted, 'accepted_at' => now()->subWeek()]);
        $plan->items()->create(['treatment_id' => $this->cleaning->id, 'description' => 'Limpieza dental', 'unit_price' => 120, 'accepted_at' => now()->subWeek()]);

        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $rosa->id])
            // Lo normal es que llegue a la que todavía no empieza; la otra se dice debajo.
            ->assertMountedActionModalSee(['Tiene cita hoy a las 15:00', 'También tiene hoy Limpieza dental con '.$this->busy->name.' a las 09:30 · En sala.', 'Marcar que llegó a esa cita'])
            // Esa cita ya dice qué es: no se pregunta por la sesión de su plan.
            ->assertMountedActionModalDontSee(['¿Es una sesión de su plan de tratamiento?', 'Le toca de su plan'])
            ->setActionData(['use_appointment' => false])
            ->assertMountedActionModalSee('¿Es una sesión de su plan de tratamiento?')
            ->setActionData(['use_appointment' => true])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(AppointmentStatus::CheckedIn, $later->refresh()->status);
    }

    public function test_an_appointment_on_another_day_is_mentioned_under_the_patient(): void
    {
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']);
        Appointment::factory()->for($this->busy)->for($this->cleaning)->for($rosa)->at('2026-11-04 16:00')->confirmed()->create();

        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $rosa->id])
            // Esta misma semana: quizá se confundió de día. No hay enlace para moverla: el paciente está en el mostrador.
            ->assertMountedActionModalSee(['Ya tiene cita el miércoles 4 de noviembre a las 16:00', '¿Se confundió de día? Esa cita sigue en pie: muévela o cancélala después.'])
            ->assertMountedActionModalDontSee(['Tiene cita hoy', 'Ver o mover esa cita']);
    }

    public function test_what_the_patient_is_missing_is_shown_before_going_in(): void
    {
        $pedro = Patient::factory()->create(['first_name' => 'Pedro', 'last_name' => 'Huamán', 'document_number' => null, 'birth_date' => null, 'origin' => AppointmentOrigin::Recommendation]);
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe', 'origin' => AppointmentOrigin::Recommendation]);
        MedicalBackground::create(['patient_id' => $rosa->id]);

        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->assertMountedActionModalSee(['Antes de pasar', 'Elige al paciente: aquí verás si le falta algo'])
            // Con el paciente todavía en el mostrador, no recién en el aviso de después.
            ->setActionData(['patient_id' => $pedro->id])
            ->assertMountedActionModalSee(['Le falta: DNI y fecha de nacimiento.', 'No ha llenado sus antecedentes médicos', 'Completar su ficha'])
            ->setActionData(['patient_id' => $rosa->id])
            ->assertMountedActionModalSee('No le falta nada: puede pasar.')
            ->assertMountedActionModalDontSee(['Le falta:', 'Completar su ficha'])
            ->setActionData(['new_patient' => true])
            ->assertMountedActionModalSee('Es paciente nuevo: al pasarlo a sala verás qué datos le faltan');
    }

    public function test_the_notice_says_whether_the_hour_of_their_appointment_today_already_passed(): void
    {
        $carmen = Patient::query()->where('first_name', 'Carmen')->sole();

        $window = Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $carmen->id])
            ->assertMountedActionModalSee(['Tenía cita hoy a las 10:00', 'Marcar que llegó a esa cita'])
            ->assertMountedActionModalDontSee('Llegó antes de su hora.');

        // Marca que llegó a esa cita: el paciente queda en sala, como dice el botón.
        $this->assertSame('Pasar a sala', $window->instance()->getMountedAction()->getModalSubmitAction()->getLabel());
    }

    public function test_a_doctor_receives_who_comes_without_an_appointment_on_their_own_agenda(): void
    {
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']);
        $this->actingAs($this->busy->user);

        // Otro doctor se desocupa antes, pero el doctor solo maneja su agenda: llega puesto él y así se queda.
        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->assertActionDataSet(['doctor_id' => $this->busy->id])
            ->setActionData(['patient_id' => $rosa->id])
            ->setActionData(['treatment_id' => $this->cleaning->id])
            ->assertActionDataSet(['doctor_id' => $this->busy->id])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $appointment = Appointment::query()->whereBelongsTo($rosa)->sole();

        $this->assertSame([$this->busy->id, AppointmentStatus::CheckedIn], [$appointment->doctor_id, $appointment->status]);
    }

    public function test_a_doctor_is_not_offered_the_file_of_a_patient_they_cannot_open(): void
    {
        $pedro = Patient::factory()->create(['first_name' => 'Pedro', 'last_name' => 'Huamán', 'document_number' => null, 'origin' => AppointmentOrigin::Recommendation]);
        $this->actingAs($this->busy->user);

        // Nunca lo atendió: su ficha le daría "sin acceso". Lo que le falta sí se ve, y al pasarlo a sala ya es su paciente.
        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $pedro->id])
            ->assertMountedActionModalSee('Le falta: DNI.')
            ->assertMountedActionModalDontSee(['Ver su ficha', 'Completar su ficha']);
    }

    public function test_a_doctor_is_not_told_about_the_appointment_the_patient_has_with_someone_else(): void
    {
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']);
        Appointment::factory()->for($this->busy)->for($this->cleaning)->for($rosa)->at('2026-11-02 12:00')->confirmed()->create();
        $this->actingAs($this->free->user);

        Livewire::test(Dashboard::class)
            ->mountAction('walkIn')
            ->setActionData(['patient_id' => $rosa->id])
            ->assertMountedActionModalDontSee(['Ya tiene cita', 'Marcar que llegó a esa cita'])
            ->assertMountedActionModalSee('Tratamiento');
    }
}
