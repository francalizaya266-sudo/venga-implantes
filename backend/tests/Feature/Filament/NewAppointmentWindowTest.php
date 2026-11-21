<?php

namespace Tests\Feature\Filament;

use App\Enums\DocumentType;
use App\Enums\RequestedBy;
use App\Enums\RescheduleRequestStatus;
use App\Filament\Pages\Agenda;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Resources\Appointments\Pages\ViewAppointment;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WaitlistEntry;
use Database\Seeders\CatalogSeeder;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** "Nueva cita" es una sola: la ventana. Se abre desde cualquier pantalla y, al agendar, se vuelve a ella. */
class NewAppointmentWindowTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Treatment $treatment;

    private Doctor $doctor;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
        $this->freezeMonday();
        $this->treatment = Treatment::factory()->create(['duration_minutes' => 30, 'price_from' => 80]);
        $this->doctor = $this->doctorOffering($this->treatment);
        $this->patient = Patient::factory()->create();
        $this->actingAs(User::factory()->reception()->create());
    }

    public function test_the_old_page_address_opens_the_window_with_what_the_link_carried(): void
    {
        $this->get(route('filament.admin.resources.citas.create', ['patient' => $this->patient->id, 'date' => '2026-11-05', 'other' => 'x']))
            ->assertRedirect(AppointmentResource::newUrl(['patient' => $this->patient->id, 'date' => '2026-11-05']));

        $this->get(AppointmentResource::newUrl(['patient' => $this->patient->id, 'date' => '2026-11-05']))->assertOk();
    }

    public function test_booking_from_another_screen_goes_back_to_that_screen(): void
    {
        $patientPage = PatientResource::getUrl('view', ['record' => $this->patient]);

        $window = $this->windowOpenedFrom($patientPage, ['patient' => $this->patient->id])
            ->assertActionDataSet(['patient_id' => $this->patient->id]);

        $this->assertSame(['Volver sin agendar', $patientPage], [$this->cancelButton($window)->getLabel(), $this->cancelButton($window)->getUrl()]);

        $window
            ->setActionData(['doctor_id' => $this->doctor->id, 'treatment_id' => $this->treatment->id, 'date' => '2026-11-03', 'slot' => '10:00'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Cita creada. El aviso al paciente está listo en Avisos.')
            ->assertRedirect($patientPage);

        $this->assertSame('2026-11-03 10:00', $this->patient->appointments()->sole()->starts_at->format('Y-m-d H:i'));
    }

    public function test_a_booking_that_fails_stays_in_the_window(): void
    {
        Appointment::factory()->for($this->doctor)->for($this->treatment)->at('2026-11-03 10:00')->create();

        $this->windowOpenedFrom(Dashboard::getUrl(), ['patient' => $this->patient->id])
            ->setActionData(['doctor_id' => $this->doctor->id, 'treatment_id' => $this->treatment->id, 'date' => '2026-11-03', 'manual' => true, 'manual_time' => '10:15'])
            ->callMountedAction()
            ->assertNoRedirect();

        $this->assertSame(0, $this->patient->appointments()->count());
    }

    public function test_it_only_goes_back_to_a_screen_of_the_panel_and_only_when_the_link_opened_the_window(): void
    {
        $book = ['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'treatment_id' => $this->treatment->id, 'date' => '2026-11-03', 'slot' => '10:00'];

        foreach (['https://otro-sitio.test/panel/pacientes', url('/'), Agenda::getUrl(['date' => '2026-11-03'])] as $from) {
            $this->windowOpenedFrom($from, ['patient' => $this->patient->id])
                ->assertSet('returnTo', null)
                ->setActionData($book)
                ->callMountedAction()
                ->assertNoRedirect();

            Appointment::query()->delete();
        }

        // Llegó con el enlace, cerró la ventana y después tocó un espacio libre de la agenda: se queda en la agenda.
        $window = Livewire::withHeaders(['Referer' => Dashboard::getUrl()])
            ->withQueryParams(['action' => 'createAppointment'])
            ->test(Agenda::class)
            ->assertSet('returnTo', Dashboard::getUrl())
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id, 'date' => '2026-11-03', 'time' => '10:00']);

        $this->assertNull($this->cancelButton($window)->getUrl());

        $window
            ->setActionData($book)
            ->callMountedAction()
            ->assertNoRedirect();
    }

    public function test_the_list_of_appointments_books_in_the_same_window_without_leaving(): void
    {
        Livewire::test(ListAppointments::class)
            ->mountAction('createAppointment')
            ->assertActionDataSet(['doctor_id' => $this->doctor->id, 'date' => '2026-11-02'])
            ->setActionData(['patient_id' => $this->patient->id, 'treatment_id' => $this->treatment->id, 'date' => '2026-11-03', 'slot' => '10:00'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNoRedirect()
            ->assertCanSeeTableRecords([$this->patient->appointments()->sole()]);
    }

    public function test_unticking_new_patient_brings_back_the_patient_that_was_chosen(): void
    {
        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['patient' => $this->patient->id])
            ->setActionData(['new_patient' => true])
            ->assertActionDataSet(['patient_id' => null])
            ->setActionData(['new_patient' => false])
            ->assertActionDataSet(['patient_id' => $this->patient->id]);
    }

    public function test_another_time_starts_with_the_hour_that_was_chosen_or_tapped(): void
    {
        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id, 'date' => '2026-11-03', 'time' => '16:00', 'treatment' => $this->treatment->id])
            ->setActionData(['manual' => true])
            ->assertActionDataSet(['manual_time' => '16:00'])
            // Una hora ya escrita no se pisa al volver a prender "Otra hora".
            ->setActionData(['manual_time' => '19:30', 'manual' => false])
            ->setActionData(['manual' => true])
            ->assertActionDataSet(['manual_time' => '19:30']);

        // Sin tratamiento todavía, la hora que se tocó en la agenda.
        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id, 'date' => '2026-11-03', 'time' => '18:20'])
            ->setActionData(['manual' => true])
            ->assertActionDataSet(['manual_time' => '18:20']);
    }

    public function test_another_time_is_chosen_with_two_taps_instead_of_typing_it(): void
    {
        $window = Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['patient' => $this->patient->id, 'doctor' => $this->doctor->id, 'date' => '2026-11-03', 'treatment' => $this->treatment->id])
            ->setActionData(['manual' => true])
            ->assertMountedActionModalSee('Toca la hora y, si no es en punto, los minutos.')
            // Sin hora no se agenda, y se dice en los botones de la hora.
            ->callMountedAction()
            ->assertHasFormErrors(['manual_hour' => 'Completa el campo hora.'])
            // Con solo tocar la hora queda en punto.
            ->setActionData(['manual_hour' => '19'])
            ->assertActionDataSet(['manual_time' => '19:00', 'manual_minute' => '00'])
            ->setActionData(['manual_minute' => '20'])
            ->assertActionDataSet(['manual_time' => '19:20'])
            ->assertMountedActionModalSee("La cita empieza a las 19:20 (7:20\u{00A0}p.\u{00A0}m.).")
            ->setActionData(['manual_hour' => '07'])
            ->assertActionDataSet(['manual_time' => '07:20'])
            ->assertMountedActionModalSee('La cita empieza a las 07:20.');

        $window->callMountedAction()->assertHasNoFormErrors();

        $this->assertSame('2026-11-03 07:20', Appointment::query()->whereBelongsTo($this->patient)->sole()->starts_at->format('Y-m-d H:i'));
    }

    public function test_the_minutes_tapped_first_wait_for_the_hour(): void
    {
        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id, 'date' => '2026-11-03', 'treatment' => $this->treatment->id])
            ->setActionData(['manual' => true])
            ->setActionData(['manual_minute' => '45'])
            ->assertActionDataSet(['manual_time' => null])
            ->setActionData(['manual_hour' => '21'])
            ->assertActionDataSet(['manual_time' => '21:45']);
    }

    public function test_the_hour_that_was_already_chosen_comes_marked_in_the_buttons(): void
    {
        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id, 'date' => '2026-11-03', 'time' => '16:00', 'treatment' => $this->treatment->id])
            ->setActionData(['manual' => true])
            ->assertActionDataSet(['manual_time' => '16:00', 'manual_hour' => '16', 'manual_minute' => '00'])
            // Una hora que no es de las que se ofrecen (de 06 a 22, de cinco en cinco) igual tiene su botón.
            ->setActionData(['manual_time' => '23:07'])
            ->assertActionDataSet(['manual_hour' => '23', 'manual_minute' => '07'])
            ->assertMountedActionModalSee("La cita empieza a las 23:07 (11:07\u{00A0}p.\u{00A0}m.).");
    }

    public function test_the_summary_says_the_day_when_it_ends_how_long_it_lasts_and_its_price(): void
    {
        $free = Treatment::factory()->create(['duration_minutes' => 45, 'price_from' => 0]);
        $unpriced = Treatment::factory()->create(['duration_minutes' => 90, 'price_from' => null]);
        $this->doctor->treatments()->attach([$free->id, $unpriced->id]);

        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id, 'date' => '2026-11-03'])
            ->assertMountedActionModalSee('Elige el tratamiento y el horario: aquí verás qué día, a qué hora termina y cuánto cuesta.')
            ->setActionData(['treatment_id' => $this->treatment->id])
            ->assertMountedActionModalSee(['<dd>'.e($this->doctor->name).'</dd>', '<dd>Mañana, martes 3 de noviembre</dd>', '<dd>Falta elegirla</dd>', "<dd>30\u{00A0}min</dd>", '<dd>Desde S/ 80</dd>'], escape: false)
            ->setActionData(['slot' => '10:00'])
            ->assertMountedActionModalSee('<dd>10:00 a 10:30</dd>', escape: false)
            ->setActionData(['manual' => true, 'manual_time' => '19:15', 'treatment_id' => $free->id])
            ->assertMountedActionModalSee(['<dd>19:15 a 20:00</dd>', '<dd>Sin costo</dd>'], escape: false)
            ->setActionData(['treatment_id' => $unpriced->id])
            ->assertMountedActionModalSee(['<dd>19:15 a 20:45</dd>', "<dd>1\u{00A0}h 30\u{00A0}min</dd>"], escape: false)
            ->assertMountedActionModalDontSee('<dt>Precio</dt>', escape: false)
            // Repetir también se ve en el resumen: cada cuánto y cuántas en total.
            ->setActionData(['repeat' => true])
            ->assertMountedActionModalSee('<dd>Cada 4 semanas, 6 citas</dd>', escape: false);
    }

    public function test_with_a_single_doctor_the_window_does_not_ask_who_and_the_summary_says_it(): void
    {
        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['date' => '2026-11-03'])
            ->assertActionDataSet(['doctor_id' => $this->doctor->id])
            ->assertMountedActionModalDontSee('data.doctor_id', escape: false)
            ->setActionData(['treatment_id' => $this->treatment->id, 'slot' => '10:00', 'patient_id' => $this->patient->id])
            ->assertMountedActionModalSee('<dd>'.e($this->doctor->name).'</dd>', escape: false)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertTrue($this->patient->appointments()->sole()->doctor->is($this->doctor));

        // Con un segundo doctor que hace lo mismo, hay a quién elegir: la lista vuelve.
        $this->doctorOffering($this->treatment);

        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['date' => '2026-11-03'])
            ->assertMountedActionModalSee('data.doctor_id', escape: false);
    }

    public function test_another_appointment_is_flagged_in_amber_only_when_it_may_be_the_same(): void
    {
        $control = Treatment::factory()->create(['name' => 'Control', 'duration_minutes' => 30]);
        $this->doctor->treatments()->attach($control->id);
        $next = Appointment::factory()->for($this->doctor)->for($control)->for($this->patient)->at('2026-11-30 10:00')->confirmed()->create();
        Appointment::factory()->for($this->doctor)->for($control)->for($this->patient)->at('2026-12-28 10:00')->create();

        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id, 'date' => '2026-11-03'])
            ->setActionData(['patient_id' => $this->patient->id, 'treatment_id' => $this->treatment->id])
            // Un control lejano y otro tratamiento: lo normal en quien viene a controles. Se dice, sin alarma.
            ->assertMountedActionModalSee(['Ya tiene cita el lunes 30 de noviembre a las 10:00', 'Control · Confirmada (y 1 cita más)', 'Ver o mover esa cita'])
            ->assertMountedActionModalSee(AppointmentResource::getUrl('view', ['record' => $next, 'action' => 'move']))
            ->assertMountedActionModalDontSee('Continúa solo si necesita una cita adicional.')
            // El mismo tratamiento: puede ser la misma cita.
            ->setActionData(['treatment_id' => $control->id])
            ->assertMountedActionModalSee('Continúa solo si necesita una cita adicional.')
            // Ese mismo día: se dice que ya tiene una ahí, aunque sea otro tratamiento.
            ->setActionData(['treatment_id' => $this->treatment->id, 'date' => '2026-11-30'])
            ->assertMountedActionModalSee(['Ese día ya tiene cita a las 10:00', 'Continúa solo si necesita una cita adicional.']);
    }

    public function test_a_treatment_outside_the_most_booked_ones_can_be_booked(): void
    {
        $others = collect(range(1, 5))->map(fn (int $order) => Treatment::factory()->create(['name' => 'Tratamiento '.$order, 'duration_minutes' => 30, 'sort_order' => $order]));
        $this->doctor->treatments()->attach($others->pluck('id')->all());
        $rare = $others->last();

        Livewire::test(Dashboard::class)
            ->mountAction('newAppointment')
            ->setActionData(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id])
            // Elegido de la lista: no es un atajo, y no queda marcado como si lo fuera.
            ->setActionData(['treatment_id' => $rare->id])
            ->assertActionDataSet(['treatment_shortcut' => null])
            ->setActionData(['date' => '2026-11-03', 'slot' => '10:00'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame($rare->id, $this->patient->appointments()->sole()->treatment_id);
    }

    public function test_a_new_patient_with_a_document_already_registered_is_told_instead_of_failing(): void
    {
        $this->patient->update(['document_type' => DocumentType::Dni, 'document_number' => '45678912']);

        Livewire::test(Dashboard::class)
            ->mountAction('newAppointment')
            ->setActionData([
                'new_patient' => true,
                'patient' => ['first_name' => 'Rosa', 'last_name' => 'Quispe', 'phone' => '987654321', 'document_type' => DocumentType::Dni->value, 'document_number' => '45678912'],
                'doctor_id' => $this->doctor->id,
                'treatment_id' => $this->treatment->id,
                'date' => '2026-11-03',
                'slot' => '10:00',
            ])
            ->callMountedAction()
            ->assertHasActionErrors(['patient.document_number' => 'unique']);

        $this->assertSame(1, Patient::query()->count());
    }

    public function test_an_appointment_on_the_same_day_says_today_when_it_is_today(): void
    {
        Appointment::factory()->for($this->doctor)->for($this->treatment)->for($this->patient)->at('2026-11-02 11:00')->confirmed()->create();

        Livewire::test(Dashboard::class)
            ->mountAction('newAppointment')
            ->setActionData(['patient_id' => $this->patient->id, 'date' => '2026-11-02'])
            ->assertMountedActionModalSee('Hoy ya tiene cita a las 11:00')
            ->assertMountedActionModalDontSee('Ese día ya tiene cita');
    }

    public function test_a_change_the_patient_asked_for_is_pointed_out_instead_of_booking_another(): void
    {
        $asked = Appointment::factory()->for($this->doctor)->for($this->treatment)->for($this->patient)->at('2026-11-30 10:00')->confirmed()->create();
        $asked->rescheduleRequests()->create(['requested_by' => RequestedBy::Patient, 'status' => RescheduleRequestStatus::Pending]);

        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['date' => '2026-11-03'])
            ->setActionData(['patient_id' => $this->patient->id])
            ->assertMountedActionModalSee(['Pidió cambiar esta cita: resuélvelo en "Cambios pedidos" en vez de crear otra.', 'Ver el cambio que pidió'])
            ->assertMountedActionModalDontSee('Ver o mover esa cita');
    }

    public function test_the_link_to_the_other_appointment_opens_it_ready_to_move(): void
    {
        $next = Appointment::factory()->for($this->doctor)->for($this->treatment)->for($this->patient)->at('2026-11-30 10:00')->confirmed()->create();

        $page = Livewire::withQueryParams(['action' => 'move'])
            ->test(ViewAppointment::class, ['record' => $next->getRouteKey()])
            ->call('mountDefaultActions');

        $this->assertSame('move', $page->instance()->getMountedAction()?->getName());
    }

    public function test_a_patient_on_the_waitlist_is_pointed_out_and_the_notice_says_whether_this_booking_takes_them_off(): void
    {
        $this->treatment->update(['name' => 'Limpieza dental']);
        $other = Treatment::factory()->create(['name' => 'Curación con resina', 'duration_minutes' => 30]);
        $this->doctor->treatments()->attach($other);
        WaitlistEntry::factory()->for($this->patient)->for($this->treatment)->create();

        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['date' => '2026-11-03'])
            ->setActionData(['patient_id' => $this->patient->id, 'treatment_id' => $other->id])
            ->assertMountedActionModalSee(['Está en la lista de espera desde el 2 de noviembre', 'Espera un horario para Limpieza dental. Si le agendas eso, sale solo de la lista.'])
            ->setActionData(['treatment_id' => $this->treatment->id])
            ->assertMountedActionModalSee('Espera un horario para Limpieza dental: al agendarle esta cita, sale solo de la lista.');
    }

    /** El botón para cerrar la ventana sin agendar. */
    private function cancelButton(Testable $window): Action
    {
        return $window->instance()->getMountedAction()->getModalCancelAction();
    }

    /**
     * La ventana abierta por el enlace "Agendar" de otra pantalla.
     *
     * @param  array<string, int|string>  $with
     */
    private function windowOpenedFrom(string $screen, array $with): Testable
    {
        return Livewire::withHeaders(['Referer' => $screen])
            ->withQueryParams(['action' => 'createAppointment', 'actionArguments' => $with])
            ->test(Agenda::class)
            ->call('mountDefaultActions');
    }
}
