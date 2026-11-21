<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Enums\DayPeriod;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\ScheduleBlockType;
use App\Enums\WaitlistStatus;
use App\Filament\Pages\Agenda;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Pages\ViewAppointment;
use App\Filament\Resources\WaitlistEntries\Pages\ManageWaitlistEntries;
use App\Filament\Resources\WaitlistEntries\WaitlistEntryActions;
use App\Filament\Resources\WaitlistEntries\WaitlistEntryResource;
use App\Filament\Widgets\WeekGaps;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\ScheduleBlock;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WhatsappMessage;
use App\Services\Booking\WaitlistService;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\Concerns\ReadsNotifications;
use Tests\TestCase;

class WaitlistPanelTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase, ReadsNotifications;

    private Treatment $treatment;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeMonday();
        $this->treatment = Treatment::factory()->create(['name' => 'Limpieza dental', 'duration_minutes' => 30]);
        $this->doctor = $this->doctorOffering($this->treatment);
        $this->doctor->update(['name' => 'Dra. Ana Torres']);
    }

    public function test_only_who_manages_the_agenda_sees_the_waitlist(): void
    {
        WaitlistEntry::factory()->for($this->treatment)->count(2)->create();

        $this->actingAs(User::factory()->reception()->create());
        $this->get(WaitlistEntryResource::getUrl('index'))->assertOk()->assertSee('Lista de espera');
        $this->assertSame('2', WaitlistEntryResource::getNavigationBadge());

        $this->actingAs($this->doctor->user);
        $this->get(WaitlistEntryResource::getUrl('index'))->assertForbidden();
    }

    public function test_reception_adds_a_patient_who_wants_an_earlier_time(): void
    {
        $reception = User::factory()->reception()->create();
        $patient = Patient::factory()->create();
        $this->actingAs($reception);

        Livewire::test(ManageWaitlistEntries::class)
            ->callAction('create', [
                'patient_id' => $patient->id,
                'treatment_id' => $this->treatment->id,
                'doctor_id' => $this->doctor->id,
                'earliest_date' => '2026-11-02',
                'latest_date' => '2026-11-20',
                'preferred_period' => DayPeriod::Morning->value,
                'notes' => 'Tiene cita el 20/11 y quiere adelantarla',
            ])
            ->assertHasNoActionErrors();

        $entry = WaitlistEntry::sole();
        $this->assertSame(WaitlistStatus::Waiting, $entry->status);
        $this->assertSame(DayPeriod::Morning, $entry->preferred_period);
        $this->assertTrue($entry->createdBy->is($reception));
    }

    public function test_reception_adds_to_the_waitlist_from_home_without_leaving_it(): void
    {
        $reception = User::factory()->reception()->create();
        $patient = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']);
        $this->actingAs($reception);

        Livewire::test(Dashboard::class)
            ->mountAction('waitlist')
            ->assertMountedActionModalSee(['Anotar en lista de espera', 'Qué va a pasar', 'Elige el tratamiento: aquí verás si ya hay un horario libre que le sirva, para no anotarlo en vano.'])
            // Lo normal ya viene puesto: desde hoy y a cualquier hora.
            ->assertActionDataSet(['earliest_date' => '2026-11-02', 'preferred_period' => DayPeriod::Any])
            ->setActionData(['patient_id' => $patient->id, 'treatment_id' => $this->treatment->id])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Rosa Quispe quedó en la lista de espera');

        $entry = WaitlistEntry::sole();
        $this->assertSame([WaitlistStatus::Waiting, $patient->id, $this->treatment->id, null], [$entry->status, $entry->patient_id, $entry->treatment_id, $entry->doctor_id]);
        $this->assertTrue($entry->createdBy->is($reception));

        // El doctor no maneja la lista: tampoco la anota desde su inicio.
        $this->actingAs($this->doctor->user);
        Livewire::test(Dashboard::class)->assertActionHidden('waitlist');
    }

    public function test_a_patient_is_not_added_twice_for_the_same_treatment(): void
    {
        $patient = Patient::factory()->create();
        $other = Treatment::factory()->create(['name' => 'Curación con resina']);
        $this->doctor->treatments()->attach($other);
        $waiting = WaitlistEntry::factory()->for($patient)->for($this->treatment)->create();
        // Lo que ya se agendó o se retiró no estorba: puede volver a anotarse.
        WaitlistEntry::factory()->for($patient)->for($other)->removed()->create();
        $this->actingAs(User::factory()->reception()->create());

        // Se dice al elegir al paciente, con el enlace a la anotación que ya tiene; con el mismo tratamiento, no deja guardar.
        Livewire::test(ManageWaitlistEntries::class)
            ->mountAction('create')
            ->setActionData(['patient_id' => $patient->id])
            ->assertMountedActionModalSee(['Ya está en la lista de espera para Limpieza dental', 'Si es para lo mismo, edita esa anotación; si es para otra cosa, elige el tratamiento.'])
            ->setActionData(['treatment_id' => $this->treatment->id])
            ->assertMountedActionModalSee(['Ya está en la lista de espera para Limpieza dental', 'Lo anotaron el 2 de noviembre. Si cambió lo que puede, edita esa anotación en vez de anotarlo otra vez.'])
            ->assertActionHasUrl(
                TestAction::make('editExistingEntry')->schemaComponent('alreadyWaiting', 'mountedActionSchema0'),
                WaitlistEntryResource::getUrl('index', ['tableAction' => 'edit', 'tableActionRecord' => $waiting->id]),
            )
            ->callMountedAction()
            ->assertHasActionErrors(['treatment_id' => 'Ya está en la lista de espera para ese tratamiento. Si cambió lo que puede, edita esa anotación.']);

        Livewire::test(ManageWaitlistEntries::class)
            ->callAction('create', ['patient_id' => $patient->id, 'treatment_id' => $other->id])
            ->assertHasNoActionErrors()
            // Editar la anotación que ya tiene no choca consigo misma.
            ->callTableAction('edit', $waiting, ['notes' => 'Solo puede sábados'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, WaitlistEntry::query()->active()->count());
        $this->assertSame('Solo puede sábados', $waiting->refresh()->notes);
    }

    public function test_the_window_says_if_a_free_time_already_suits_them_so_they_are_booked_instead(): void
    {
        $patient = Patient::factory()->create();
        WaitlistEntry::factory()->for($this->treatment)->count(2)->create();
        $this->actingAs(User::factory()->reception()->create());

        $window = Livewire::test(ManageWaitlistEntries::class)
            ->mountAction('create')
            ->setActionData(['patient_id' => $patient->id, 'treatment_id' => $this->treatment->id])
            ->assertMountedActionModalSee(['Ya hay un horario libre que le sirve', 'Lunes 2 de noviembre a las 09:00. Mejor agéndalo de una vez: no hace falta anotarlo.', 'Agendar ahí']);

        // Si desde el día que puede no hay nada libre, anotarlo sí sirve; y se dice cuántos esperan lo mismo.
        ScheduleBlock::create(['doctor_id' => $this->doctor->id, 'type' => ScheduleBlockType::Course, 'starts_at' => '2026-11-30 06:00', 'ends_at' => '2026-12-13 22:00']);
        $window->setActionData(['earliest_date' => '2026-11-30'])
            ->assertMountedActionModalDontSee('Ya hay un horario libre que le sirve')
            ->assertMountedActionModalSee([
                'No hay nada libre que le sirva en las 2 semanas desde el 30 de noviembre: anotarlo sí sirve.',
                'Hay 2 personas más en espera para Limpieza dental. En la alerta sale primero quien espera hace más tiempo.',
            ]);

        // "Agendar ahí" abre "Nueva cita" encima con todo puesto; al agendar se cierran las dos y no queda anotado.
        $booking = Livewire::test(ManageWaitlistEntries::class)
            ->mountAction('create')
            ->setActionData(['patient_id' => $patient->id, 'treatment_id' => $this->treatment->id])
            ->mountAction(TestAction::make('bookFreeSlot')->schemaComponent('freeSlot', 'mountedActionSchema0'))
            ->assertSchemaStateSet([
                'patient_id' => $patient->id,
                'treatment_id' => $this->treatment->id,
                'doctor_id' => $this->doctor->id,
                'date' => '2026-11-02',
                'slot' => '09:00',
            ], 'mountedActionSchema1')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame([], $booking->get('mountedActions'));
        $this->assertSame('2026-11-02 09:00', $patient->appointments()->sole()->starts_at->format('Y-m-d H:i'));
        $this->assertFalse(WaitlistEntry::query()->where('patient_id', $patient->id)->exists());
    }

    public function test_a_short_deadline_with_nothing_free_is_said_to_be_hard(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 18:50', 'America/Lima'));
        ScheduleBlock::create(['doctor_id' => $this->doctor->id, 'type' => ScheduleBlockType::Course, 'starts_at' => '2026-11-03 06:00', 'ends_at' => '2026-11-03 22:00']);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(ManageWaitlistEntries::class)
            ->mountAction('create')
            ->setActionData(['patient_id' => Patient::factory()->create()->id, 'treatment_id' => $this->treatment->id, 'latest_date' => '2026-11-03'])
            ->assertMountedActionModalSee([
                'No hay nada libre en ese plazo, y es corto: es difícil que se libere algo. Si puede esperar más, amplía',
                'Nadie más está en espera para Limpieza dental: cuando se libere un horario, sale primero en la alerta.',
            ])
            // Solo para hoy: queda que alguien cancele o no llegue.
            ->setActionData(['latest_date' => '2026-11-02'])
            ->assertMountedActionModalSee('Hoy no queda nada libre. Si alguien cancela, te llega la alerta para ofrecérselo.');
    }

    public function test_who_can_only_come_later_is_searched_from_that_day(): void
    {
        ScheduleBlock::create(['doctor_id' => $this->doctor->id, 'type' => ScheduleBlockType::Course, 'starts_at' => '2026-12-01 06:00', 'ends_at' => '2026-12-14 22:00']);
        $this->actingAs(User::factory()->reception()->create());

        // Desde el 1 de diciembre: se buscan las dos semanas desde ese día, no las próximas.
        Livewire::test(ManageWaitlistEntries::class)
            ->mountAction('create')
            ->setActionData(['patient_id' => Patient::factory()->create()->id, 'treatment_id' => $this->treatment->id, 'earliest_date' => '2026-12-01'])
            ->assertMountedActionModalSee('No hay nada libre que le sirva en las 2 semanas desde el 1 de diciembre: anotarlo sí sirve.')
            ->setActionData(['earliest_date' => '2026-12-15'])
            ->assertMountedActionModalSee('Ya hay un horario libre que le sirve');
    }

    public function test_an_appointment_in_a_few_days_with_nothing_free_before_is_said_to_be_hard_to_bring_forward(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 18:50', 'America/Lima'));
        $patient = Patient::factory()->create();
        Appointment::factory()->for($patient)->for($this->doctor)->for($this->treatment)->at('2026-11-03 09:00')->create();
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(ManageWaitlistEntries::class)
            ->mountAction('create')
            ->setActionData(['patient_id' => $patient->id])
            ->setActionData(['treatment_id' => $this->treatment->id])
            ->assertActionDataSet(['latest_date' => '2026-11-02'])
            ->assertMountedActionModalDontSee('Ya hay un horario libre que le sirve')
            ->assertMountedActionModalSee('No hay nada libre antes de su cita, y ya es en pocos días: es difícil adelantarla.')
            // Aunque se vacíe "Hasta", un horario después de su cita no sirve para adelantarla.
            ->setActionData(['latest_date' => null])
            ->assertMountedActionModalDontSee('Ya hay un horario libre que le sirve');
    }

    public function test_who_already_has_an_appointment_for_it_waits_to_bring_it_forward(): void
    {
        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->for($patient)->for($this->doctor)->for($this->treatment)->at('2026-11-20 10:00')->create();
        $this->actingAs(User::factory()->reception()->create());
        $bringForward = TestAction::make('bringAppointmentForward')->schemaComponent('freeSlot', 'mountedActionSchema0');

        Livewire::test(ManageWaitlistEntries::class)
            ->mountAction('create')
            ->setActionData(['patient_id' => $patient->id])
            ->setActionData(['treatment_id' => $this->treatment->id])
            // Un horario después de su cita no le sirve: "Hasta" queda el día antes.
            ->assertActionDataSet(['latest_date' => '2026-11-19'])
            ->assertMountedActionModalSee(['Ya tiene cita el viernes 20 de noviembre a las 10:00', 'Se anota para adelantarla', 'Puedes adelantar su cita ahí de una vez.', 'Adelantar su cita'])
            // No se le ofrece otra cita: se mueve la que tiene.
            ->assertMountedActionModalDontSee('Agendar ahí')
            ->assertActionHasUrl($bringForward, AppointmentResource::getUrl('view', ['record' => $appointment, 'action' => 'move']));
    }

    public function test_with_the_appointment_today_it_only_waits_for_an_earlier_time_today(): void
    {
        $patient = Patient::factory()->create();
        Appointment::factory()->for($patient)->for($this->doctor)->for($this->treatment)->at('2026-11-02 15:00')->create();
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(ManageWaitlistEntries::class)
            ->mountAction('create')
            ->setActionData(['patient_id' => $patient->id])
            ->setActionData(['treatment_id' => $this->treatment->id])
            // No hay un día antes que poner en "Hasta", y no se dice que lo hay.
            ->assertActionDataSet(['latest_date' => null])
            ->assertMountedActionModalSee(['Ya tiene cita hoy a las 15:00', 'Se anota solo para pasarla más temprano'])
            ->assertMountedActionModalDontSee('Por eso "Hasta" queda un día antes.');
    }

    public function test_a_patient_without_a_phone_is_flagged_because_the_time_cannot_be_offered(): void
    {
        $withoutPhone = Patient::factory()->create(['account_id' => null]);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(ManageWaitlistEntries::class)
            ->mountAction('create')
            ->setActionData(['patient_id' => Patient::factory()->create()->id])
            ->assertMountedActionModalDontSee('No tiene celular registrado')
            ->setActionData(['patient_id' => $withoutPhone->id])
            ->assertMountedActionModalSee(['No tiene celular registrado', 'Sin celular no se le puede ofrecer por WhatsApp el horario que se libere.']);
    }

    public function test_a_new_patient_is_registered_in_the_same_window_with_a_phone(): void
    {
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(ManageWaitlistEntries::class)
            ->mountAction('create')
            ->setActionData(['new_patient' => true])
            ->setActionData(['patient' => ['first_name' => 'Lucía', 'last_name' => 'Rojas', 'relationship' => 'self'], 'treatment_id' => $this->treatment->id])
            ->callMountedAction()
            // Sin celular no hay a dónde ofrecerle el horario.
            ->assertHasActionErrors(['patient.phone' => 'required'])
            ->setActionData(['patient' => ['first_name' => 'Lucía', 'last_name' => 'Rojas', 'relationship' => 'self', 'phone' => '987654321']])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Lucía Rojas quedó en la lista de espera');

        $entry = WaitlistEntry::sole();
        $this->assertSame(['Lucía Rojas', $this->treatment->id], [$entry->patient->full_name, $entry->treatment_id]);
        $this->assertNotNull($entry->patient->account);
    }

    public function test_a_full_day_in_new_appointment_adds_them_to_the_waitlist_with_what_was_chosen(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 18:50', 'America/Lima'));
        $patient = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']);
        $this->actingAs(User::factory()->reception()->create());
        $addToWaitlist = TestAction::make('addToWaitlist')->schemaComponent('slot', 'mountedActionSchema0');

        $agenda = Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['patient' => $patient->id, 'treatment' => $this->treatment->id, 'doctor' => $this->doctor->id])
            // Se abre en el siguiente día con lugar: ahí no hace falta.
            ->assertMountedActionModalDontSee('Anotar en lista de espera')
            ->setActionData(['date' => '2026-11-02'])
            ->assertActionVisible($addToWaitlist)
            ->mountAction($addToWaitlist)
            ->assertSchemaStateSet([
                'new_patient' => false,
                'patient_id' => $patient->id,
                'treatment_id' => $this->treatment->id,
                'earliest_date' => '2026-11-02',
                'latest_date' => '2026-11-02',
            ], 'mountedActionSchema1')
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Rosa Quispe quedó en la lista de espera');

        // Al anotarlo se cierran las dos ventanas.
        $this->assertSame([], $agenda->get('mountedActions'));
        $this->assertSame([$patient->id, '2026-11-02'], [WaitlistEntry::sole()->patient_id, WaitlistEntry::sole()->latest_date->toDateString()]);
    }

    public function test_the_lists_split_who_is_waiting_from_who_was_booked_or_left(): void
    {
        $waiting = WaitlistEntry::factory()->for($this->treatment)->create();
        $offered = WaitlistEntry::factory()->for($this->treatment)->offered()->create();
        $booked = WaitlistEntry::factory()->for($this->treatment)->create(['status' => WaitlistStatus::Booked]);
        $removed = WaitlistEntry::factory()->for($this->treatment)->removed()->create();
        $expired = WaitlistEntry::factory()->for($this->treatment)->create(['earliest_date' => '2026-10-20', 'latest_date' => '2026-10-30']);

        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(ManageWaitlistEntries::class)
            ->assertCanSeeTableRecords([$waiting, $offered])
            ->assertCanNotSeeTableRecords([$booked, $removed, $expired])
            ->set('activeTab', 'booked')
            ->assertCanSeeTableRecords([$booked])
            ->set('activeTab', 'closed')
            ->assertCanSeeTableRecords([$removed, $expired])
            ->assertSee('Venció el plazo');
    }

    public function test_an_empty_list_says_what_that_list_is_for(): void
    {
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(ManageWaitlistEntries::class)
            ->assertSee(['Nadie en lista de espera', 'Anota aquí a quien quiere un horario antes'])
            ->set('activeTab', 'booked')
            ->assertSee(['Nadie de la lista se agendó todavía', 'Aquí quedan quienes pasaron de la lista de espera a una cita.'])
            ->assertDontSee('Anota aquí')
            ->set('activeTab', 'closed')
            ->assertSee(['Nadie retirado ni vencido', 'Aquí quedan quienes se retiraron de la lista'])
            ->assertDontSee('Anota aquí');
    }

    public function test_the_freed_slot_link_shows_who_can_come_that_day(): void
    {
        $canCome = WaitlistEntry::factory()->for($this->treatment)->create();
        $fromThursday = WaitlistEntry::factory()->for($this->treatment)->create(['earliest_date' => '2026-11-05']);

        $this->actingAs(User::factory()->reception()->create());

        Livewire::withQueryParams(['filters' => ['fits' => ['date' => '2026-11-04', 'doctor_id' => $this->doctor->id]]])
            ->test(ManageWaitlistEntries::class)
            ->assertCanSeeTableRecords([$canCome])
            ->assertCanNotSeeTableRecords([$fromThursday])
            ->assertSee('Pueden venir el mié. 4 nov. con Dra. Ana Torres');
    }

    public function test_offering_a_slot_opens_whatsapp_with_the_first_free_time_and_marks_it_offered(): void
    {
        $entry = WaitlistEntry::factory()
            ->for(Patient::factory()->state(['first_name' => 'Rosa', 'last_name' => 'Quispe']))
            ->for($this->treatment)
            ->create(['earliest_date' => '2026-11-03', 'preferred_period' => DayPeriod::Afternoon]);
        $reception = User::factory()->reception()->create();
        $this->actingAs($reception);

        Livewire::test(ManageWaitlistEntries::class)
            ->mountTableAction('offer', $entry)
            ->assertSchemaStateSet([
                'doctor_id' => $this->doctor->id,
                'date' => '2026-11-03',
                'slot' => '15:00',
                'body' => 'Hola Rosa, se liberó un horario para limpieza dental el martes 3 de noviembre a las 3:00 pm con Dra. Ana Torres. Te lo guardamos hasta las 10:00 am: ¿lo quieres? Respóndenos y te lo reservamos.',
            ])
            ->setTableActionData(['slot' => '16:30'])
            ->assertSchemaStateSet(['body' => 'Hola Rosa, se liberó un horario para limpieza dental el martes 3 de noviembre a las 4:30 pm con Dra. Ana Torres. Te lo guardamos hasta las 10:00 am: ¿lo quieres? Respóndenos y te lo reservamos.'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        // La pestaña se abre en el mismo toque (si no, el navegador la puede bloquear) y el aviso se queda con el
        // enlace y con qué hacer si al final no se le envió.
        $notice = $this->lastNotification();
        $this->assertSame('Oferta para Rosa Quispe abierta en WhatsApp', $notice['title']);
        $this->assertSame('El martes 3 de noviembre a las 16:30 queda apartado para Rosa hasta el lunes 2 de noviembre a las 10:00. Si al final no se le envió, toca «Liberar el horario» en la lista de espera para ofrecérselo a otro.', $notice['body']);
        $this->assertSame('Abrir de nuevo', $notice['actions'][0]['label']);
        $this->assertStringStartsWith('https://wa.me/', (string) $notice['actions'][0]['url']);

        $entry->refresh();
        $this->assertSame(WaitlistStatus::Offered, $entry->status);
        $this->assertSame('2026-11-03 16:30', $entry->offered_starts_at->format('Y-m-d H:i'));
        // Apartado dos horas, con la atención y la esterilización.
        $this->assertSame(['2026-11-02 10:00', '2026-11-03 17:10'], [$entry->held_until->format('Y-m-d H:i'), $entry->offered_until->format('Y-m-d H:i')]);

        $message = WhatsappMessage::sole();
        $this->assertSame(MessageTemplateKey::WaitlistOffer, $message->template_key);
        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertSame($entry->patient_id, $message->patient_id);
    }

    public function test_the_offer_window_shows_the_days_of_their_window_and_a_full_day_is_not_a_dead_end(): void
    {
        $entry = WaitlistEntry::factory()
            ->for(Patient::factory()->state(['first_name' => 'Rosa', 'last_name' => 'Quispe']))
            ->for($this->treatment)
            ->create(['latest_date' => '2026-11-05']);
        ScheduleBlock::create(['doctor_id' => $this->doctor->id, 'type' => ScheduleBlockType::Course, 'starts_at' => '2026-11-03 06:00', 'ends_at' => '2026-11-03 22:00']);
        $this->actingAs(User::factory()->reception()->create());
        $nextDay = TestAction::make('nextDayWithSlots')->schemaComponent('slot', 'mountedActionSchema0');

        Livewire::test(ManageWaitlistEntries::class)
            ->mountTableAction('offer', $entry)
            ->assertSchemaStateSet(['date' => '2026-11-02', 'slot' => '09:00'])
            // Solo los días de su plazo (hasta el jueves), cada uno con cuántos horarios le sirven, y hasta cuándo se
            // aparta (la de las 9:00 es en una hora: hasta media hora antes, para que alcance a llegar).
            ->assertMountedActionModalSee(['Hoy', 'Mañana', 'Sin lugar', 'Mié 4', 'Jue 5', '30 libres', 'Al abrir WhatsApp queda apartado para Rosa hasta las 08:30 mientras responde'])
            ->assertMountedActionModalDontSee('Vie 6')
            // El martes el doctor no atiende: se dice cuál es el siguiente y se llega con un toque.
            ->setTableActionData(['date' => '2026-11-03'])
            ->assertSchemaStateSet(['slot' => null, 'body' => ''])
            ->assertMountedActionModalSee('Ese día no hay un horario que le sirva. El siguiente con lugar es el miércoles 4 de noviembre.')
            ->assertActionVisible($nextDay)
            ->callAction($nextDay)
            ->assertSchemaStateSet(['date' => '2026-11-04', 'slot' => '09:00'])
            ->assertSchemaStateSet(['body' => 'Hola Rosa, se liberó un horario para limpieza dental el miércoles 4 de noviembre a las 9:00 am con Dra. Ana Torres. Te lo guardamos hasta las 10:00 am: ¿lo quieres? Respóndenos y te lo reservamos.']);

        // Cerrar la ventana no cancela nada.
        $this->assertSame('Volver', WaitlistEntryActions::offer()->getModalCancelActionLabel());
    }

    public function test_choosing_another_doctor_moves_to_their_next_day_with_room(): void
    {
        $other = $this->doctorOffering($this->treatment);
        ScheduleBlock::create(['doctor_id' => $other->id, 'type' => ScheduleBlockType::Course, 'starts_at' => '2026-11-02 06:00', 'ends_at' => '2026-11-03 22:00']);
        $entry = WaitlistEntry::factory()->for($this->treatment)->create();
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(ManageWaitlistEntries::class)
            ->mountTableAction('offer', $entry)
            ->assertSchemaStateSet(['doctor_id' => $this->doctor->id, 'date' => '2026-11-02'])
            ->setTableActionData(['doctor_id' => $other->id])
            ->assertSchemaStateSet(['date' => '2026-11-04', 'slot' => '09:00']);
    }

    public function test_the_list_flags_what_needs_doing_now_and_highlights_the_step_that_applies(): void
    {
        $service = app(WaitlistService::class);
        $dueTomorrow = WaitlistEntry::factory()->for($this->treatment)->create(['latest_date' => '2026-11-03']);
        $dueToday = WaitlistEntry::factory()->for($this->treatment)->create(['latest_date' => '2026-11-02']);
        $holding = WaitlistEntry::factory()->for($this->treatment)->create();
        $service->markOffered($holding, $this->doctor, $this->treatment, now()->parse('2026-11-04 10:00'));
        // No respondió a tiempo (o dijo que no): el apartado se soltó y sigue en la lista.
        $notTaken = WaitlistEntry::factory()->for($this->treatment)->create();
        $service->markOffered($notTaken, $this->doctor, $this->treatment, now()->parse('2026-11-04 11:00'));
        $service->release($notTaken);
        $this->actingAs(User::factory()->reception()->create());

        $list = Livewire::test(ManageWaitlistEntries::class)
            ->assertSee(['Buscar paciente, DNI o celular', 'Filtros', 'Acciones', 'Quien se anotó primero'])
            ->assertSeeHtml(['<span class="waitlist-alert">Vence mañana</span>', '<span class="waitlist-alert">Vence hoy</span>'])
            ->assertTableColumnFormattedStateSet('status', 'En espera', $dueTomorrow)
            ->assertTableColumnFormattedStateSet('status', 'Avisado', $holding)
            ->assertTableColumnFormattedStateSet('status', 'No lo tomó', $notTaken);

        // En ámbar solo lo que pide hacer algo: esperar es lo normal y va en gris.
        $this->assertSame(['gray', 'info', 'warning'], [$this->statusColor($list, $dueTomorrow), $this->statusColor($list, $holding), $this->statusColor($list, $notTaken)]);

        // El paso que toca: ofrecerle un horario, o agendarlo si ya tiene uno apartado.
        $this->assertSame(['offer'], $this->mainSteps($dueTomorrow));
        $this->assertSame(['offer'], $this->mainSteps($notTaken));
        $this->assertSame(['book'], $this->mainSteps($holding));
    }

    /** @return list<string> */
    private function mainSteps(WaitlistEntry $entry): array
    {
        return collect([WaitlistEntryActions::offer(), WaitlistEntryActions::book()])
            ->filter(fn ($action) => str_contains($action->record($entry->fresh())->getExtraAttributeBag()->get('class', ''), 'appt-main-step'))
            ->map(fn ($action) => $action->getName())
            ->values()
            ->all();
    }

    private function statusColor(Testable $list, WaitlistEntry $entry): string
    {
        $column = $list->instance()->getTable()->getColumn('status')->record($entry->fresh());

        return (string) $column->getColor($column->getState());
    }

    public function test_a_slot_taken_meanwhile_is_not_offered(): void
    {
        $entry = WaitlistEntry::factory()->for($this->treatment)->create(['earliest_date' => '2026-11-03']);
        $this->actingAs(User::factory()->reception()->create());

        $page = Livewire::test(ManageWaitlistEntries::class)->mountTableAction('offer', $entry);
        Appointment::factory()->for($this->doctor)->for($this->treatment)->confirmed()->at('2026-11-03 09:00')->create();

        // Ya no aparece entre los horarios libres: hay que elegir otro.
        $page->callMountedTableAction()->assertHasTableActionErrors(['slot']);

        $this->assertSame(WaitlistStatus::Waiting, $entry->fresh()->status);
        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_booking_uses_the_offered_slot_or_moves_the_appointment_the_patient_already_has(): void
    {
        $offered = WaitlistEntry::factory()->for($this->treatment)->offered()->create([
            'offered_starts_at' => '2026-11-03 16:30',
            'offered_doctor_id' => $this->doctor->id,
        ]);
        $hasAppointment = WaitlistEntry::factory()->for($this->treatment)->create();
        $appointment = Appointment::factory()->for($hasAppointment->patient)->for($this->doctor)->for($this->treatment)->at('2026-11-20 10:00')->create();

        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(ManageWaitlistEntries::class)
            ->assertTableActionHasLabel('book', 'Agendar', $offered)
            ->assertTableActionHasUrl('book', AppointmentResource::newUrl([
                'patient' => $offered->patient_id,
                'treatment' => $this->treatment->id,
                'doctor' => $this->doctor->id,
                'date' => '2026-11-03',
                'time' => '16:30',
            ]), $offered)
            ->assertTableActionHasLabel('book', 'Adelantar cita', $hasAppointment)
            ->assertTableActionHasUrl('book', AppointmentResource::getUrl('view', ['record' => $appointment, 'action' => 'move']), $hasAppointment)
            ->assertSee('Ya tiene cita el vie. 20 nov., 10:00: adelántasela');
    }

    public function test_advancing_an_appointment_opens_move_with_the_offered_time_already_agreed(): void
    {
        $entry = WaitlistEntry::factory()->for($this->treatment)->for(Patient::factory()->state(['first_name' => 'Rosa', 'last_name' => 'Quispe']))->create();
        $appointment = Appointment::factory()->for($entry->patient)->for($this->doctor)->for($this->treatment)->confirmed()->at('2026-11-20 10:00')->create();
        app(WaitlistService::class)->markOffered($entry, $this->doctor, $this->treatment, now()->parse('2026-11-04 10:00'));
        $this->actingAs(User::factory()->reception()->create());

        $arguments = ['doctor' => $this->doctor->id, 'date' => '2026-11-04', 'time' => '10:00'];
        Livewire::test(ManageWaitlistEntries::class)
            ->assertTableActionHasUrl('book', AppointmentResource::getUrl('view', ['record' => $appointment, 'action' => 'move', 'actionArguments' => $arguments]), $entry);

        // Aceptó por WhatsApp: "Mover" ya trae ese horario, acordado con ella. Al moverla, sale de la lista.
        Livewire::withQueryParams(['action' => 'move', 'actionArguments' => $arguments])
            ->test(ViewAppointment::class, ['record' => $appointment->getKey()])
            ->call('mountDefaultActions')
            ->assertActionDataSet(['doctor_id' => $this->doctor->id, 'date' => '2026-11-04', 'slot' => '10:00', 'agreed' => true])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(['2026-11-04 10:00', AppointmentStatus::Confirmed], [$appointment->fresh()->starts_at->format('Y-m-d H:i'), $appointment->fresh()->status]);
        $this->assertFalse($entry->fresh()->isActive());
    }

    public function test_reception_removes_someone_who_no_longer_waits(): void
    {
        $entry = WaitlistEntry::factory()->for($this->treatment)->create(['notes' => 'Prefiere sábados']);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(ManageWaitlistEntries::class)
            ->callTableAction('remove', $entry, ['reason' => 'Se atendió en otro lado'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(WaitlistStatus::Removed, $entry->fresh()->status);
        $this->assertSame("Prefiere sábados\nRetirado: Se atendió en otro lado", $entry->fresh()->notes);
    }

    public function test_the_held_slot_shows_in_the_agenda_and_can_be_released(): void
    {
        $entry = WaitlistEntry::factory()
            ->for(Patient::factory()->state(['first_name' => 'Rosa', 'last_name' => 'Quispe']))
            ->for($this->treatment)
            ->create();
        app(WaitlistService::class)->markOffered($entry, $this->doctor, $this->treatment, now()->parse('2026-11-04 10:00'));
        $this->actingAs(User::factory()->reception()->create());

        Livewire::withQueryParams(['date' => '2026-11-04', 'mode' => 'day', 'doctor' => $this->doctor->id])
            ->test(Agenda::class)
            ->assertSee('Apartado para Rosa Quispe · hasta las 10:00');

        Livewire::test(ManageWaitlistEntries::class)
            ->assertSee('apartado hasta las 10:00')
            ->callTableAction('release', $entry)
            ->assertNotified('Horario liberado');

        $this->assertNull($entry->fresh()->held_until);
        $this->assertSame(WaitlistStatus::Offered, $entry->fresh()->status, 'Sigue en la lista, ya avisado.');
    }

    public function test_freeing_a_held_time_by_hand_says_who_on_the_list_can_take_it_now(): void
    {
        $rosa = WaitlistEntry::factory()->for(Patient::factory()->state(['first_name' => 'Rosa', 'last_name' => 'Quispe']))->for($this->treatment)->create(['created_at' => now()->subDays(2)]);
        $luis = WaitlistEntry::factory()->for(Patient::factory()->state(['first_name' => 'Luis', 'last_name' => 'Soto']))->for($this->treatment)->create(['created_at' => now()->subDay()]);
        $service = app(WaitlistService::class);
        $service->markOffered($rosa, $this->doctor, $this->treatment, now()->parse('2026-11-04 10:00'));
        $this->actingAs(User::factory()->reception()->create());

        // Rosa no puede: al liberarlo se dice que Luis sí, con el botón para ofrecérselo, como cuando el apartado vence solo.
        Livewire::test(ManageWaitlistEntries::class)->callTableAction('release', $rosa);

        $notice = $this->lastNotification();
        $this->assertSame('Horario liberado', $notice['title']);
        $this->assertSame('Mié. 4 nov., 10:00 a. m. con Dra. Ana Torres quedó libre. Luis Soto, de la lista de espera, puede venir a esa hora.', $notice['body']);
        $this->assertSame('Ofrecérselo a Luis', $notice['actions'][0]['label']);
        $this->assertSame(WaitlistEntryResource::urlForOffer($luis, $this->doctor, now()->parse('2026-11-04 10:00')), $notice['actions'][0]['url']);
        // Se va solo: no se queda tapando los botones de arriba.
        $this->assertSame(WaitlistEntryActions::FREED_NOTICE_SECONDS * 1000, $notice['duration']);

        // Se le aparta a Luis y lo retiran de la lista: el horario también queda libre, y ya no hay a quién (a Rosa ya se le ofreció).
        $service->markOffered($luis, $this->doctor, $this->treatment, now()->parse('2026-11-04 10:00'));

        Livewire::test(ManageWaitlistEntries::class)->callTableAction('remove', $luis->fresh(), ['reason' => 'Ya no puede']);

        $removed = $this->lastNotification();
        $this->assertSame(['Retirado de la lista de espera', 'Mié. 4 nov., 10:00 a. m. con Dra. Ana Torres quedó libre para la agenda y la web.'], [$removed['title'], $removed['body']]);
    }

    public function test_the_freed_slot_alert_opens_the_agenda_with_the_offer_ready(): void
    {
        $entry = WaitlistEntry::factory()
            ->for(Patient::factory()->state(['first_name' => 'Rosa', 'last_name' => 'Quispe']))
            ->for($this->treatment)
            ->create();
        $this->actingAs(User::factory()->reception()->create());

        $url = WaitlistEntryResource::urlForOffer($entry, $this->doctor, now()->parse('2026-11-04 10:00'));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->get($url)->assertOk();

        // La página pide abrir la ventana apenas carga (wire:init).
        Livewire::withQueryParams($query)
            ->test(Agenda::class)
            ->call('mountDefaultActions')
            ->assertSet('mountedActions.0.name', 'offer')
            ->assertSchemaStateSet(['doctor_id' => $this->doctor->id, 'date' => '2026-11-04', 'slot' => '10:00'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('2026-11-04 10:00', $entry->fresh()->offered_starts_at->format('Y-m-d H:i'));
    }

    public function test_the_week_gaps_say_how_many_on_the_waitlist_can_come_each_day(): void
    {
        WaitlistEntry::factory()->for($this->treatment)->count(2)->create();
        WaitlistEntry::factory()->for($this->treatment)->create(['earliest_date' => '2026-11-03']);

        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(WeekGaps::class)
            ->assertSeeInOrder(['Hoy', '2 de la lista de espera pueden venir', 'Mañana', '3 de la lista de espera pueden venir']);
    }
}
