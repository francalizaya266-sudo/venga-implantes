<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\ScheduleBlockType;
use App\Enums\TreatmentPlanStatus;
use App\Filament\Pages\Agenda;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Settings;
use App\Filament\Pages\Summary;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Resources\Appointments\Pages\ViewAppointment;
use App\Filament\Resources\Consultations\ConsultationResource;
use App\Filament\Resources\TreatmentPlans\Pages\ListTreatmentPlans;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Filament\Resources\WaitlistEntries\Pages\ManageWaitlistEntries;
use App\Filament\Resources\WaitlistEntries\WaitlistEntryResource;
use App\Filament\Widgets\DayMomentCard;
use App\Filament\Widgets\FollowUpsToday;
use App\Filament\Widgets\MonthOverview;
use App\Filament\Widgets\MonthTrendChart;
use App\Filament\Widgets\NextPatient;
use App\Filament\Widgets\OpenConsultations;
use App\Filament\Widgets\Reports\SummaryStats;
use App\Filament\Widgets\SystemHealth;
use App\Filament\Widgets\TodayAppointments;
use App\Filament\Widgets\TodayStats;
use App\Filament\Widgets\WaitingRoom;
use App\Filament\Widgets\WeekGaps;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Consent;
use App\Models\ConsentTemplate;
use App\Models\Consultation;
use App\Models\DailyClosing;
use App\Models\Doctor;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\ScheduleBlock;
use App\Models\Setting;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WhatsappMessage;
use App\Services\Booking\AppointmentService;
use App\Services\Clinical\TreatmentPlanService;
use App\Services\Dashboard\DayMoment;
use App\Services\Dashboard\DoctorDay;
use App\Services\Dashboard\FollowUps;
use App\Services\Dashboard\MonthFigures;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Tests\Concerns\AnswersWhatsAppQuestion;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

class DashboardPanelTest extends TestCase
{
    use AnswersWhatsAppQuestion, CreatesAgenda, LazilyRefreshDatabase;

