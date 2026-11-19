<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Enums\TreatmentPlanStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Consultations\ConsultationResource;
use App\Filament\Resources\Consultations\Pages\EditConsultation;
use App\Filament\Resources\Consultations\Pages\ViewConsultation;
use App\Filament\Resources\Supplies\SupplyResource;
use App\Filament\Support\VisitCloseOut;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\ConsentTemplate;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Supply;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

class VisitCloseOutTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    public function test_finishing_reviews_what_is_often_forgotten_then_tells_reception_to_collect_and_suggests_the_next_session(): void
    {
        $this->freezeMonday();
        $endo = Treatment::factory()->create(['name' => 'Endodoncia']);
        $doctor = $this->doctorOffering($endo);
        $reception = User::factory()->reception()->create();
        $patient = Patient::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Huamán']);
        ConsentTemplate::create(['name' => 'Consentimiento de endodoncia', 'treatment_id' => $endo->id, 'body' => 'Endodoncia de {paciente}.']);
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'title' => 'Plan', 'status' => TreatmentPlanStatus::InProgress, 'accepted_at' => now()->subWeek()]);
        $item = $plan->items()->create(['treatment_id' => $endo->id, 'description' => 'Endodoncia', 'tooth' => '46', 'unit_price' => 400, 'sessions_planned' => 2, 'accepted_at' => now()->subWeek()]);
        $visit = Appointment::factory()->for($doctor)->for($endo)->for($patient)->at('2026-11-02 10:00')->create(['status' => AppointmentStatus::InProgress, 'treatment_plan_item_id' => $item->id]);
        $consultation = Consultation::factory()->for($patient)->for($doctor)->create(['appointment_id' => $visit->id, 'closed_at' => null]);
        $consultation->planItems()->attach($item);
        $this->actingAs($doctor->user);

        Livewire::test(EditConsultation::class, ['record' => $consultation->getRouteKey()])
            ->mountAction('close')
            ->assertMountedActionModalSee(['Revisa antes de terminar', 'Falta el diagnóstico.', 'Falta firmar el consentimiento de endodoncia.'])
            ->callMountedAction()
            ->assertNotified(Notification::make()->success()->title('Atención terminada')->body('Recepción ya sabe que tiene que cobrarle.'))
            ->assertRedirect(ConsultationResource::getUrl('view', ['record' => $consultation]));

        // El doctor no cobra: recepción se enteró sola al terminar, con lo que debe y el botón para cobrarle.
        $alert = $reception->notifications()->sole()->data;
        $this->assertSame('Rosa Huamán terminó su atención: cobrarle', $alert['title']);
        $this->assertSame('Debe S/ 200.00 · atendió '.$doctor->name, $alert['body']);
        $this->assertSame(AppointmentResource::getUrl('view', ['record' => $visit]), $alert['actions'][0]['url']);

        // Arriba de la atención: lo que debe, ya en manos de recepción, y la segunda sesión, a la semana.
        $nextMonday = CarbonImmutable::parse('2026-11-09');
        Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
            ->assertSeeInOrder(['Cobro', 'Debe S/ 200.00', 'Recepción ya sabe que tiene que cobrarle.'])
            ->assertDontSee('Avisar a recepción')
            ->assertSeeInOrder(['Próxima cita', 'Próxima: Endodoncia · pieza 46 · sesión 2 de 2', 'Sugerida el lunes 9 de noviembre · A la semana', 'Agendar'])
            ->assertSeeHtml(e(AppointmentResource::nextVisitUrl($patient, $doctor->id, $nextMonday)))
            ->assertActionHidden('collect');

        // Con la cita ya agendada, la tarjeta lo dice en vez de sugerir.
        Appointment::factory()->for($doctor)->for($endo)->for($patient)->at('2026-11-09 10:00')->confirmed()->create(['treatment_plan_item_id' => $item->id]);
        $this->assertSame('Ya tiene su próxima cita', VisitCloseOut::viewData($consultation->fresh(), $doctor->user)['next']['label']);

        // Al día siguiente la tarjeta ya no aparece.
        $this->travelTo(now()->addDay());
        $this->assertNull(VisitCloseOut::viewData($consultation->fresh(), $doctor->user));
    }

    public function test_a_doctor_who_has_nothing_to_collect_does_not_bother_reception(): void
    {
        $this->freezeMonday();
        $doctor = Doctor::factory()->create();
        $reception = User::factory()->reception()->create();
        $consultation = Consultation::factory()->for($doctor)->create(['closed_at' => null]);
        $this->actingAs($doctor->user);

        Livewire::test(EditConsultation::class, ['record' => $consultation->getRouteKey()])
            ->callAction('close')
            ->assertNotified(Notification::make()->success()->title('Atención terminada'));

        $this->assertSame(0, $reception->notifications()->count());
    }

    public function test_a_doctor_can_still_tell_reception_when_it_did_not_hear_on_finishing(): void
    {
        $this->freezeMonday();
        $doctor = Doctor::factory()->create();
        $reception = User::factory()->reception()->create();
        $consultation = Consultation::factory()->for($doctor)->create(['closed_at' => now()]);
        // Un cargo que se agregó después de terminar: recepción todavía no lo sabe.
        Charge::factory()->for($consultation->patient)->create(['consultation_id' => $consultation->id, 'description' => 'Radiografía', 'amount' => 30]);
        $this->actingAs($doctor->user);

        Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
            ->assertSeeInOrder(['Debe S/ 30.00', 'Se cargó S/ 30.00: Radiografía', 'Avisar a recepción'])
            ->call('mountAction', 'notifyReception')
            ->assertNotified('Listo: recepción ya sabe que tiene que cobrarle');

        Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
            ->assertSeeInOrder(['Debe S/ 30.00', 'Recepción ya sabe que tiene que cobrarle.'])
            ->assertDontSee('Avisar a recepción');

        $this->assertSame($consultation->patient->full_name.' terminó su atención: cobrarle', $reception->notifications()->sole()->data['title']);
    }

    public function test_the_admin_who_attends_collects_or_hands_the_payment_to_reception(): void
    {
        $this->freezeMonday();
        $admin = User::factory()->admin(isDentist: true)->create();
        $doctor = Doctor::factory()->create(['user_id' => $admin->id]);
        $reception = User::factory()->reception()->create();
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental', 'price_from' => 120]);
        $patient = Patient::factory()->create(['first_name' => 'Clara', 'last_name' => 'Mercado']);
        $consultation = Consultation::factory()->for($patient)->for($doctor)->create(['closed_at' => null]);
        $consultation->treatments()->attach($cleaning);
        $this->actingAs($admin);

        // Ella sí maneja la caja: al terminar no se avisa a nadie, decide ella.
        Livewire::test(EditConsultation::class, ['record' => $consultation->getRouteKey()])
            ->callAction('close')
            ->assertNotified(Notification::make()->success()->title('Atención terminada'));

        $this->assertSame(0, $reception->notifications()->count());

        Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
            ->assertSeeInOrder(['Cobro', 'Debe S/ 120.00', 'Pasar a recepción', 'Cobrar ahora'])
            ->call('mountAction', 'notifyReception')
            ->assertNotified('Listo: recepción ya sabe que tiene que cobrarle');

        // Ya se lo pasó, pero si el paciente sigue ahí, todavía puede cobrarle ella.
        Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
            ->assertSeeInOrder(['Debe S/ 120.00', 'Recepción ya sabe que tiene que cobrarle.', 'Cobrar ahora'])
            ->assertDontSee('Pasar a recepción');

        $this->assertSame('Clara Mercado terminó su atención: cobrarle', $reception->notifications()->sole()->data['title']);
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_without_anyone_at_reception_the_admin_collects_herself(): void
    {
        $this->freezeMonday();
        $admin = User::factory()->admin(isDentist: true)->create();
        $doctor = Doctor::factory()->create(['user_id' => $admin->id]);
        User::factory()->reception()->create(['is_active' => false]);
        $consultation = Consultation::factory()->for($doctor)->create(['closed_at' => now()]);
        Charge::factory()->for($consultation->patient)->create(['consultation_id' => $consultation->id, 'amount' => 50]);
        $this->actingAs($admin);

        Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
            ->assertSeeInOrder(['Debe S/ 50.00', 'Cobrar ahora'])
            ->assertDontSee('Pasar a recepción');
    }

    public function test_the_materials_to_discount_say_how_much_is_left(): void
    {
        $reception = User::factory()->reception()->create();
        $gloves = Supply::factory()->create(['name' => 'Guantes de nitrilo', 'unit' => 'par', 'min_stock' => 0]);
        app(InventoryService::class)->receive($gloves, 3, $reception);

        $this->assertContains('Guantes de nitrilo · quedan 3 pares', collect(SupplyResource::pickerOptions(withStock: true))->flatten()->all());
        $this->assertContains('Guantes de nitrilo', collect(SupplyResource::pickerOptions())->flatten()->all());
    }
}
