<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\User;
use App\Services\Agenda\DoctorCalendarFeed;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** En "Mi perfil", cada doctor saca el enlace para ver su agenda en el calendario del celular, y lo cambia si hace falta. */
class DoctorCalendarLinkTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function action(string $name): TestAction
    {
        return TestAction::make($name)->schemaComponent('calendarActions', 'form');
    }

    public function test_a_doctor_creates_the_link_copies_it_and_reads_how_to_add_it(): void
    {
        $doctor = Doctor::factory()->create();
        $this->actingAs($doctor->user);

        $page = Livewire::test(EditProfile::class)
            ->assertSee(['Ver mi agenda en el calendario del celular', 'Sin datos clínicos ni celulares', 'Todavía no tienes enlace'])
            ->assertDontSee('Cambiar el enlace')
            ->callAction($this->action('createCalendarLink'))
            ->assertNotified('Enlace creado');

        $url = app(DoctorCalendarFeed::class)->url($doctor->refresh());
        $this->assertNotNull($url);

        $page->assertSee([$url, 'Copiar el enlace', 'Agregar al iPhone', 'Añadir calendario suscrito', 'calendar.google.com', 'Cambiar el enlace'])
            ->assertDontSee('Crear mi enlace')
            ->assertActionVisible($this->action('copyCalendarLink'))
            ->assertActionHasUrl($this->action('addToIphone'), str_replace('http://', 'webcal://', $url));

        $this->get($url)->assertOk();
    }

    public function test_changing_the_link_stops_the_old_one_and_stays_in_the_history(): void
    {
        $doctor = Doctor::factory()->create(['name' => 'Dra. Ana Torres']);
        $old = app(DoctorCalendarFeed::class)->renew($doctor);
        $this->actingAs($doctor->user);

        Livewire::test(EditProfile::class)
            ->assertSee($old)
            ->callAction($this->action('changeCalendarLink'))
            ->assertNotified('Enlace cambiado');

        $new = app(DoctorCalendarFeed::class)->url($doctor->refresh());
        $this->assertNotSame($old, $new);
        $this->get($old)->assertNotFound();
        $this->get($new)->assertOk();

        // Quien administra ve que lo cambió, sin la clave. El historial guarda hasta 20 letras: la base de las pruebas no
        // lo exige, MySQL sí (con un nombre más largo, cambiar el enlace daba error).
        $this->assertLessThanOrEqual(20, strlen(DoctorCalendarFeed::LINK_CHANGED));
        $change = AuditLog::query()->where('auditable_type', Doctor::class)->where('event', DoctorCalendarFeed::LINK_CHANGED)->sole();
        $this->assertSame($doctor->user_id, $change->user_id);
        $this->assertStringNotContainsString(basename($new, '.ics'), json_encode($change->toArray()));
        $this->assertSame(0, AuditLog::query()->where('auditable_type', Doctor::class)->where('event', 'updated')->count());

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageAuditLogs::class)
            ->assertTableColumnStateSet('summary', 'Se cambió el enlace de su agenda para el celular', $change);
    }

    public function test_the_admin_who_attends_has_it_too_and_reception_does_not(): void
    {
        $admin = User::factory()->admin(isDentist: true)->create();
        Doctor::factory()->create(['user_id' => $admin->id]);
        $this->actingAs($admin);

        Livewire::test(EditProfile::class)
            ->assertSee('Ver mi agenda en el calendario del celular')
            ->assertActionVisible($this->action('createCalendarLink'));

        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(EditProfile::class)
            ->assertDontSee('Ver mi agenda en el calendario del celular');

        // Quien administra y ya no atiende (su ficha de doctor está desactivada) no tiene agenda que ver.
        $retired = User::factory()->admin(isDentist: true)->create();
        Doctor::factory()->create(['user_id' => $retired->id, 'is_active' => false]);
        $this->actingAs($retired);

        Livewire::test(EditProfile::class)
            ->assertDontSee('Ver mi agenda en el calendario del celular');
    }

    public function test_with_the_panel_opened_on_this_same_computer_it_warns_that_the_phone_cannot_reach_it(): void
    {
        $doctor = Doctor::factory()->create();
        app(DoctorCalendarFeed::class)->renew($doctor);
        $this->actingAs($doctor->user);

        Livewire::test(EditProfile::class)
            ->assertSee('Este enlace solo funciona en esta computadora');

        $this->app['url']->forceRootUrl('https://citas.vengaimplantes.pe');
        $this->app['url']->forceScheme('https');

        Livewire::test(EditProfile::class)
            ->assertSee('https://citas.vengaimplantes.pe/agenda/')
            ->assertDontSee('Este enlace solo funciona en esta computadora');
    }
}
