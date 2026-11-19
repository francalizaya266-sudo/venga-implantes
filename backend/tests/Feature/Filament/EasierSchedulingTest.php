<?php

namespace Tests\Feature\Filament;

use App\Enums\AppointmentStatus;
use App\Filament\Forms\ChoiceChips;
use App\Filament\Pages\Agenda;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\ToggleButtons;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** Elegir el horario de una cita nueva con pocos toques: los días a la vista, las horas por partes del día. */
class EasierSchedulingTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    private Treatment $treatment;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
        $this->freezeMonday();
        $this->treatment = Treatment::factory()->create(['name' => 'Limpieza dental', 'duration_minutes' => 30, 'buffer_minutes' => 10]);
        $this->doctor = $this->doctorOffering($this->treatment);
        $this->actingAs(User::factory()->reception()->create());
    }

    public function test_the_next_seven_days_are_buttons_that_say_how_many_free_times_each_has(): void
    {
        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id, 'treatment' => $this->treatment->id])
            ->assertActionDataSet(['date' => '2026-11-02'])
            ->assertSchemaComponentExists('date', checkComponentUsing: fn (ChoiceChips $date) => $date->getOptions() === [
                '2026-11-02' => 'Hoy',
                '2026-11-03' => 'Mañana',
                '2026-11-04' => 'Mié 4',
                '2026-11-05' => 'Jue 5',
                '2026-11-06' => 'Vie 6',
                '2026-11-07' => 'Sáb 7',
                '2026-11-08' => 'Dom 8',
            ]
                // De 9 a 1 y de 3 a 7, una cita de 30 minutos cada 15: de 9:00 a 12:30 y de 15:00 a 18:30.
                && $date->getDescription('2026-11-03') === '30 libres'
                // El domingo no atiende.
                && $date->getDescription('2026-11-08') === 'Sin lugar');
    }

    public function test_another_day_further_away_is_picked_on_a_calendar_and_gets_its_own_button(): void
    {
        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id, 'treatment' => $this->treatment->id])
            ->callAction(TestAction::make('otherDay')->schemaComponent('date'), ['day' => '2026-11-30'])
            ->assertActionDataSet(['date' => '2026-11-30', 'date_from_agenda' => '2026-11-30'])
            ->assertSchemaComponentExists('date', checkComponentUsing: fn (ChoiceChips $date) => array_key_last($date->getOptions()) === '2026-11-30'
                && $date->getOptions()['2026-11-30'] === 'Lun 30');
    }

    public function test_at_night_the_window_opens_on_the_next_day_with_room_unless_the_day_was_chosen_on_purpose(): void
    {
        $this->travelTo(now()->setTime(21, 30));

        // Con tratamiento y doctor ya puestos, se abre directo en mañana.
        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id, 'treatment' => $this->treatment->id])
            ->assertActionDataSet(['date' => '2026-11-03']);

        // Sin tratamiento todavía: al elegirlo.
        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id])
            ->assertActionDataSet(['date' => '2026-11-02'])
            ->setActionData(['treatment_id' => $this->treatment->id])
            ->assertActionDataSet(['date' => '2026-11-03']);

        // El domingo, tocado a propósito, se queda: dice que está lleno y ofrece ir al siguiente.
        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id])
            ->setActionData(['date' => '2026-11-08'])
            ->setActionData(['treatment_id' => $this->treatment->id])
            ->assertActionDataSet(['date' => '2026-11-08'])
            ->assertMountedActionModalSee('Ese día no hay horario libre. El siguiente con lugar es el lunes 9 de noviembre.');
    }

    public function test_the_free_times_come_in_morning_and_afternoon_and_the_ones_that_leave_no_gaps_are_recommended(): void
    {
        Appointment::factory()->for($this->doctor)->for($this->treatment)->at('2026-11-03 10:00')->confirmed()->create();

        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id, 'treatment' => $this->treatment->id, 'date' => '2026-11-03'])
            ->assertSchemaComponentExists('slot', checkComponentUsing: function (ChoiceChips $slot): bool {
                $groups = $slot->getGroupedOptions();

                return array_keys($groups) === ['Mañana', 'Tarde']
                    && array_key_first($groups['Mañana']) === '09:00'
                    && array_key_first($groups['Tarde']) === '15:00'
                    // Justo después de la cita de las 10:00 (y su limpieza), sin dejar hueco.
                    && $slot->isHighlighted('10:40')
                    && $slot->getDescription('10:40') === 'Recomendada'
                    && ! $slot->isHighlighted('15:30');
            })
            ->assertMountedActionModalSee('Las recomendadas van pegadas a otra cita: así no quedan huecos en la agenda.');
    }

    public function test_another_time_today_only_offers_the_hours_still_ahead(): void
    {
        $this->travelTo(now()->setTime(16, 22));

        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id, 'treatment' => $this->treatment->id, 'date' => '2026-11-02'])
            ->setActionData(['manual' => true])
            ->assertSchemaComponentExists('manual_hour', checkComponentUsing: fn (ToggleButtons $hour) => array_key_first($hour->getOptions()) === 16
                && array_key_last($hour->getOptions()) === 22)
            // Con solo tocar la hora actual, el primer minuto que todavía no pasó.
            ->setActionData(['manual_hour' => '16'])
            ->assertActionDataSet(['manual_time' => '16:25', 'manual_minute' => '25'])
            ->assertSchemaComponentExists('manual_minute', checkComponentUsing: fn (ToggleButtons $minute) => array_key_first($minute->getOptions()) === 25)
            // Otro día, todas las horas.
            ->setActionData(['date' => '2026-11-03'])
            ->assertSchemaComponentExists('manual_hour', checkComponentUsing: fn (ToggleButtons $hour) => array_key_first($hour->getOptions()) === '06');
    }

    public function test_late_at_night_another_time_today_says_there_are_no_hours_left(): void
    {
        $this->travelTo(now()->setTime(23, 10));

        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id, 'date' => '2026-11-02'])
            ->setActionData(['manual' => true])
            ->assertSchemaComponentExists('manual_hour', checkComponentUsing: fn (ToggleButtons $hour) => $hour->getOptions() === [])
            ->assertMountedActionModalSee('Hoy ya no quedan horas por delante: elige otro día.');
    }

    public function test_the_most_booked_treatments_are_one_tap_away(): void
    {
        [$resin, $extraction, $checkup, $whitening] = collect(['Curación con resina', 'Extracción simple', 'Control', 'Blanqueamiento'])
            ->map(fn (string $name) => Treatment::factory()->create(['name' => $name, 'duration_minutes' => 30]));
        $this->doctor->treatments()->attach([$resin->id, $extraction->id, $checkup->id, $whitening->id]);
        Appointment::factory()->count(3)->for($this->doctor)->for($whitening)->at('2026-10-20 10:00')->create();
        Appointment::factory()->count(2)->for($this->doctor)->for($resin)->at('2026-10-21 10:00')->create();

        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id])
            // Los cuatro primeros: los más agendados y, después, en su orden de siempre.
            ->assertSchemaComponentExists('treatment_shortcut', checkComponentUsing: fn (ChoiceChips $quick) => count($quick->getOptions()) === 4
                && array_slice(array_keys($quick->getOptions()), 0, 2) === [$whitening->id, $resin->id])
            ->setActionData(['treatment_shortcut' => (string) $resin->id])
            ->assertActionDataSet(['treatment_id' => $resin->id])
            // Elegido en la lista, el botón lo acompaña.
            ->setActionData(['treatment_id' => $whitening->id])
            ->assertActionDataSet(['treatment_shortcut' => (string) $whitening->id]);
    }

    public function test_each_shortcut_stays_tied_to_its_treatment_when_the_list_changes(): void
    {
        $whitening = Treatment::factory()->create(['name' => 'Blanqueamiento', 'duration_minutes' => 30]);
        $this->doctor->treatments()->attach($whitening);
        $chipsId = null;

        // Elegir otro doctor reordena "Los más pedidos": cada botón lleva la clave de su tratamiento para que el
        // navegador no deje marcado el que ahora ocupa su lugar.
        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id])
            ->assertSchemaComponentExists('treatment_shortcut', checkComponentUsing: function (ChoiceChips $quick) use (&$chipsId): bool {
                $chipsId = $quick->getId();

                return true;
            })
            ->assertMountedActionModalSeeHtml('wire:key="'.$chipsId.'-'.$whitening->id.'"');
    }

    public function test_choosing_the_patient_brings_the_doctor_who_saw_them_last(): void
    {
        $other = $this->doctorOffering($this->treatment);
        $patient = Patient::factory()->create();
        Appointment::factory()->for($patient)->for($other)->for($this->treatment)->at('2026-10-05 10:00')->create(['status' => AppointmentStatus::Completed]);

        Livewire::test(Agenda::class)
            ->mountAction('createAppointment')
            ->assertActionDataSet(['doctor_id' => null])
            ->setActionData(['patient_id' => $patient->id])
            ->assertActionDataSet(['doctor_id' => $other->id]);

        // Desde su ficha ("Agendar") ya llega puesto.
        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['patient' => $patient->id])
            ->assertActionDataSet(['doctor_id' => $other->id]);

        // Uno elegido a mano no se cambia.
        Livewire::test(Agenda::class)
            ->mountAction('createAppointment', ['doctor' => $this->doctor->id])
            ->setActionData(['patient_id' => $patient->id])
            ->assertActionDataSet(['doctor_id' => $this->doctor->id]);
    }
}