    private const NBSP = "\u{00A0}";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('web');
        $this->freezeMonday();
    }

    public function test_each_role_sees_its_own_cards(): void
    {
        $doctor = Doctor::factory()->withSchedule()->create();

        $this->actingAs(User::factory()->admin()->create());
        // Lo del mes va al final, después de todo lo del día, en una línea.
        $this->assertSame([SystemHealth::class, TodayStats::class, DayMomentCard::class, TodayAppointments::class, WaitingRoom::class, FollowUpsToday::class, WeekGaps::class, MonthOverview::class], $this->visibleWidgets());
        $this->get(Dashboard::getUrl())->assertOk()->assertSee(['Lunes 2 de noviembre', 'Nueva cita', 'Llegó sin cita', 'Cobrar', 'Anotar en lista de espera']);

        // Quien administra y también atiende ve la clínica entera, en una sola pantalla. Hoy no tiene citas: su
        // próximo paciente no ocupa lugar.
        $dentist = User::factory()->admin(isDentist: true)->create();
        Doctor::factory()->withSchedule()->create(['user_id' => $dentist->id]);
        $this->actingAs($dentist);
        $this->assertSame([SystemHealth::class, TodayStats::class, DayMomentCard::class, TodayAppointments::class, WaitingRoom::class, FollowUpsToday::class, WeekGaps::class, MonthOverview::class], $this->visibleWidgets());

        $this->actingAs(User::factory()->reception()->create());
        $this->assertSame([TodayStats::class, DayMomentCard::class, TodayAppointments::class, WaitingRoom::class, FollowUpsToday::class, WeekGaps::class], $this->visibleWidgets());
        $this->get(Dashboard::getUrl())->assertOk();
        Livewire::test(TodayStats::class)
            ->assertSeeInOrder(['Citas de hoy', 'En sala ahora', 'Por confirmar', 'Cobrado hoy'])
            // Las horas libres se ven en "Huecos de la semana", no también aquí.
            ->assertDontSee(['Horas libres', 'Cambios pedidos', 'Avisos por enviar']);

        // El doctor ve su paciente; cómo va el mes, solo si el consultorio se lo permite.
        $this->actingAs($doctor->user);
        $this->assertSame([TodayStats::class, NextPatient::class, TodayAppointments::class], $this->visibleWidgets());
        $this->get(Dashboard::getUrl())->assertOk()->assertSee(['Nueva cita', 'Llegó sin cita'])->assertDontSee(['Cobrar', 'Anotar en lista de espera']);
        Livewire::test(TodayStats::class)
            ->assertSeeInOrder(['Mis citas de hoy', 'Por confirmar', 'Cambios pedidos', 'Avisos por enviar'])
            ->assertDontSee(['Cobrado hoy', 'Horas libres', 'En sala ahora']);

        Setting::set('reports.doctors_see_own', true);
        $this->assertSame([TodayStats::class, NextPatient::class, TodayAppointments::class, MonthOverview::class], $this->visibleWidgets());
        $this->get(Dashboard::getUrl())->assertOk();
    }

    public function test_the_admin_who_also_treats_sees_their_next_patient_and_the_whole_clinic_on_one_screen(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Curación con resina']);
        $dentist = User::factory()->admin(isDentist: true)->create(['name' => 'Dr. Jassmanny Neil Vega Jaen']);
        $own = $this->doctorOffering($treatment);
        $own->update(['user_id' => $dentist->id]);
        $other = $this->doctorOffering($treatment);
        Appointment::factory()->for($own)->for($treatment)->for($this->patient('Rosa', 'Quispe'))->at('2026-11-02 10:00')->confirmed()->create();
        Appointment::factory()->for($other)->for($treatment)->for($this->patient('Luis', 'Paz'))->at('2026-11-02 07:30')
            ->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => now()->subMinutes(25)]);
        $this->actingAs($dentist);

        // Sin elegir vista: su próximo paciente arriba y, en la misma pantalla, la sala, lo que hay que hacer, los
        // huecos y el mes de todos.
        $this->get(Dashboard::getUrl())->assertOk()
            ->assertSee(['Buenos días, Dr. Jassmanny', 'Tu próximo paciente', 'Sala de espera'])
            ->assertDontSee(['Mi consulta', 'Toda la clínica']);
        $this->assertSame([SystemHealth::class, TodayStats::class, DayMomentCard::class, NextPatient::class, TodayAppointments::class, WaitingRoom::class, FollowUpsToday::class, WeekGaps::class, MonthOverview::class], $this->visibleWidgets());
        Livewire::test(NextPatient::class)->assertSee('Rosa Quispe')->assertDontSee('Luis Paz');
        Livewire::test(TodayAppointments::class)->assertSee(['Agenda de hoy', 'Rosa Quispe', 'Luis Paz']);
        // Quien espera hace 20 minutos o más se marca en rojo arriba, aunque la sala quede abajo al hacer scroll.
        Livewire::test(TodayStats::class)
            ->assertSeeInOrder(['Citas de hoy', 'En sala ahora', 'El que más espera: 25'.self::NBSP.'min', 'Por confirmar'])
            ->assertSeeHtml('class="dash-kpi is-danger"');

        // Un enlace guardado de antes, con la vista, abre la misma pantalla.
        $this->get(Dashboard::getUrl(['vista' => 'mi-consulta']))->assertOk()->assertSee(['Tu próximo paciente', 'Sala de espera']);

        // El día que no atiende, "Hoy" es de la clínica: su próximo paciente no ocupa lugar.
        $this->travelTo(CarbonImmutable::parse('2026-11-03 08:00', 'America/Lima'));
        $this->assertNotContains(NextPatient::class, $this->visibleWidgets());
        $this->assertContains(WaitingRoom::class, $this->visibleWidgets());
    }

    public function test_with_several_doctors_the_agenda_of_today_shows_one_and_remembers_it(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $ana = $this->doctorOffering($treatment);
        $ana->update(['name' => 'Dra. Ana Torres']);
        $luis = $this->doctorOffering($treatment);
        $luis->update(['name' => 'Dr. Luis Rojas']);
        $this->doctorOffering($treatment);
        Appointment::factory()->for($ana)->for($treatment)->for($this->patient('Rosa', 'Quispe'))->at('2026-11-02 10:00')->create();
        Appointment::factory()->for($luis)->for($treatment)->for($this->patient('Pedro', 'Paz'))->at('2026-11-02 11:00')->create();
        $this->actingAs(User::factory()->reception()->create());

        // Abre con todos; al elegir un doctor, solo sus citas (y ya no hace falta decir de quién es cada una).
        Livewire::test(TodayAppointments::class)
            ->assertSeeInOrder(['Agenda de hoy', 'Todos', 'Dra. Ana Torres', 'Dr. Luis Rojas'])
            ->assertSee(['Rosa Quispe', 'Pedro Paz'])
            ->call('showDoctor', $ana->id)
            ->assertSee('Rosa Quispe')
            ->assertDontSee('Pedro Paz')
            ->assertSeeHtml('aria-pressed="true"');

        // La elección se recuerda al volver a "Hoy".
        Livewire::test(TodayAppointments::class)->assertSee('Rosa Quispe')->assertDontSee('Pedro Paz');
        Livewire::test(TodayAppointments::class)->call('showDoctor', null)->assertSee(['Rosa Quispe', 'Pedro Paz']);

        // Si el doctor elegido deja de atender, vuelve a "Todos".
        Livewire::test(TodayAppointments::class)->call('showDoctor', $luis->id)->assertDontSee('Rosa Quispe');
        $luis->update(['is_active' => false]);
        Livewire::test(TodayAppointments::class)->assertSee(['Rosa Quispe', 'Pedro Paz'])->assertDontSeeHtml('>Dr. Luis Rojas</button>');
    }

    public function test_on_a_day_without_attention_home_says_so_and_opens_the_next_day_in_the_agenda(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental']);
        // De lunes a sábado.
        $ana = $this->doctorOffering($treatment);
        $ana->update(['name' => 'Dra. Ana Torres']);
        $luis = $this->doctorOffering($treatment);
        $visit = fn (Doctor $doctor, string $at, AppointmentStatus $status = AppointmentStatus::Pending) => Appointment::factory()
            ->for($doctor)->for($treatment)->for($this->patient('Paciente', 'Prueba'))->at($at)->create(['status' => $status]);
        $visit($ana, '2026-11-02 09:00');
        $visit($luis, '2026-11-02 10:00');
        // La cancelada no cuenta.
        $visit($ana, '2026-11-02 11:00', AppointmentStatus::Cancelled);
        $reception = User::factory()->reception()->create();
        $this->actingAs($reception);

        // Domingo: nadie atiende. Se dice, con las citas de mañana y el botón para verlas en la Agenda.
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00', 'America/Lima'));
        Livewire::test(TodayAppointments::class)
            ->assertSee(['Hoy el consultorio no atiende', 'Mañana hay 2 citas.'])
            ->assertDontSee(['No hay citas hoy', 'Las citas de hoy aparecen aquí'])
            ->assertActionVisible(TestAction::make('seeNextDay')->table())
            ->assertActionHasLabel(TestAction::make('seeNextDay')->table(), 'Ver mañana')
            ->assertActionHasUrl(TestAction::make('seeNextDay')->table(), Agenda::getUrl(['date' => '2026-11-02']))
            // Con un doctor elegido arriba, lo suyo.
            ->call('showDoctor', $ana->id)
            ->assertSee(['Dra. Ana Torres no atiende hoy', 'Mañana tiene 1 cita.']);

        // El doctor ve lo suyo.
        $this->actingAs($ana->user);
        Livewire::test(TodayAppointments::class)->assertSee(['Hoy no atiendes', 'Mañana tienes 1 cita.']);

        // Un feriado cargado en Bloqueos, de todo el consultorio: el martes 3 no se atiende y el miércoles no hay citas.
        $this->actingAs($reception);
        Livewire::test(TodayAppointments::class)->call('showDoctor', null);
        ScheduleBlock::create(['doctor_id' => null, 'type' => ScheduleBlockType::Holiday, 'starts_at' => '2026-11-03 00:00', 'ends_at' => '2026-11-03 23:59']);
        $this->travelTo(CarbonImmutable::parse('2026-11-03 08:00', 'America/Lima'));
        Livewire::test(TodayAppointments::class)
            ->assertSee(['Hoy el consultorio no atiende', 'Mañana todavía no hay citas.'])
            ->assertActionHasLabel(TestAction::make('seeNextDay')->table(), 'Ver mañana');

        // Sábado 7 y domingo 8 cerrados (el sábado, por un curso de los dos): el próximo es el lunes.
        ScheduleBlock::create(['doctor_id' => null, 'type' => ScheduleBlockType::Course, 'starts_at' => '2026-11-07 00:00', 'ends_at' => '2026-11-07 23:59']);
        $visit($luis, '2026-11-09 16:00');
        $this->travelTo(CarbonImmutable::parse('2026-11-07 09:00', 'America/Lima'));
        Livewire::test(TodayAppointments::class)
            ->assertSee(['Hoy el consultorio no atiende', 'El lunes hay 1 cita.'])
            ->assertActionHasLabel(TestAction::make('seeNextDay')->table(), 'Ver el lunes')
            ->assertActionHasUrl(TestAction::make('seeNextDay')->table(), Agenda::getUrl(['date' => '2026-11-09']));

        // Un día que se atiende, aunque esté vacío, dice lo de siempre y no ofrece otro día.
        $this->travelTo(CarbonImmutable::parse('2026-11-04 08:00', 'America/Lima'));
        Livewire::test(TodayAppointments::class)
            ->assertSee(['No hay citas hoy', 'Las citas de hoy aparecen aquí'])
            ->assertDontSee('no atiende')
            ->assertActionHidden(TestAction::make('seeNextDay')->table());
    }

    public function test_the_agenda_puts_first_what_needs_something_and_folds_what_ended(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Lima'));
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $ana = $this->doctorOffering($treatment);
        $luis = $this->doctorOffering($treatment);
        $visit = fn (string $firstName, string $at, AppointmentStatus $status, ?Doctor $doctor = null) => Appointment::factory()
            ->for($doctor ?? $ana)->for($treatment)->for($this->patient($firstName, 'Prueba'))->at($at)
            ->create(['status' => $status, 'checked_in_at' => $status === AppointmentStatus::CheckedIn ? now()->subMinutes(10) : null]);
        $visit('Alba', '2026-11-02 08:00', AppointmentStatus::Completed);
        $owes = $visit('Berta', '2026-11-02 08:30', AppointmentStatus::Completed);
        Charge::factory()->for($owes->patient)->create(['amount' => 120]);
        $visit('Carlos', '2026-11-02 09:00', AppointmentStatus::NoShow);
        // Pasó su hora y nadie marcó si vino.
        $visit('Diana', '2026-11-02 10:00', AppointmentStatus::Pending);
        $visit('Elena', '2026-11-02 11:30', AppointmentStatus::CheckedIn);
        $visit('Fabio', '2026-11-02 13:00', AppointmentStatus::Confirmed);
        $visit('Gina', '2026-11-02 09:30', AppointmentStatus::Completed, $luis);
        $this->actingAs(User::factory()->reception()->create());

        // Arriba lo que pide algo (en sala, quien terminó y debe, la que nadie cerró), después lo que viene y al pie
        // cuántas terminaron, cada parte con su rótulo porque no va por hora. Quien debe no queda en tenue y la que
        // nadie cerró va en ámbar. Nadie de "Por llegar" se atrasa: la línea de "ahora" quedaría pegada al rótulo.
        $agenda = Livewire::test(TodayAppointments::class)
            ->assertSeeInOrder(['En la clínica', 'Elena Prueba', 'Por cobrar', 'Berta Prueba', 'Debe S/', 'Cobrar', 'Sin cerrar', 'Diana Prueba', 'Por llegar', 'Fabio Prueba', 'Terminadas', '2 atendidas · 1 no asistió', 'Ver'])
            ->assertDontSee(['Alba Prueba', 'Carlos Prueba', 'Gina Prueba'])
            ->assertSeeHtml(['dash-appt-completed dash-appt-owes', 'dash-appt-pending dash-appt-unclosed'])
            ->assertDontSeeHtml('dash-appt-next');

        // "Ver" las pone al final, con su rótulo y por hora; la elección se recuerda y "Ocultar" las vuelve a plegar.
        $agenda->call('toggleFinished')->assertSeeInOrder(['Fabio Prueba', 'Terminadas', 'Alba Prueba', 'Carlos Prueba', 'Gina Prueba', 'Ocultar']);
        Livewire::test(TodayAppointments::class)->assertSee('Alba Prueba')->call('toggleFinished')->assertDontSee('Alba Prueba');

        // Si en la agenda que se ve no queda nada más, las terminadas están a la vista y no hay nada que plegar.
        Livewire::test(TodayAppointments::class)->call('showDoctor', $luis->id)->assertSeeInOrder(['Terminadas', 'Gina Prueba'])->assertDontSeeHtml('dash-agenda-finished');

        // Si alguien de "Por llegar" ya debía haber llegado, la línea de "ahora" lo separa de lo que viene. Si lo que
        // viene son dos citas a la misma hora, va antes de la que se ve primero (la que se agendó antes).
        $visit('Hugo', '2026-11-02 11:45', AppointmentStatus::Pending);
        $visit('Iris', '2026-11-02 13:00', AppointmentStatus::Pending, $luis);
        Livewire::test(TodayAppointments::class)
            ->call('showDoctor', null)
            ->assertSeeInOrder(['Sin cerrar', 'Diana Prueba', 'Por llegar', 'Hugo Prueba', 'Fabio Prueba', 'Iris Prueba'])
            ->assertSeeHtml('dash-appt-confirmed dash-appt-next')
            ->assertDontSeeHtml('dash-appt-pending dash-appt-next');

        // El doctor no cobra: quien debe es una atendida más, plegada con las otras.
        $this->actingAs($ana->user);
        Livewire::test(TodayAppointments::class)
            ->assertSeeInOrder(['Elena Prueba', 'Diana Prueba', 'Fabio Prueba', 'Terminadas', '2 atendidas · 1 no asistió'])
            ->assertDontSee(['Berta Prueba', 'Gina Prueba']);
    }

    public function test_each_visit_of_today_takes_two_lines_and_says_the_doctor_only_when_there_are_several(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $ana = $this->doctorOffering($treatment);
        $ana->update(['name' => 'Dra. Ana Torres']);
        $longName = $this->patient('Aleksandra María José', 'Wiśniewska-Kowalczyk de la Santísima Trinidad');
        $longName->account->update(['phone' => '+51987654321']);
        Appointment::factory()->for($ana)->for($treatment)->for($longName)->at('2026-11-02 10:00')->create();
        // El nombre lo escribe cualquiera desde la web: en el título que se lee al pasar el ratón no se cuela HTML.
        Appointment::factory()->for($ana)->for($treatment)->for($this->patient('Rosa', 'Paz"><b>x</b>'))->at('2026-11-02 11:00')->create();
        $this->actingAs(User::factory()->reception()->create());
        $phone = '987'.self::NBSP.'654'.self::NBSP.'321';

        // Debajo del nombre, "Tratamiento · celular", con el celular sin partir. Con un solo doctor no se dice de quién
        // es la cita. El nombre muy largo se lee entero al pasar el ratón.
        Livewire::test(TodayAppointments::class)
            ->assertSeeInOrder(['Aleksandra María José Wiśniewska-Kowalczyk de la Santísima Trinidad', "Limpieza dental · {$phone}"])
            ->assertSeeHtml('title="Aleksandra María José Wiśniewska-Kowalczyk de la Santísima Trinidad"')
            ->assertSeeHtml('title="Rosa Paz&quot;&gt;&lt;b&gt;x&lt;/b&gt;"')
            ->assertDontSeeHtml('<b>x</b>')
            ->assertDontSee('Dra. Ana Torres');

        // Con dos doctores, en "Todos" cada cita dice de quién es; con uno elegido arriba, ya no hace falta. Las
        // opciones se deslizan de lado si no entran, y la elegida queda a la vista.
        $this->doctorOffering($treatment);
        Once::flush();
        Livewire::test(TodayAppointments::class)
            ->assertSeeHtml("title=\"Limpieza dental · {$phone} · Dra. Ana Torres\"")
            ->assertSeeHtml('class="dash-segmented dash-doctor-switch"')
            ->call('showDoctor', $ana->id)
            ->assertSeeHtml("title=\"Limpieza dental · {$phone}\"")
            ->assertDontSeeHtml("{$phone} · Dra. Ana Torres");
    }

    public function test_who_did_not_come_is_rebooked_from_the_agenda_with_the_same_visit(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $doctor = $this->doctorOffering($treatment);
        $rosa = $this->patient('Rosa', 'Quispe');
        $missed = Appointment::factory()->for($doctor)->for($treatment)->for($rosa)->at('2026-11-02 08:00')->create(['status' => AppointmentStatus::NoShow]);
        $this->actingAs(User::factory()->reception()->create());

        // "Reagendar" abre "Nueva cita" con el paciente, el doctor y el tratamiento; la que faltó sigue como falta.
        Livewire::test(TodayAppointments::class)
            ->assertActionVisible(TestAction::make('rebook')->table($missed))
            ->assertActionHasUrl(TestAction::make('rebook')->table($missed), AppointmentResource::newUrl(['patient' => $rosa->id, 'doctor' => $doctor->id, 'treatment' => $treatment->id]));
        $this->assertSame(AppointmentStatus::NoShow, $missed->fresh()->status);

        // Con otra cita después de la que faltó, ya tiene su horario.
        Appointment::factory()->for($doctor)->for($treatment)->for($rosa)->at('2026-11-04 10:00')->create();
        Livewire::test(TodayAppointments::class)->assertActionHidden(TestAction::make('rebook')->table($missed));
    }

    public function test_each_visit_is_settled_on_the_card_without_opening_it(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental', 'price_from' => 120]);
        $doctor = $this->doctorOffering($treatment);
        $visit = fn (string $firstName, string $when) => Appointment::factory()->for($doctor)->for($treatment)
            ->for(Patient::factory()->create(['first_name' => $firstName, 'last_name' => 'Prueba']))->at($when);
        $rosa = $visit('Rosa', '2026-11-02 10:00')->create();
        $pedro = $visit('Pedro', '2026-11-02 16:00')->confirmed()->create();
        $ana = $visit('Ana', '2026-11-02 17:00')->create();
        $this->actingAs(User::factory()->reception()->create());

        // 08:00: se confirma desde la tarjeta.
        Livewire::test(DayMomentCard::class)
            ->callAction('confirm', arguments: ['appointment' => $rosa->id])
            ->assertNotified('Cita confirmada')
            ->assertDontSee('10:00 · Rosa Prueba');
        $this->assertSame(AppointmentStatus::Confirmed, $rosa->refresh()->status);

        // 19:30: nadie anotó si vinieron. Pedro vino (queda atendido y con su cargo); Ana no.
        $rosa->update(['status' => AppointmentStatus::Completed]);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Lima'));

        Livewire::test(DayMomentCard::class)
            ->assertSeeInOrder(['Citas sin cerrar', '16:00 · Pedro Prueba', 'Vino', 'No vino', '17:00 · Ana Prueba'])
            ->mountAction('complete', ['appointment' => $pedro->id])
            ->assertMountedActionModalSee('Limpieza dental')
            ->callMountedAction()
            ->assertNotified('Cita atendida')
            ->callAction('missed', arguments: ['appointment' => $ana->id])
            ->assertNotified('Marcada como inasistencia')
            ->assertDontSee(['Citas sin cerrar', 'Ana Prueba'])
            ->assertSeeInOrder(['Se fueron debiendo', 'Pedro Prueba', 'Cobrar', 'Falta el cierre de caja']);

        $this->assertSame(AppointmentStatus::Completed, $pedro->refresh()->status);
        $this->assertSame(AppointmentStatus::NoShow, $ana->refresh()->status);
        $this->assertSame(120.0, $pedro->patient->balance());

        // A quien se fue debiendo se le cobra ahí mismo, con el "Cobrar" del cierre del día.
        Livewire::test(DayMomentCard::class)
            ->mountAction('collect', ['patient' => $pedro->patient_id])
            ->assertActionDataSet(['amount' => 120.0])
            ->callMountedAction()
            ->assertNotified('Pago registrado')
            ->assertDispatched(Dashboard::CHANGED)
            ->assertDontSee(['Se fueron debiendo', 'Pedro Prueba']);
        $this->assertSame(0.0, $pedro->patient->balance());
    }

    public function test_who_stayed_checked_in_for_hours_is_no_longer_waiting(): void
    {
        $treatment = Treatment::factory()->create();
        $doctor = $this->doctorOffering($treatment);
        $arrived = fn (string $firstName, string $when) => Appointment::factory()->for($doctor)->for($treatment)
            ->for(Patient::factory()->create(['first_name' => $firstName, 'last_name' => 'Prueba']))->at($when)
            ->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => $when]);
        $arrived('Rosa', '2026-11-02 09:00');
        $arrived('Pedro', '2026-11-02 18:00');
        $this->travelTo(CarbonImmutable::parse('2026-11-02 18:20', 'America/Lima'));
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(WaitingRoom::class)->assertSee('Pedro Prueba')->assertDontSee('Rosa Prueba');
        // En la agenda tampoco sale "Esperando 9 h" en rojo: Rosa queda "En sala", sin contar, hasta que alguien la cierre.
        Livewire::test(TodayAppointments::class)
            ->assertSee(['Rosa Prueba', 'Pedro Prueba', 'Esperando 20'.self::NBSP.'min'])
            ->assertDontSee('Esperando 9');

        // Para su doctor tampoco es "tu próximo paciente": es una cita que quedó sin cerrar.
        $this->assertSame('Pedro', (new DoctorDay($doctor->id))->next()->patient->first_name);
        $this->assertSame(1, (new DoctorDay($doctor->id))->waiting());
    }

    public function test_today_finds_the_appointments_of_the_day_by_a_range_of_hours(): void
    {
        // Con date(starts_at) la base revisa cita por cita y no usa el índice de starts_at: las tarjetas, la agenda,
        // la sala y los pendientes de Hoy buscan las del día de 00:00 a 00:00 (Appointment::startsOn).
        $treatment = Treatment::factory()->create();
        $doctor = $this->doctorOffering($treatment);
        Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Rosa', 'Quispe'))->at('2026-11-02 07:30')
            ->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => '2026-11-02 07:25']);
        Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Pedro', 'Paz'))->at('2026-11-02 10:00')->confirmed()->create();
        $this->actingAs(User::factory()->admin()->create());
        DB::enableQueryLog();

        Livewire::test(TodayStats::class)->assertSee('Citas de hoy');
        Livewire::test(TodayAppointments::class)->assertSee(['Rosa Quispe', 'Pedro Paz']);
        Livewire::test(WaitingRoom::class)->assertSee('Rosa Quispe');
        Livewire::test(FollowUpsToday::class)->assertOk();

        $byDate = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $sql) => preg_match('/(strftime\(\'%Y-%m-%d\', |date\()["`]?(appointments["`]?\.["`]?)?starts_at/i', $sql) === 1);
        $this->assertSame([], $byDate->values()->all());
    }

    public function test_who_reception_passed_to_the_chair_stays_as_the_doctors_next_patient(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Curación con resina']);
        $doctor = $this->doctorOffering($treatment);
        $reception = User::factory()->reception()->create();
        $rosa = Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Rosa', 'Quispe'))->at('2026-11-02 07:30')
            ->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => '2026-11-02 07:25']);
        Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Pedro', 'Paz'))->at('2026-11-02 08:30')
            ->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => '2026-11-02 07:50']);
        Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Ana', 'Ríos'))->at('2026-11-02 09:00')->confirmed()->create();

        // Recepción toca "Pasó a consulta": Rosa ya está en el sillón, pero el doctor todavía no abrió su atención.
        app(AppointmentService::class)->markStarted($rosa, $reception);
        $this->actingAs($doctor->user);

        // Sigue siendo su paciente: arriba, con "Atender", y después quien espera en sala.
        Livewire::test(NextPatient::class)
            ->assertSeeInOrder(['07:30', 'Rosa Quispe', 'Pasó a consulta a las 08:00', 'Atender'])
            ->assertSeeInOrder(['En sala', '1 paciente más espera', 'Después', '08:30', 'Pedro Paz · Curación con resina · ya está en sala'])
            ->callAction('start', arguments: ['appointment' => $rosa->id])
            ->assertRedirect(ConsultationResource::getUrl('edit', ['record' => $rosa->fresh()->consultation]));

        // Con la atención abierta, Rosa pasa a "Atenciones sin terminar" y el próximo es Pedro.
        $this->assertSame('Pedro', (new DoctorDay($doctor->id))->next()->patient->first_name);
    }

    public function test_the_closing_card_shows_up_when_the_day_ends_without_reloading(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $doctor = $this->doctorOffering($treatment);
        Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Pedro', 'Huamán'))->at('2026-11-02 16:00')->confirmed()->create();
        $this->actingAs(User::factory()->reception()->create());

        // "Hoy" se abrió a las 11:00, cuando no había nada que hacer: la tarjeta ya está, sin ocupar lugar, y se revisa sola.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 11:00', 'America/Lima'));
        $this->assertContains(DayMomentCard::class, $this->visibleWidgets());
        $card = Livewire::test(DayMomentCard::class)
            ->assertSeeHtml('wire:poll.60s')
            ->assertSeeHtml('day-moment-idle')
            ->assertDontSee('Para cerrar el día');

        // 19:30, sin recargar: al revisarse aparece lo que queda para cerrar el día.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Lima'));
        // (La respuesta de una actualización viene en JSON: se buscan textos sin tildes.)
        $card->dispatch(Dashboard::CHANGED)
            ->assertViewHas('moment', DayMoment::CLOSING)
            ->assertDontSeeHtml('day-moment-idle')
            ->assertSeeInOrder(['Citas sin cerrar', 'Pedro', 'Falta el cierre de caja']);
    }

    public function test_after_the_cash_is_closed_reception_is_told_debts_are_collected_tomorrow(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $doctor = $this->doctorOffering($treatment);
        $rosa = $this->patient('Rosa', 'Quispe');
        Appointment::factory()->for($doctor)->for($treatment)->for($rosa)->at('2026-11-02 10:00')->create(['status' => AppointmentStatus::Completed]);
        Charge::factory()->for($rosa)->create(['amount' => 120, 'description' => 'Limpieza dental', 'charged_at' => '2026-11-02 10:40']);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Lima'));
        DailyClosing::factory()->create(['date' => '2026-11-02']);

        // Recepción ya no puede cobrar con fecha de hoy: no se le ofrece "Cobrar", se le dice qué pasa.
        $this->actingAs(User::factory()->reception()->create());
        Livewire::test(DayMomentCard::class)
            ->assertSeeInOrder(['Se fueron debiendo', 'La caja de hoy ya se cerró: lo que les cobres desde ahora entra en la caja de mañana.', 'Rosa Quispe', 'S/ 120.00', 'Caja cerrada a las'])
            ->assertActionHidden('collect');

        // La administradora sí puede: su "Cobrar" le avisa que cambia el cierre.
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(DayMomentCard::class)
            ->assertActionVisible('collect')
            ->assertDontSee('La caja de hoy ya se cerró');
    }

    public function test_home_shows_what_to_confirm_before_opening_and_what_is_left_when_closing(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $doctor = $this->doctorOffering($treatment);
        $rosa = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Quispe']);
        $pedro = Patient::factory()->create(['first_name' => 'Pedro', 'last_name' => 'Huamán']);
        $rosasVisit = Appointment::factory()->for($doctor)->for($treatment)->for($rosa)->at('2026-11-02 10:00')->create();
        Appointment::factory()->for($doctor)->for($treatment)->for($pedro)->at('2026-11-02 16:00')->confirmed()->create();
        Appointment::factory()->for($doctor)->for($treatment)->for(Patient::factory()->create(['first_name' => 'Ana', 'last_name' => 'Rojas']))->at('2026-11-02 17:00')->confirmed()->create();
        $this->actingAs(User::factory()->reception()->create());

        // 08:00, antes de abrir: lo que falta confirmar.
        $this->assertContains(DayMomentCard::class, $this->visibleWidgets());
        Livewire::test(DayMomentCard::class)
            ->assertSeeInOrder(['Antes de abrir', '1 cita de hoy sin confirmar', '10:00 · Rosa Quispe', 'Limpieza dental', 'Confirmar'])
            // La cita completa y no solo "Pedro": el doctor de la prueba lleva un nombre al azar y puede llamarse igual.
            ->assertDontSee(['16:00 · Pedro Huamán', 'Para cerrar el día']);

        // Durante el día mandan la agenda y la sala: la tarjeta sigue ahí, sin nada ni lugar ocupado.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 11:00', 'America/Lima'));
        Livewire::test(DayMomentCard::class)
            ->assertSeeHtml('day-moment-idle')
            ->assertDontSee(['Antes de abrir', 'Para cerrar el día']);

        // 19:30: Rosa se fue debiendo y la cita de Pedro quedó sin cerrar.
        $rosasVisit->update(['status' => AppointmentStatus::Completed]);
        Charge::factory()->for($rosa)->create(['amount' => 120, 'description' => 'Limpieza dental', 'charged_at' => '2026-11-02 10:40']);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 19:30', 'America/Lima'));
        Livewire::test(DayMomentCard::class)
            ->assertSeeInOrder(['Para cerrar el día', 'Se fueron debiendo', 'S/ 120.00'])
            ->assertDontSee('Cobrado hoy')
            ->assertSeeInOrder(['Se fueron debiendo', 'Rosa Quispe', 'Citas sin cerrar', '16:00 · Pedro Huamán', '17:00 · Ana Rojas', 'Falta el cierre de caja', 'Cerrar la caja'])
            ->assertDontSee('Antes de abrir');

        // El doctor no la ve: lo suyo es su próximo paciente.
        $this->actingAs($doctor->user);
        $this->assertNotContains(DayMomentCard::class, $this->visibleWidgets());
    }

    public function test_what_is_done_on_one_card_shows_on_the_others_at_once(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental', 'price_from' => 120]);
        $doctor = $this->doctorOffering($treatment);
        $rosa = Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Rosa', 'Quispe'))->at('2026-11-02 10:00')->create();
        $this->actingAs(User::factory()->reception()->create());

        // Las tarjetas ya están abiertas cuando recepción toca un botón en otra.
        $stats = Livewire::test(TodayStats::class)->assertSee('Hoy y mañana · reenvía el aviso o llama');
        $agenda = Livewire::test(TodayAppointments::class)->assertSee('Pendiente');
        $room = Livewire::test(WaitingRoom::class)->assertSee('Nadie esperando');

        // "Confirmar" en "Antes de abrir" avisa a las demás, que se vuelven a pintar sin esperar su recarga de 30 s.
        Livewire::test(DayMomentCard::class)
            ->callAction('confirm', arguments: ['appointment' => $rosa->id])
            ->assertDispatched(Dashboard::CHANGED);
        $stats->dispatch(Dashboard::CHANGED)->assertSee('Hoy y mañana, todo confirmado');
        $agenda->dispatch(Dashboard::CHANGED)->assertSee('Confirmada')->assertDontSee('Pendiente');

        // "Llegó" en la agenda: la sala de espera la muestra al momento.
        $agenda->callTableAction('arrived', $rosa)->assertDispatched(Dashboard::CHANGED);
        $room->dispatch(Dashboard::CHANGED)->assertSee('Rosa Quispe')->assertDontSee('Nadie esperando');

        // Los botones de arriba también avisan: un cobro cambia "Cobrado hoy".
        Charge::factory()->for($rosa->patient)->create(['amount' => 120]);
        $stats->dispatch(Dashboard::CHANGED)->assertSee(['S/ 0.00', 'Falta cobrar S/ 120.00 a 1 paciente']);
        Livewire::test(Dashboard::class)
            ->mountAction('registerPayment')
            ->setActionData(['patient_id' => $rosa->patient_id, 'amount' => 120])
            ->callMountedAction()
            ->assertNotified('Pago registrado')
            ->assertDispatched(Dashboard::CHANGED);
        $stats->dispatch(Dashboard::CHANGED)->assertSee('S/ 120.00')->assertDontSee('Falta cobrar');

        // Las del costado y la del doctor escuchan el mismo aviso.
        Livewire::test(FollowUpsToday::class)->dispatch(Dashboard::CHANGED)->assertOk();
        Livewire::test(WeekGaps::class)->dispatch(Dashboard::CHANGED)->assertOk();
        $this->actingAs($doctor->user);
        Livewire::test(NextPatient::class)->dispatch(Dashboard::CHANGED)->assertSee('Rosa Quispe');
    }

    public function test_the_numbers_of_the_day_count_what_the_lists_they_lead_to_show(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $doctor = $this->doctorOffering($treatment);
        $visit = fn (string $firstName, string $when, array $attributes = []) => Appointment::factory()->for($doctor)->for($treatment)
            ->for($this->patient($firstName, 'Prueba'))->at($when)->create($attributes);
        $today = $visit('Rosa', '2026-11-02 10:00');
        $tomorrow = $visit('Pedro', '2026-11-03 10:00');
        $later = $visit('Ana', '2026-11-05 10:00');
        $visit('Luis', '2026-11-02 11:00', ['status' => AppointmentStatus::NoShow]);
        $visit('Carmen', '2026-11-02 12:00', ['status' => AppointmentStatus::Cancelled]);
        $this->actingAs(User::factory()->reception()->create());

        // "Citas de hoy" cuenta las filas de la agenda de abajo: quien faltó sigue ahí; la cancelada, no.
        Livewire::test(TodayStats::class)
            ->assertSeeHtmlInOrder(['Citas de hoy', '<strong class="dash-kpi-value">2</strong>', '0 atendidas · 1 por llegar · 1 no asistió'])
            // "Por confirmar" son las de hoy y mañana, y lleva a la lista con esas mismas.
            ->assertSeeHtmlInOrder(['Por confirmar', '<strong class="dash-kpi-value">2</strong>', 'Hoy y mañana'])
            ->assertSeeHtml('href="'.e(AppointmentResource::toConfirmUrl(today(), today()->addDay())).'"');
        // Quien faltó sigue en la agenda, plegado al pie con las terminadas; la cancelada, no.
        Livewire::test(TodayAppointments::class)
            ->assertSee(['Rosa Prueba', 'Terminadas', '1 no asistió'])
            ->assertDontSee(['Luis Prueba', 'Carmen Prueba'])
            ->call('toggleFinished')
            ->assertSee(['Rosa Prueba', 'Luis Prueba'])
            ->assertDontSee('Carmen Prueba');

        Livewire::test(ListAppointments::class, ['activeTab' => 'pending'])
            ->assertCanSeeTableRecords([$today, $tomorrow, $later])
            ->filterTable('dates', ['from' => '2026-11-02', 'until' => '2026-11-03'])
            ->assertCanSeeTableRecords([$today, $tomorrow])
            ->assertCanNotSeeTableRecords([$later]);
    }

    public function test_only_the_number_that_needs_someone_is_highlighted(): void
    {
        $treatment = Treatment::factory()->create();
        $doctor = $this->doctorOffering($treatment);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(TodayStats::class)
            ->assertSee('Hoy y mañana, todo confirmado')
            ->assertDontSeeHtml('dash-kpi is-warning');

        Appointment::factory()->for($doctor)->for($treatment)->at('2026-11-03 10:00')->create(['status' => AppointmentStatus::Pending]);

        Livewire::test(TodayStats::class)
            ->assertSee('Hoy y mañana · reenvía el aviso o llama')
            ->assertSeeHtml('dash-kpi is-warning');
    }

    public function test_the_waiting_room_lists_who_has_waited_longest_first(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $doctor = $this->doctorOffering($treatment);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(WaitingRoom::class)->assertSee('Nadie esperando');

        Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Luis', 'Mamani'))->at('2026-11-02 08:30')
            ->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => now()->subMinutes(5)]);
        Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Rosa', 'Quispe'))->at('2026-11-02 09:00')
            ->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => now()->subMinutes(25)]);
        Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Ana', 'Flores'))->at('2026-11-02 09:30')->confirmed()->create();

        Livewire::test(WaitingRoom::class)
            // Arriba, su doctor está libre y lo esperan dos: en ámbar, porque hay que pasar a alguien.
            ->assertSeeInOrder([$doctor->name, 'Libre', '2 esperando'])
            ->assertSeeHtml('class="is-call"')
            // Rosa llegó antes, aunque su cita sea más tarde. Lleva 25 minutos, pero llegó temprano: todavía no es
            // su hora, así que no está demorada y no va en rojo.
            ->assertSeeInOrder(['Rosa Quispe', 'Limpieza dental · cita de las 09:00 · llegó temprano', '25'.self::NBSP.'min', 'Luis Mamani', '5'.self::NBSP.'min'])
            ->assertDontSeeHtml('dash-waiting-time is-long')
            // Con un solo doctor, cada paciente no repite de quién es.
            ->assertDontSee(['Ana Flores', 'cita de las 09:00 · '.$doctor->name]);

        // 09:20: ya pasaron 20 minutos de su hora. La espera va en rojo y dice todo lo que lleva en sala.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:20', 'America/Lima'));
        Livewire::test(WaitingRoom::class)
            ->assertSeeHtmlInOrder(['Rosa Quispe', '<span class="dash-waiting-time is-long">1'.self::NBSP.'h 45'.self::NBSP.'min</span>']);
    }

    public function test_the_waiting_room_says_whom_each_doctor_is_seeing_and_who_is_free(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $torres = $this->doctorOffering($treatment);
        $torres->update(['name' => 'Dra. Ana Torres', 'sort_order' => 1]);
        $rojas = $this->doctorOffering($treatment);
        $rojas->update(['name' => 'Dr. Luis Rojas', 'sort_order' => 2]);
        $diaz = $this->doctorOffering($treatment);
        $diaz->update(['name' => 'Dra. Carla Díaz', 'sort_order' => 3]);
        Appointment::factory()->for($torres)->for($treatment)->for($this->patient('Rosa', 'Quispe'))->at('2026-11-02 07:30')
            ->create(['status' => AppointmentStatus::InProgress, 'started_at' => now()->subMinutes(28)]);
        Appointment::factory()->for($torres)->for($treatment)->for($this->patient('Pedro', 'Paz'))->at('2026-11-02 08:00')
            ->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => now()->subMinutes(10)]);
        Appointment::factory()->for($rojas)->for($treatment)->for($this->patient('Eva', 'Sol'))->at('2026-11-02 10:00')->confirmed()->create();
        // Quien solo tenía una cita cancelada no atiende hoy.
        Appointment::factory()->for($diaz)->for($treatment)->for($this->patient('Rut', 'Mar'))->at('2026-11-02 11:00')
            ->create(['status' => AppointmentStatus::Cancelled]);
        $this->actingAs(User::factory()->reception()->create());

        // La que está atendiendo tiene a uno esperando, en gris: ya lo pasará; el otro está libre.
        Livewire::test(WaitingRoom::class)
            ->assertSeeInOrder(['Dra. Ana Torres', 'Atendiendo a Rosa Quispe, hace 28'.self::NBSP.'min', '1 esperando', 'Dr. Luis Rojas', 'Libre', 'Pedro Paz'])
            ->assertDontSeeHtml('class="is-call"')
            ->assertDontSee('Dra. Carla Díaz');
    }

    public function test_a_visit_that_runs_long_past_its_time_asks_whether_it_ended(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $torres = $this->doctorOffering($treatment);
        $torres->update(['name' => 'Dra. Ana Torres']);
        $rosa = Appointment::factory()->for($torres)->for($treatment)->for($this->patient('Rosa', 'Quispe'))->at('2026-11-02 07:00')
            ->create(['status' => AppointmentStatus::InProgress, 'started_at' => '2026-11-02 07:05']);
        Appointment::factory()->for($torres)->for($treatment)->for($this->patient('Pedro', 'Paz'))->at('2026-11-02 08:00')
            ->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => '2026-11-02 07:55']);
        $this->actingAs(User::factory()->reception()->create());

        // 08:15: la cita de Rosa era hasta las 07:30. Quizá ya salió y nadie lo marcó: se pregunta, lleva a la cita
        // para cerrarla, y quien espera a esa doctora se pinta en ámbar.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:15', 'America/Lima'));
        Livewire::test(WaitingRoom::class)
            ->assertSee('Atendiendo a Rosa Quispe, hace 1'.self::NBSP.'h 10'.self::NBSP.'min · su cita era hasta las 07:30, ¿ya terminó?')
            ->assertSeeHtml('<a href="'.AppointmentResource::getUrl('view', ['record' => $rosa]).'" class="dash-room-overrun">')
            ->assertSeeHtml('<b class="is-call">1 esperando</b>');
    }

    public function test_the_parts_of_todays_count_add_up_to_the_total(): void
    {
        $treatment = Treatment::factory()->create();
        $doctor = $this->doctorOffering($treatment);
        $visit = fn (string $firstName, string $when) => Appointment::factory()->for($doctor)->for($treatment)
            ->for($this->patient($firstName, 'Prueba'))->at($when);
        $visit('Ana', '2026-11-02 08:00')->create(['status' => AppointmentStatus::Completed]);
        $visit('Luis', '2026-11-02 09:00')->create(['status' => AppointmentStatus::NoShow]);
        $visit('Rosa', '2026-11-02 09:30')->create();
        $visit('Pedro', '2026-11-02 10:30')->create(['status' => AppointmentStatus::InProgress, 'started_at' => '2026-11-02 10:35']);
        $visit('Eva', '2026-11-02 11:00')->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => '2026-11-02 10:50']);
        $visit('Rut', '2026-11-02 12:00')->confirmed()->create();
        $visit('Gloria', '2026-11-02 12:30')->create(['status' => AppointmentStatus::Cancelled]);
        $this->actingAs(User::factory()->reception()->create());

        // 11:05: seis citas (la cancelada no cuenta). Rosa ya pasó su hora y nadie marcó si vino: no está "por llegar".
        $this->travelTo(CarbonImmutable::parse('2026-11-02 11:05', 'America/Lima'));
        Livewire::test(TodayStats::class)
            ->assertSeeInOrder(['Citas de hoy', '6', '1 atendida · 2 en la clínica · 1 por llegar · 1 sin cerrar · 1 no asistió']);
    }

    public function test_home_says_the_month_in_one_line_and_its_detail_is_in_reports(): void
    {
        $torres = Doctor::factory()->create(['name' => 'Dra. Ana Torres']);
        $ramos = Doctor::factory()->create(['name' => 'Dr. Luis Ramos']);
        $patient = $this->patient('Rosa', 'Quispe');
        Payment::factory()->for($patient)->create(['doctor_id' => $torres->id, 'amount' => 300, 'paid_at' => '2026-11-01 10:00']);
        Payment::factory()->for($patient)->create(['doctor_id' => $ramos->id, 'amount' => 150, 'paid_at' => '2026-11-02 07:30']);
        Payment::factory()->for($patient)->create(['doctor_id' => $torres->id, 'amount' => 300, 'paid_at' => '2026-10-02 10:00']);
        Setting::set('reports.monthly_goal', 4500);

        $this->actingAs(User::factory()->admin()->create());

        // En "Hoy", una línea: lo cobrado, la meta y "Ver reportes". Los números de Reportes ya no se repiten aquí.
        Livewire::test(MonthOverview::class)
            ->assertSeeInOrder(['Este mes:', 'S/ 450.00', 'cobrados', '10 % de la meta', 'Ver reportes'])
            ->assertDontSee(['Realizado', 'Citas atendidas', 'Inasistencias', 'Por cobrar']);

        // En Reportes, con "Este mes": comparado con los mismos días de octubre y la meta al detalle. Al 2 de
        // noviembre, S/ 450 de S/ 4,500 es justo el ritmo de 2 días de 30.
        Livewire::test(SummaryStats::class, ['pageFilters' => ['period' => 'month']])
            ->assertSee(['Cobrado', 'S/ 450.00', '+50 % vs. 1–2 oct.', 'Con tan pocos días, las diferencias todavía no dicen mucho.'])
            ->assertSee(['Meta del mes', '10 %', 'De S/ 4,500 · faltan S/ 4,050 en 28 días, a buen ritmo']);
        // La meta es del mes en curso y del consultorio entero: con otro período o un doctor elegido, no va.
        Livewire::test(SummaryStats::class, ['pageFilters' => ['period' => 'last_month']])->assertDontSee('Meta del mes');
        Livewire::test(SummaryStats::class, ['pageFilters' => ['period' => 'month', 'doctor' => $ramos->id]])->assertDontSee('Meta del mes');

        // El doctor que ve sus números: lo de sus citas, sin la meta del consultorio.
        Setting::set('reports.doctors_see_own', true);
        $this->actingAs($ramos->user);

        Livewire::test(MonthOverview::class)
            ->assertSee(['Este mes, de tus citas:', 'S/ 150.00'])
            ->assertDontSee(['S/ 450.00', 'de la meta']);
    }

    public function test_reports_say_where_the_month_would_close_at_this_pace(): void
    {
        $patient = $this->patient('Rosa', 'Quispe');
        Payment::factory()->for($patient)->create(['amount' => 300, 'paid_at' => '2026-11-01 10:00']);
        Payment::factory()->for($patient)->create(['amount' => 150, 'paid_at' => '2026-11-02 07:30']);
        Payment::factory()->for($patient)->create(['amount' => 200, 'paid_at' => '2026-10-01 10:00']);
        Payment::factory()->for($patient)->create(['amount' => 1000, 'paid_at' => '2026-10-31 10:00']);
        Payment::factory()->for($patient)->create(['amount' => 999, 'paid_at' => '2026-10-31 11:00', 'voided_at' => '2026-10-31 12:00']);
        $this->actingAs(User::factory()->admin()->create());
        $thisMonth = ['pageFilters' => ['period' => 'month']];

        $this->assertSame(1200.0, (new MonthFigures)->figures()['collected_last_month']);

        // Los primeros días no se proyecta: un solo pago lo cambia todo.
        Livewire::test(SummaryStats::class, $thisMonth)->assertDontSee('A este ritmo');

        $this->travelTo(CarbonImmutable::parse('2026-11-10 18:00', 'America/Lima'));
        Payment::factory()->for($patient)->create(['amount' => 550, 'paid_at' => '2026-11-10 09:00']);

        // S/ 1,000 en 10 días de 30: a este ritmo, S/ 3,000. Solo en "Este mes": otro período ya cerró o es otro.
        Livewire::test(SummaryStats::class, $thisMonth)
            ->assertSee('A este ritmo, noviembre cerraría en S/ 3,000.00; octubre cerró en S/ 1,200.00.');
        Livewire::test(SummaryStats::class, ['pageFilters' => ['period' => 'last_month']])->assertDontSee('A este ritmo');

        Setting::set('reports.monthly_goal', 4500);

        Livewire::test(SummaryStats::class, $thisMonth)
            ->assertSee('A este ritmo, noviembre cerraría en S/ 3,000.00, por debajo de la meta de S/ 4,500.');
    }

    public function test_the_month_updates_after_collecting_and_its_link_opens_reports_this_month(): void
    {
        $ramos = Doctor::factory()->create(['name' => 'Dr. Luis Ramos']);
        $patient = $this->patient('Rosa', 'Quispe');
        Payment::factory()->for($patient)->create(['doctor_id' => $ramos->id, 'amount' => 300, 'paid_at' => '2026-11-01 10:00']);
        $this->actingAs(User::factory()->admin()->create());

        // "Ver reportes" abre Reportes en este mes y con todos, no en el último período que se miró allí.
        $month = Livewire::test(MonthOverview::class)
            ->assertSee(['S/ 300.00', Summary::getUrl(['filters' => ['period' => 'month']])]);

        // Se cobra en otra ventana: el mes lo cuenta apenas "Hoy" se entera, sin esperar 5 minutos.
        Payment::factory()->for($patient)->create(['doctor_id' => $ramos->id, 'amount' => 150, 'paid_at' => now()]);
        $month->dispatch(Dashboard::CHANGED)->assertSee('S/ 450.00');

        // Al doctor que ve sus números, con él elegido.
        Setting::set('reports.doctors_see_own', true);
        $this->actingAs($ramos->user);
        Livewire::test(MonthOverview::class)
            ->assertSee(Summary::getUrl(['filters' => ['period' => 'month', 'doctor' => $ramos->id]]));
    }

    public function test_the_admin_who_treats_sees_the_clinic_month_and_their_own_in_reports(): void
    {
        $dentist = User::factory()->admin(isDentist: true)->create();
        $own = Doctor::factory()->create(['user_id' => $dentist->id]);
        $other = Doctor::factory()->create();
        $patient = $this->patient('Rosa', 'Quispe');
        Appointment::factory()->for($own)->for($patient)->at('2026-11-01 10:00')->create(['status' => AppointmentStatus::Completed]);
        Payment::factory()->for($patient)->create(['doctor_id' => $own->id, 'amount' => 300, 'paid_at' => '2026-11-01 10:00']);
        Payment::factory()->for($patient)->create(['doctor_id' => $other->id, 'amount' => 150, 'paid_at' => '2026-11-02 07:30']);
        Setting::set('reports.monthly_goal', 4500);
        $this->actingAs($dentist);

        // En "Hoy", lo de todo el consultorio con la meta; lo de sus citas, en Reportes eligiéndose a sí mismo.
        Livewire::test(MonthOverview::class)
            ->assertSee(['Este mes:', 'S/ 450.00', '10 % de la meta'])
            ->assertDontSee('de tus citas');
        Livewire::test(SummaryStats::class, ['pageFilters' => ['period' => 'month', 'doctor' => $own->id]])
            ->assertSee(['Citas atendidas', 'S/ 300.00'])
            ->assertDontSee(['S/ 450.00', 'Meta del mes']);
    }

    public function test_reports_show_what_was_collected_each_of_the_last_30_days(): void
    {
        $patient = $this->patient('Rosa', 'Quispe');
        Payment::factory()->for($patient)->create(['amount' => 300, 'paid_at' => '2026-10-30 10:00']);
        Payment::factory()->for($patient)->create(['amount' => 120, 'paid_at' => '2026-10-30 16:00']);
        Payment::factory()->for($patient)->create(['amount' => 150, 'paid_at' => '2026-11-02 07:30']);
        // Fuera de los 30 días y anulado: no cuentan.
        Payment::factory()->for($patient)->create(['amount' => 900, 'paid_at' => '2026-10-03 10:00']);
        Payment::factory()->for($patient)->create(['amount' => 999, 'paid_at' => '2026-11-01 11:00', 'voided_at' => '2026-11-01 12:00']);
        $this->actingAs(User::factory()->admin()->create());

        // Está en Reportes, debajo de los ingresos por mes; en "Hoy" el mes va en una línea.
        $this->assertContains(MonthTrendChart::class, (new Summary)->getWidgets());
        $this->assertNotContains(MonthTrendChart::class, (new Dashboard)->getWidgets());

        $chart = Livewire::test(MonthTrendChart::class)
            ->assertSee(['Cobrado por día · últimos 30 días', 'En total, S/ 570.00.', 'El mejor día fue el viernes 30 de octubre, con S/ 420.00.'])
            ->instance();
        $data = (fn () => $this->getCachedData())->call($chart);
        $this->assertCount(30, $data['labels']);
        $this->assertSame(['Dom. 4 oct.', 'Lun. 2 nov.'], [$data['labels'][0], $data['labels'][29]]);
        $this->assertSame([420.0, 0.0, 0.0, 150.0], array_slice($data['datasets'][0]['data'], 26));

        // Con un doctor elegido en Reportes, lo de ese doctor.
        $doctor = Doctor::factory()->create();
        Payment::factory()->for($patient)->create(['doctor_id' => $doctor->id, 'amount' => 80, 'paid_at' => '2026-11-01 09:00']);
        Livewire::test(MonthTrendChart::class, ['pageFilters' => ['period' => 'month', 'doctor' => $doctor->id]])
            ->assertSee('En total, S/ 80.00.');

        // Solo para el administrador.
        Setting::set('reports.doctors_see_own', true);
        $this->actingAs($doctor->user);
        $this->assertFalse(MonthTrendChart::canView());
    }

    public function test_reception_sees_whom_to_follow_up_with_and_the_lists_count_the_same(): void
    {
        $doctor = Doctor::factory()->withSchedule()->create(['name' => 'Dra. Ana Torres']);
        $treatment = Treatment::factory()->create();

        $rosa = $this->patient('Rosa', 'Quispe');
        $waiting = TreatmentPlan::create(['patient_id' => $rosa->id, 'doctor_id' => $doctor->id, 'status' => TreatmentPlanStatus::Proposed]);
        $waiting->items()->create(['description' => 'Ortodoncia', 'unit_price' => 450]);
        $waiting->update(['proposed_at' => now()->subDays(8)]);

        // Presentado ayer: aún se le da tiempo para pensarlo.
        $luis = $this->patient('Luis', 'Mamani');
        $recent = TreatmentPlan::create(['patient_id' => $luis->id, 'doctor_id' => $doctor->id, 'status' => TreatmentPlanStatus::Proposed, 'proposed_at' => now()->subDay()]);

        $ana = $this->patient('Ana', 'Flores');
        $unscheduled = $this->acceptedPlan($ana, $doctor, 'Corona', '46');

        // Aceptó y ya tiene su próxima cita: no hay nada que perseguir.
        $pedro = $this->patient('Pedro', 'Huamán');
        $scheduled = $this->acceptedPlan($pedro, $doctor, 'Endodoncia', '36');
        Appointment::factory()->for($pedro)->for($doctor)->for($treatment)->at('2026-11-03 10:00')->create();

        $carmen = $this->patient('Carmen', 'Rojas', ['birth_date' => '1990-11-02']);
        $jose = $this->patient('José', 'Condori', ['last_visit_at' => now()->subMonths(7)]);

        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(FollowUpsToday::class)
            ->assertSee(['Presupuestos sin respuesta', 'Rosa Quispe', 'S/ 450.00 · Dra. Ana Torres · presentado hace 1 semana'])
            ->assertDontSee('Luis Mamani')
            ->assertSee(['Planes sin próxima cita', 'Ana Flores', 'Falta: Corona', 'Nueva cita'])
            ->assertDontSee(['Pedro Huamán', 'pieza 46'])
            ->assertSee(['Para su control', 'José Condori', 'Cumpleaños de hoy', 'Carmen Rojas', 'Cumple 36 años'])
            // Cada grupo lleva a su pestaña de Presupuestos y planes, donde se les escribe y se les agenda.
            ->assertSeeHtml('href="'.e(TreatmentPlanResource::getUrl('index', ['tab' => 'awaiting'])).'"')
            ->assertSeeHtml('href="'.e(TreatmentPlanResource::getUrl('index', ['tab' => 'unscheduled'])).'"');

        Livewire::test(ListTreatmentPlans::class)
            ->set('activeTab', 'awaiting')
            ->assertCanSeeTableRecords([$waiting])
            ->assertCanNotSeeTableRecords([$recent, $unscheduled, $scheduled])
            ->set('activeTab', 'unscheduled')
            ->assertCanSeeTableRecords([$unscheduled])
            ->assertCanNotSeeTableRecords([$waiting, $scheduled]);
    }

    public function test_the_follow_up_numbers_count_budgets_like_the_tabs_they_lead_to(): void
    {
        $doctor = Doctor::factory()->withSchedule()->create();

        // Rosa tiene dos presupuestos sin respuesta: se le escribe una vez, pero la pestaña muestra los dos.
        $rosa = $this->patient('Rosa', 'Quispe');
        foreach ([8, 20] as $daysAgo) {
            TreatmentPlan::create(['patient_id' => $rosa->id, 'doctor_id' => $doctor->id, 'status' => TreatmentPlanStatus::Proposed, 'proposed_at' => now()->subDays($daysAgo)])
                ->items()->create(['description' => 'Ortodoncia', 'unit_price' => 450]);
        }

        $this->actingAs(User::factory()->reception()->create());

        $this->assertSame(2, app(FollowUps::class)->plansAwaitingAnswer()['count']);
        $this->assertCount(1, app(FollowUps::class)->plansAwaitingAnswer()['plans']);
        // Lo que hay en juego: los dos presupuestos, aunque a Rosa se le escriba una vez.
        Livewire::test(FollowUpsToday::class)->assertSeeInOrder(['Presupuestos sin respuesta', 'Suman S/'.self::NBSP.'900.00 · presentados hace más de '.TreatmentPlan::ANSWER_WAIT_DAYS.' días']);

        Livewire::test(ListTreatmentPlans::class)
            ->set('activeTab', 'awaiting')
            ->assertCountTableRecords(2);
    }

    public function test_a_follow_up_message_opens_ready_to_send_and_marks_the_patient_as_contacted(): void
    {
        $carmen = $this->patient('Carmen', 'Rojas', ['birth_date' => '1990-11-02']);
        $this->actingAs(User::factory()->reception()->create());

        $this->answerWhatsApp(Livewire::test(FollowUpsToday::class)
            ->assertDontSee('Le escribiste')
            ->mountAction('whatsapp', ['template' => MessageTemplateKey::Birthday->value, 'patient' => $carmen->id])
            ->assertSchemaStateSet(['body' => '¡Feliz cumpleaños, Carmen! 🎂 Todo el equipo de Clínica Vega Implant te desea un gran día.'])
            ->callMountedAction()
            ->assertHasNoErrors());

        $message = WhatsappMessage::sole();
        $this->assertSame([MessageTemplateKey::Birthday, MessageStatus::Sent, $carmen->id], [$message->template_key, $message->status, $message->patient_id]);

        Livewire::test(FollowUpsToday::class)->assertSee('Le escribiste hoy');
    }

    public function test_regulars_who_leave_happy_are_asked_for_their_google_review_once_in_six_months(): void
    {
        Setting::set('clinic.reviews_url', 'https://g.page/r/vega/review');
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $visit = fn (Patient $patient, string $at, AppointmentStatus $status = AppointmentStatus::Completed) => Appointment::factory()->for($patient)->for($cleaning)->at($at)->create(['status' => $status]);
        $regular = function (string $firstName, string $lastName) use ($visit): Patient {
            $patient = $this->patient($firstName, $lastName);
            $visit($patient, '2026-05-04 10:00');
            $visit($patient, '2026-11-02 09:00');

            return $patient;
        };

        // Viene seguido, está al día y no falta: hoy es el día para pedírsela.
        $rosa = $regular('Rosa', 'Quispe');
        // Primera vez: todavía no.
        $visit($this->patient('Luis', 'Mamani'), '2026-11-02 09:30');
        // Se fue debiendo.
        Charge::factory()->for($regular('Ana', 'Flores'))->create(['amount' => 120]);
        // Faltó a una cita hace dos meses.
        $visit($regular('Pedro', 'Huamán'), '2026-09-07 10:00', AppointmentStatus::NoShow);
        // Ya se le pidió hace tres meses.
        $carmen = $regular('Carmen', 'Rojas');
        WhatsappMessage::create(['account_id' => $carmen->account_id, 'patient_id' => $carmen->id, 'template_key' => MessageTemplateKey::ReviewRequest, 'phone' => '+51987654321', 'status' => MessageStatus::Sent, 'sent_at' => '2026-08-03 10:00']);

        $this->actingAs(User::factory()->reception()->create());

        $followUps = Livewire::test(FollowUpsToday::class)
            ->assertSee(['Pedir su opinión en Google', 'Rosa Quispe', 'Hoy: Limpieza dental · 2 atenciones'])
            ->assertDontSee(['Luis Mamani', 'Ana Flores', 'Pedro Huamán', 'Carmen Rojas'])
            ->mountAction('whatsapp', ['template' => MessageTemplateKey::ReviewRequest->value, 'patient' => $rosa->id])
            ->assertSchemaStateSet(['body' => "Hola Rosa, gracias por confiar en Clínica Vega Implant. Si te sentiste bien atendido, ¿nos dejas tu opinión en Google? Toma un minuto y ayuda a otras personas a encontrarnos:\nhttps://g.page/r/vega/review"])
            ->callMountedAction()
            ->assertHasNoErrors();
        $this->answerWhatsApp($followUps);

        Livewire::test(FollowUpsToday::class)->assertSee(['Rosa Quispe', 'Le escribiste hoy']);

        // La semana siguiente vuelve, contenta otra vez: ya se le pidió, no se le insiste.
        $this->travelTo(now()->addWeek());
        $visit($rosa, '2026-11-09 09:00');

        Livewire::test(FollowUpsToday::class)->assertDontSee('Rosa Quispe');

        // Sin el enlace a Google configurado, no se ofrece a nadie.
        $visit($regular('Elena', 'Vargas'), '2026-11-09 10:00');
        Setting::set('clinic.reviews_url', null);

        Livewire::test(FollowUpsToday::class)->assertDontSee(['Pedir su opinión', 'Elena Vargas']);
    }

    public function test_the_plan_message_names_what_is_pending_without_the_tooth(): void
    {
        $doctor = Doctor::factory()->create();
        $ana = $this->patient('Ana', 'Flores');
        $this->acceptedPlan($ana, $doctor, 'Corona', '46');
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(FollowUpsToday::class)
            ->mountAction('whatsapp', ['template' => MessageTemplateKey::PlanNextSession->value, 'patient' => $ana->id])
            ->assertSchemaStateSet(['body' => "Hola Ana, te escribimos de Clínica Vega Implant.\nEn tu tratamiento queda pendiente: Corona.\n\n¿Te reservamos tu próxima cita? Respóndenos con el día y la hora que te acomodan."]);
    }

    public function test_a_leap_day_birthday_is_greeted_on_february_28_in_other_years(): void
    {
        $leapling = $this->patient('Ana', 'Flores', ['birth_date' => '2000-02-29']);
        $greetedOn = fn (string $date) => Patient::query()->birthdayOn(CarbonImmutable::parse($date))->whereKey($leapling)->exists();

        $this->assertTrue($greetedOn('2027-02-28'));
        $this->assertFalse($greetedOn('2028-02-28'));
        $this->assertTrue($greetedOn('2028-02-29'));
    }

    public function test_presenting_a_plan_records_when_it_was_presented(): void
    {
        $plan = TreatmentPlan::create(['patient_id' => $this->patient('Rosa', 'Quispe')->id, 'doctor_id' => Doctor::factory()->create()->id, 'status' => TreatmentPlanStatus::Draft]);
        $this->assertNull($plan->proposed_at);

        app(TreatmentPlanService::class)->markProposed($plan);

        $this->assertTrue($plan->fresh()->proposed_at->equalTo(now()));
    }

    public function test_the_week_shows_how_full_each_day_is_and_where_an_appointment_still_fits(): void
    {
        $doctor = Doctor::factory()->withSchedule()->create();
        Appointment::factory()->for($doctor)->for(Treatment::factory())->for($this->patient('Rosa', 'Quispe'))->at('2026-11-02 09:00', minutes: 30, buffer: 10)->create();
        ScheduleBlock::create(['doctor_id' => $doctor->id, 'type' => ScheduleBlockType::Course, 'starts_at' => '2026-11-03 00:00', 'ends_at' => '2026-11-03 23:59']);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(WeekGaps::class)
            // Arriba, el total de la semana (el que antes repetía "Horas libres" en los números del día).
            ->assertSee('Quedan 39'.self::NBSP.'h 20'.self::NBSP.'min libres de hoy a 7 días.')
            // En una fila, empezando hoy: cuánto queda libre en cada día, con poco espacio.
            ->assertSeeInOrder(['Hoy', '7 h 20', 'Mar', 'Lleno', 'Mié', '8 h', 'Sáb', '8 h'])
            // Y debajo, el detalle de cada día al tocarlo; abre en el primero que tiene huecos.
            ->assertSeeInOrder(['Hoy', '7'.self::NBSP.'h 20'.self::NBSP.'min libres', '09:40–13:00 · 15:00–19:00', 'Abrir la agenda de ese día', 'Mañana', 'Sin huecos', 'Miércoles 4', '8'.self::NBSP.'h libres'])
            ->assertSeeHtml('x-data="{ day: 0 }"')
            // El domingo no se atiende.
            ->assertDontSee('Domingo');
    }

    public function test_today_counts_from_now_and_each_doctor_shows_who_on_the_waitlist_fits(): void
    {
        $this->travelTo(now()->setTime(17, 0));
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $implant = Treatment::factory()->create(['name' => 'Implante']);
        $whitening = Treatment::factory()->create(['name' => 'Blanqueamiento']);
        $vega = Doctor::factory()->withSchedule()->create(['name' => 'Dr. Vega']);
        $vega->treatments()->attach([$cleaning->id, $whitening->id]);
        $ana = Doctor::factory()->withSchedule()->create(['name' => 'Dra. Ana']);
        $ana->treatments()->attach([$cleaning->id, $implant->id]);

        // A las 17:00, a Dr. Vega solo le quedan 20 minutos (no entra una cita) y a Dra. Ana una hora.
        Appointment::factory()->for($vega)->for($cleaning)->for($this->patient('Eva', 'Sol'))->at('2026-11-02 17:00', minutes: 100, buffer: 0)->create();
        Appointment::factory()->for($ana)->for($implant)->for($this->patient('Luz', 'Ramos'))->at('2026-11-02 17:00', minutes: 60, buffer: 0)->create();

        WaitlistEntry::factory()->for($this->patient('Rosa', 'Quispe'))->for($implant)->create();
        WaitlistEntry::factory()->for($this->patient('Luis', 'Mamani'))->for($cleaning)->create(['doctor_id' => $vega->id]);
        WaitlistEntry::factory()->for($this->patient('Marta', 'Cruz'))->for($cleaning)->create();
        WaitlistEntry::factory()->for($this->patient('Jorge', 'Salas'))->for($whitening)->create();

        $this->actingAs(User::factory()->reception()->create());

        $fits = fn (Doctor $doctor, string $date) => WaitlistEntryResource::getUrl('index', ['filters' => ['fits' => ['date' => $date, 'doctor_id' => $doctor->id]]]);

        Livewire::test(WeekGaps::class)
            // Lo que ya pasó de hoy no cuenta: queda 1 h de 4 h, así que la barra va al 75 %, no casi vacía.
            ->assertSeeHtml('style="width: 75%"')
            // La lista de espera va con cada doctor que tiene huecos, y solo con quien hace ese tratamiento.
            ->assertSeeInOrder(['Hoy', 'Dra. Ana:', '18:00–19:00', '2 de la lista de espera pueden venir con Dra. Ana', 'Mañana', 'Dr. Vega:', 'Dra. Ana:', '3 de la lista de espera pueden venir con Dr. Vega', '2 de la lista de espera pueden venir con Dra. Ana'])
            // Su enlace abre esa misma lista: ese día y con ese doctor.
            ->assertSee([$fits($ana, '2026-11-02'), $fits($vega, '2026-11-03')])
            ->assertDontSee($fits($vega, '2026-11-02'));

        Livewire::test(ManageWaitlistEntries::class)
            ->filterTable('fits', ['date' => '2026-11-02', 'doctor_id' => $ana->id])
            ->assertCanSeeTableRecords(WaitlistEntry::query()->whereHas('patient', fn ($patient) => $patient->whereIn('first_name', ['Rosa', 'Marta']))->get())
            ->assertCountTableRecords(2);
    }

    public function test_the_doctor_sees_the_patient_waiting_with_their_alerts_and_can_start_from_home(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Curación con resina']);
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $doctor = $this->doctorOffering($treatment);

        $maria = $this->patient('María', 'Quispe');
        Appointment::factory()->for($doctor)->for($treatment)->for($maria)->at('2026-11-02 10:00')->confirmed()->create();

        $jose = $this->patient('José', 'Condori', ['birth_date' => '1990-05-10']);
        $jose->medicalBackground()->create(['allergies' => 'Penicilina']);
        Appointment::factory()->for($doctor)->for($cleaning)->for($jose)->at('2026-10-05 10:00')->create(['status' => AppointmentStatus::Completed]);
        $waiting = Appointment::factory()->for($doctor)->for($treatment)->for($jose)->at('2026-11-02 11:00')
            ->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => now()->subMinutes(25), 'patient_note' => 'Me duele al masticar', 'internal_notes' => 'Trae su radiografía']);

        $unfinished = Consultation::factory()->for($doctor)->for($this->patient('Luz', 'Ramos'))->create(['attended_at' => now()->subDay()]);
        Consultation::factory()->for($doctor)->for($this->patient('Eva', 'Sol'))->create(['attended_at' => now()->subMinutes(40)]);

        $this->actingAs($doctor->user);

        // Quien ya está en sala va primero, aunque su cita sea más tarde; al pie, quién viene después.
        Livewire::test(NextPatient::class)
            ->assertSee(['11:00', 'José Condori', '36 años', 'Curación con resina', 'Llegó hace 25'.self::NBSP.'min'])
            ->assertSee(['Alergia: Penicilina', 'Limpieza dental · 5 oct. 2026', 'Ver historia'])
            // Lo que el paciente escribió al reservar y lo que recepción anotó al agendar.
            ->assertSeeInOrder(['Dejó una nota', 'Me duele al masticar', 'Nota interna', 'Trae su radiografía'])
            ->assertSeeInOrder(['José Condori', 'Después', '10:00', 'María Quispe · Curación con resina'])
            // Las atenciones abiertas van en su propia tarjeta, al costado.
            ->assertDontSee('Atenciones sin terminar');

        $this->assertTrue(OpenConsultations::canView());
        Livewire::test(OpenConsultations::class)
            ->assertSee(['Atenciones sin terminar', 'Luz Ramos', 'Continuar'])
            // La que quedó de ayer resalta: mientras siga abierta no se cobra ni avanza el plan. La de hoy, no.
            ->assertSeeHtmlInOrder(['Luz Ramos', '<small class="dash-late">abierta hace 1 día · sin cobrar</small>', 'Eva Sol', '<small class="">abierta hoy a las 07:20</small>']);

        // En la agenda, la etiqueta ya dice "En sala": la fila solo agrega cuánto lleva.
        Livewire::test(TodayAppointments::class)->assertSee('Esperando 25'.self::NBSP.'min')->assertDontSee('En sala hace');

        Livewire::test(NextPatient::class)
            ->callAction('start', arguments: ['appointment' => $waiting->id])
            ->assertRedirect(ConsultationResource::getUrl('edit', ['record' => $waiting->fresh()->consultation]));

        $this->assertSame(AppointmentStatus::InProgress, $waiting->fresh()->status);
        $this->assertNotNull($unfinished->fresh());
    }

    public function test_the_next_patient_says_whether_their_lab_work_arrived(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Colocación de corona']);
        $doctor = $this->doctorOffering($treatment);
        $rosa = $this->patient('Rosa', 'Quispe');
        Appointment::factory()->for($doctor)->for($treatment)->for($rosa)->at('2026-11-02 10:00')->confirmed()->create();
        $lab = fn (string $description, array $dates, ?Doctor $of = null) => LabOrder::factory()->for($rosa)
            ->create(['doctor_id' => ($of ?? $doctor)->id, 'description' => $description, 'sent_on' => '2026-10-01', ...$dates]);

        $lab('Corona de zirconio', ['due_on' => '2026-10-31', 'received_on' => '2026-10-31']);
        $lab('Férula de descarga', ['due_on' => '2026-11-01']);
        $lab('Puente de tres piezas', ['due_on' => '2026-11-10']);
        // Ya se colocó: vino después de que llegó.
        $lab('Incrustación de resina', ['due_on' => '2026-10-20', 'received_on' => '2026-10-20']);
        Appointment::factory()->for($doctor)->for($treatment)->for($rosa)->at('2026-10-22 10:00')->create(['status' => AppointmentStatus::Completed]);
        // Hace mucho, y de otro doctor: no le toca.
        $lab('Prótesis total', ['due_on' => '2026-07-01', 'received_on' => '2026-07-01']);
        $lab('Carilla de porcelana', ['due_on' => '2026-11-01'], Doctor::factory()->create());
        $this->actingAs($doctor->user);

        Livewire::test(NextPatient::class)
            // Una etiqueta corta por trabajo, con la frase entera al pasar el mouse.
            ->assertSee([
                'Corona de zirconio: llegó el 31 oct.',
                'Férula de descarga: no llegó, debía el 1 nov.',
                'Puente de tres piezas: llega el 10 nov.',
            ])
            ->assertSeeHtml([
                'class="is-success" title="Corona de zirconio: ya llegó del laboratorio el 31 de octubre."',
                'class="is-danger" title="Férula de descarga: no ha llegado del laboratorio; debía llegar hace 1 día."',
                'class="is-warning" title="Puente de tres piezas: sigue en el laboratorio, llega el 10 de noviembre."',
            ])
            ->assertDontSee(['Incrustación de resina', 'Prótesis total', 'Carilla de porcelana']);
    }

    public function test_the_next_patient_shows_what_can_hurt_in_full_and_the_rest_in_short_tags(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Curación con resina']);
        $doctor = $this->doctorOffering($treatment);
        $jose = $this->patient('José', 'Condori');
        $jose->medicalBackground()->create(['allergies' => 'Penicilina', 'medications' => 'Warfarina 5 mg', 'conditions' => ['hypertension']]);
        Appointment::factory()->for($doctor)->for($treatment)->for($jose)->at('2026-11-02 10:00')->confirmed()->create();
        $this->actingAs($doctor->user);

        // La alergia y el anticoagulante, enteros y en rojo, antes que lo demás; la presión, en una etiqueta.
        Livewire::test(NextPatient::class)
            ->assertSeeHtmlInOrder(['class="dash-alerts-severe"', 'Alergia: Penicilina', 'Toma: Warfarina 5 mg', 'class="dash-alerts"', 'Hipertensión'])
            ->assertDontSee('Sin alergias');

        // Sin antecedentes, una etiqueta corta que explica qué preguntar al pasar el mouse.
        $jose->medicalBackground()->delete();
        Livewire::test(NextPatient::class)
            ->assertSeeHtml('title="Pregunta por alergias, enfermedades y medicamentos."')
            ->assertSee('Faltan los antecedentes')
            ->assertDontSeeHtml('dash-alerts-severe');
    }

    public function test_more_unfinished_visits_than_fit_on_home_lead_to_their_tab_in_the_list(): void
    {
        $doctor = Doctor::factory()->withSchedule()->create();
        Consultation::factory()->count(4)->for($doctor)->create(['attended_at' => now()->subDay()]);

        $this->actingAs($doctor->user);

        Livewire::test(OpenConsultations::class)
            ->assertSee('Ver las 4')
            ->assertSeeHtml('href="'.e(ConsultationResource::getUrl('index', ['tab' => 'open'])).'"');
    }

    public function test_late_in_the_day_home_does_not_offer_a_patient_whose_time_already_passed(): void
    {
        $treatment = Treatment::factory()->create(['name' => 'Curación con resina']);
        $doctor = $this->doctorOffering($treatment);
        $rosa = Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Rosa', 'Huamán'))->at('2026-11-02 11:00')->confirmed()->create();
        Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Luis', 'Paz'))->at('2026-11-02 16:00')->create();
        Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Ana', 'Ríos'))->at('2026-11-02 09:00')->create(['status' => AppointmentStatus::InProgress]);
        $this->actingAs($doctor->user);

        // 16:10: la de las 11:00 nunca llegó; la de las 16:00 va tarde.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 16:10', 'America/Lima'));

        Livewire::test(NextPatient::class)
            ->assertSee(['16:00', 'Luis Paz', 'Va 10'.self::NBSP.'min tarde'])
            ->assertDontSee('Rosa Huamán');

        // En la cita que ya pasó, el paso a la vista es "No asistió" y ya no "Confirmar".
        Livewire::test(ViewAppointment::class, ['record' => $rosa->getRouteKey()])
            ->assertActionVisible('missed')
            ->assertActionHidden('confirm');

        // A las 20:35 ya no queda nadie por venir.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 20:35', 'America/Lima'));
        Livewire::test(NextPatient::class)->assertDontSee(['Luis Paz', 'Rosa Huamán']);

        // Los números del día suman el total: a esta hora nada está por llegar, todo quedó sin cerrar (también la
        // que sigue "en atención" desde la mañana).
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(TodayStats::class)
            ->assertSee('0 atendidas · 3 sin cerrar')
            ->assertDontSee('en atención')
            ->assertSee('Aún no se terminó ninguna atención');
    }

    public function test_past_appointments_left_open_show_up_and_the_no_shows_close_in_one_click(): void
    {
        $treatment = Treatment::factory()->create();
        $doctor = $this->doctorOffering($treatment);
        $forgotten = Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Ana', 'Ríos'))->at('2026-10-30 10:00')->confirmed()->create();
        $leftInRoom = Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Luis', 'Paz'))->at('2026-10-30 11:00')
            ->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => '2026-10-30 10:55']);
        $closed = Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Eva', 'Sol'))->at('2026-10-30 12:00')->create(['status' => AppointmentStatus::Completed]);
        $today = Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Rut', 'Mar'))->at('2026-11-02 10:00')->confirmed()->create();

        $this->actingAs(User::factory()->reception()->create());

        // Mientras sigan abiertas no se cobra lo atendido: su número va pintado, como lo demás que no puede esperar.
        Livewire::test(FollowUpsToday::class)
            ->assertSee(['Citas sin cerrar', 'Ya pasó su hora: marca si vino o si no asistió'])
            ->assertSeeHtmlInOrder(['Citas sin cerrar', '<span class="dash-count is-warning">2</span>']);

        Livewire::test(ListAppointments::class, ['activeTab' => 'unclosed'])
            ->assertCanSeeTableRecords([$forgotten, $leftInRoom])
            ->assertCanNotSeeTableRecords([$closed, $today])
            ->callTableBulkAction('noShowBulk', [$forgotten, $leftInRoom]);

        // Solo cambia la que nadie marcó; la que quedó en sala espera que alguien diga si se atendió.
        $this->assertSame(AppointmentStatus::NoShow, $forgotten->fresh()->status);
        $this->assertSame(AppointmentStatus::CheckedIn, $leftInRoom->fresh()->status);
    }

    public function test_what_to_resolve_today_comes_first_and_a_long_follow_up_folds(): void
    {
        $treatment = Treatment::factory()->create();
        $doctor = $this->doctorOffering($treatment);
        $doctor->update(['name' => 'Dra. Ana Torres']);
        Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Ana', 'Ríos'))->at('2026-10-30 10:00')->confirmed()->create();
        Appointment::factory()->for($doctor)->for($treatment)->for($this->patient('Rosa', 'Quispe'))->at('2026-11-02 07:30')
            ->create(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => now()->subMinutes(25)]);
        $this->patient('Carmen', 'Rojas', ['birth_date' => '1990-11-02']);
        $this->actingAs(User::factory()->reception()->create());

        // Primero lo que espera a alguien hoy, con cuántos urgentes arriba; el seguimiento, corto, va a la vista y
        // su primer grupo con nombres, abierto.
        Livewire::test(FollowUpsToday::class)
            ->assertSeeInOrder([
                '2 urgentes', 'Resolver hoy',
                'Con 20 min o más de demora', 'Rosa Quispe (25'.self::NBSP.'min, Dra. Ana Torres).',
                'Citas sin cerrar',
                'Seguimiento', 'Cumpleaños de hoy', 'Carmen Rojas',
            ])
            ->assertSeeHtml('href="#'.WaitingRoom::ANCHOR.'"')
            ->assertSeeHtml('wire:key="follow-up-birthdays" wire:ignore.self open')
            ->assertDontSeeHtml('<details class="dash-block"');

        $this->patient('José', 'Condori', ['last_visit_at' => now()->subMonths(7)]);
        $plan = TreatmentPlan::create(['patient_id' => $this->patient('Luis', 'Mamani')->id, 'doctor_id' => $doctor->id, 'status' => TreatmentPlanStatus::Proposed]);
        $plan->items()->create(['description' => 'Ortodoncia', 'unit_price' => 450]);
        $plan->update(['proposed_at' => now()->subDays(8)]);

        // Con tres grupos de seguimiento y algo por resolver, el seguimiento va plegado para no tapar lo de hoy, y
        // dice qué guarda.
        Livewire::test(FollowUpsToday::class)
            ->assertSeeHtml('<details class="dash-block"')
            ->assertSeeInOrder(['Resolver hoy', 'Citas sin cerrar', 'Seguimiento', 'presupuestos sin respuesta, para su control y cumpleaños de hoy', 'Presupuestos sin respuesta', 'Para su control', 'Cumpleaños de hoy'])
            // La demora y las citas sin cerrar dependen de la hora: la tarjeta se revisa sola cada minuto.
            ->assertSeeHtml('wire:poll.60s');
    }

    public function test_the_dentist_sees_todays_visits_whose_consent_is_still_unsigned(): void
    {
        $extraction = Treatment::factory()->create(['name' => 'Extracción simple']);
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental']);
        $template = ConsentTemplate::create(['name' => 'Consentimiento de extracción', 'treatment_id' => $extraction->id, 'body' => 'Extracción de {paciente}.']);
        $dentist = User::factory()->admin(isDentist: true)->create();
        $doctor = $this->doctorOffering($extraction);
        $doctor->update(['user_id' => $dentist->id]);

        $unsigned = Appointment::factory()->for($doctor)->for($extraction)->for($this->patient('Rosa', 'Quispe'))->at('2026-11-02 10:00')->confirmed()->create();
        $luis = $this->patient('Luis', 'Paz');
        Appointment::factory()->for($doctor)->for($extraction)->for($luis)->at('2026-11-02 11:00')->confirmed()->create();
        Consent::create([
            'patient_id' => $luis->id, 'consent_template_id' => $template->id, 'treatment_id' => $extraction->id,
            'title' => $template->name, 'body_snapshot' => 'Firmado.', 'signer_name' => $luis->full_name, 'signed_at' => now(),
        ]);
        Appointment::factory()->for($doctor)->for($cleaning)->for($this->patient('Eva', 'Sol'))->at('2026-11-02 12:00')->confirmed()->create();
        Appointment::factory()->for($doctor)->for($extraction)->for($this->patient('Rut', 'Mar'))->at('2026-11-03 10:00')->confirmed()->create();
        $this->actingAs($dentist);

        // Solo la de hoy cuyo tratamiento lo pide y sin firmar; lleva a la cita, desde donde se firma.
        Livewire::test(FollowUpsToday::class)
            ->assertSeeInOrder(['1 urgente', 'Resolver hoy', 'Consentimientos por firmar hoy', 'Rosa Quispe', 'Extracción simple'])
            ->assertSeeHtml('href="'.e(AppointmentResource::getUrl('view', ['record' => $unsigned])).'"')
            ->assertSeeHtml('wire:key="follow-up-consents" wire:ignore.self open')
            ->assertDontSee(['Luis Paz', 'Eva Sol', 'Rut Mar']);

        // Recepción no toma consentimientos: no le sale.
        $this->actingAs(User::factory()->reception()->create());
        Livewire::test(FollowUpsToday::class)->assertDontSee('Consentimientos por firmar hoy');
    }

    public function test_the_admin_sees_consultations_left_open_on_earlier_days_by_any_doctor(): void
    {
        $torres = Doctor::factory()->withSchedule()->create(['name' => 'Dra. Ana Torres']);
        $rojas = Doctor::factory()->withSchedule()->create(['name' => 'Dr. Luis Rojas']);
        Consultation::factory()->for($torres)->for($this->patient('Rosa', 'Quispe'))->create(['attended_at' => now()->subDays(3)]);
        Consultation::factory()->for($rojas)->for($this->patient('Luis', 'Paz'))->create(['attended_at' => now()->subDay()]);
        // La de hoy sigue en curso, y la cerrada ya no espera nada.
        Consultation::factory()->for($rojas)->for($this->patient('Eva', 'Sol'))->create(['attended_at' => now()->subHour()]);
        Consultation::factory()->for($torres)->for($this->patient('Rut', 'Mar'))->create(['attended_at' => now()->subDays(2), 'closed_at' => now()->subDays(2)]);
        $this->actingAs(User::factory()->admin(isDentist: true)->create());

        Livewire::test(FollowUpsToday::class)
            ->assertSeeInOrder(['1 urgente', 'Atenciones abiertas de días anteriores', 'Rosa Quispe (Dra. Ana Torres, hace 3 días), Luis Paz (Dr. Luis Rojas, hace 1 día).'])
            ->assertSeeHtml('href="'.e(ConsultationResource::getUrl('index', ['tab' => 'open'])).'"')
            ->assertDontSee(['Eva Sol', 'Rut Mar']);

        // Recepción no ve atenciones; el doctor ve las suyas en su propia tarjeta.
        $this->actingAs(User::factory()->reception()->create());
        Livewire::test(FollowUpsToday::class)->assertDontSee('Atenciones abiertas de días anteriores');
    }

    public function test_the_admin_who_treats_sees_their_open_visits_on_their_card_and_the_other_doctors_in_to_do(): void
    {
        $dentist = User::factory()->admin(isDentist: true)->create();
        $own = Doctor::factory()->withSchedule()->create(['user_id' => $dentist->id, 'name' => 'Dr. Jassmanny Vega']);
        $torres = Doctor::factory()->withSchedule()->create(['name' => 'Dra. Ana Torres']);
        Consultation::factory()->for($own)->for($this->patient('Rosa', 'Quispe'))->create(['attended_at' => now()->subDays(2)]);
        Consultation::factory()->for($torres)->for($this->patient('Luis', 'Paz'))->create(['attended_at' => now()->subDays(3)]);
        $this->actingAs($dentist);

        // Las suyas, en su tarjeta aunque hoy no tenga citas; las de los demás, en "Por hacer hoy". Ninguna dos veces.
        $this->assertContains(OpenConsultations::class, $this->visibleWidgets());
        $this->assertNotContains(NextPatient::class, $this->visibleWidgets());
        Livewire::test(OpenConsultations::class)->assertSee('Rosa Quispe')->assertDontSee('Luis Paz');
        Livewire::test(FollowUpsToday::class)
            ->assertSeeInOrder(['1 urgente', 'Atenciones abiertas de otros doctores', 'Luis Paz (Dra. Ana Torres, hace 3 días).'])
            ->assertDontSee(['Rosa Quispe', 'Atenciones abiertas de días anteriores']);
    }

    public function test_home_shows_the_first_unfinished_consultations_and_links_to_the_rest(): void
    {
        $doctor = Doctor::factory()->withSchedule()->create();
        foreach (['Uno', 'Dos', 'Tres', 'Cuatro', 'Cinco'] as $i => $name) {
            Consultation::factory()->for($doctor)->for($this->patient($name, 'Prueba'))->create(['attended_at' => now()->subDays(5 - $i)]);
        }

        $this->actingAs($doctor->user);

        Livewire::test(OpenConsultations::class)
            ->assertSee(['Uno Prueba', 'Dos Prueba', 'Tres Prueba', 'Ver las 5'])
            ->assertDontSee(['Cuatro Prueba', 'Cinco Prueba']);
    }

    public function test_the_admin_is_warned_when_backups_stop_or_whatsapp_fails(): void
    {
        $admin = User::factory()->admin(isDentist: true)->create();
        $this->actingAs($admin);

        $this->assertTrue(SystemHealth::canView());
        Livewire::test(SystemHealth::class)->assertSee(['Todavía no hay copias de seguridad', 'Ver copias']);

        Storage::disk('local')->put('respaldos/respaldo_2026-11-02_030000.sql.gz', 'copia');
        $this->assertFalse(SystemHealth::canView());

        $patient = $this->patient('Rosa', 'Quispe');
        WhatsappMessage::create([
            'account_id' => $patient->account_id,
            'patient_id' => $patient->id,
            'template_key' => MessageTemplateKey::Reminder,
            'phone' => $patient->account->phone,
            'status' => MessageStatus::Failed,
            'channel' => MessageChannel::CloudApi,
            'error' => 'Número no válido',
        ]);

        $this->assertTrue(SystemHealth::canView());
        Livewire::test(SystemHealth::class)->assertSee('1 aviso de WhatsApp no se pudo enviar esta semana');

        // Recepción no ve el estado del sistema.
        $this->actingAs(User::factory()->reception()->create());
        $this->assertFalse(SystemHealth::canView());
    }

    public function test_on_home_a_missing_backup_is_said_once_at_the_top_and_not_again_in_the_menu(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // Sin copias: arriba, en rojo; el pie del menú no lo repite.
        $this->get(Dashboard::getUrl())->assertOk()
            ->assertSee('Todavía no hay copias de seguridad')
            ->assertDontSee('backup-status is-', false);

        // En las demás pantallas, el pie del menú es el único aviso.
        $this->get(Agenda::getUrl())->assertOk()->assertSee('backup-status is-danger', false);

        // Con la copia al día, el menú lo dice también en "Hoy": no hay nada repetido.
        Storage::disk('local')->put('respaldos/respaldo_2026-11-02_030000.sql.gz', 'copia');
        $this->get(Dashboard::getUrl())->assertOk()->assertSee('backup-status is-success', false);
    }

    public function test_the_admin_sets_a_monthly_goal_in_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Settings::class)
            ->assertSee('Meta de cobro del mes')
            ->fillForm(['reports' => ['monthly_goal' => '12000']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(12000, Setting::get('reports.monthly_goal'));
    }

    /** @return list<class-string> */
    private function visibleWidgets(): array
    {
        return array_values(array_filter((new Dashboard)->getWidgets(), fn (string $widget) => $widget::canView()));
    }

    /** Paciente con fecha de nacimiento fija: uno al azar podría cumplir años hoy. */
    private function patient(string $firstName, string $lastName, array $attributes = []): Patient
    {
        return Patient::factory()->create(['first_name' => $firstName, 'last_name' => $lastName, 'birth_date' => '1980-05-15', ...$attributes]);
    }

    private function acceptedPlan(Patient $patient, Doctor $doctor, string $procedure, string $tooth): TreatmentPlan
    {
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'status' => TreatmentPlanStatus::Accepted, 'accepted_at' => now()->subWeek()]);
        $plan->items()->create(['description' => $procedure, 'tooth' => $tooth, 'unit_price' => 800, 'accepted_at' => now()->subWeek()]);

        return $plan;
    }
}
