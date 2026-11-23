<?php

namespace Tests\Feature\Services\Backup;

use App\Filament\Pages\Backups;
use App\Filament\Widgets\SystemHealth;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Backup\ExtraCopy;
use App\Services\Backup\OffsiteCopies;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La copia extra en otra carpeta: la de cada día se guarda también fuera de la computadora (la carpeta de OneDrive o
 * Google Drive, o un USB), con solo las últimas, y si no se puede se avisa y se reintenta.
 */
class ExtraCopyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Una carpeta fuera del sistema, como la de OneDrive o un USB. */
    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-10-23 03:00', 'America/Lima'));
        $this->folder = sys_get_temp_dir().'/copias-del-consultorio-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->folder);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);

        parent::tearDown();
    }

    /** @return list<string> */
    private function copiesInTheFolder(): array
    {
        return collect(File::files($this->folder))->map(fn ($file) => $file->getFilename())->sort()->values()->all();
    }

    private function chooseFolder(int $keep = ExtraCopy::KEEP_BY_DEFAULT): void
    {
        Setting::set(ExtraCopy::FOLDER_SETTING, $this->folder);
        Setting::set(ExtraCopy::KEEP_SETTING, $keep);
    }

    public function test_each_daily_copy_is_saved_also_in_the_folder_and_only_the_last_ones_stay(): void
    {
        $this->chooseFolder(keep: 2);
        // Lo que ya había en la carpeta no es del sistema: no se toca.
        File::put($this->folder.'/notas.txt', 'mías');

        foreach (range(1, 3) as $day) {
            $this->artisan('respaldo:crear')->expectsOutputToContain('También en '.$this->folder)->assertSuccessful();
            $this->travel(1)->days();
        }

        $this->assertSame(['notas.txt', 'respaldo_2026-10-24_030000.sql.gz', 'respaldo_2026-10-25_030000.sql.gz'], $this->copiesInTheFolder());
        // Igual que la original: es la misma copia, comprimida.
        $this->assertFileEquals(Storage::disk('local')->path('respaldos/respaldo_2026-10-25_030000.sql.gz'), $this->folder.'/respaldo_2026-10-25_030000.sql.gz');

        $extra = app(ExtraCopy::class);
        $this->assertTrue($extra->hasTheLatest());
        $this->assertNull($extra->problem());
        $this->assertFalse($extra->isPending());
    }

    public function test_when_the_folder_is_missing_it_warns_and_retries_until_it_is_back(): void
    {
        $this->actingAs(User::factory()->admin(isDentist: true)->create());
        $this->chooseFolder();
        // El USB se quedó desconectado.
        File::deleteDirectory($this->folder);

        // La copia del día igual se crea: solo falta la extra.
        $this->artisan('respaldo:crear')->expectsOutputToContain('No se pudo guardar también en')->assertSuccessful();
        Storage::disk('local')->assertExists('respaldos/respaldo_2026-10-23_030000.sql.gz');

        $extra = app(ExtraCopy::class);
        $this->assertStringStartsWith('No se encontró la carpeta. Si es un USB, conéctalo.', $extra->problem());
        $this->assertTrue($extra->isPending());
        Livewire::test(SystemHealth::class)
            ->assertSee(['La copia extra no se guardó en la otra carpeta', 'Si es un USB, conéctalo.', 'Se vuelve a intentar sola cada 15 minutos.', 'Ver copias']);
        Livewire::test(Backups::class)
            ->assertSee(['La copia extra no se guardó en la otra carpeta', 'Probar de nuevo']);

        // Se reintenta sola cada 15 minutos mientras falte.
        $this->artisan('schedule:list')->assertSuccessful();
        $retry = collect(app(Schedule::class)->events())->sole(fn (Event $event) => str_contains((string) $event->command, 'respaldo:copia-extra'));
        $this->assertSame('*/15 * * * *', $retry->expression);
        $this->assertTrue($retry->filtersPass($this->app));

        // Vuelve el USB: el siguiente intento la guarda y los avisos se van.
        File::ensureDirectoryExists($this->folder);
        $this->travel(15)->minutes();
        $this->artisan('respaldo:copia-extra')->expectsOutputToContain('Copia guardada también en')->assertSuccessful();

        $this->assertSame(['respaldo_2026-10-23_030000.sql.gz'], $this->copiesInTheFolder());
        $this->assertNull($extra->problem());
        $this->assertFalse($retry->filtersPass($this->app));
        $this->assertFalse(SystemHealth::canView());
    }

    public function test_the_folder_must_exist_let_write_and_be_outside_the_system(): void
    {
        $extra = app(ExtraCopy::class);

        $this->assertStringStartsWith('Escribe la ruta completa', $extra->problemWith('Copias del consultorio'));
        $this->assertStringStartsWith('No se encontró la carpeta.', $extra->problemWith($this->folder.'/no-existe'));
        // Junto a la original o a la vista de la web, no: ni dando un rodeo con "..".
        foreach ([storage_path('app'), public_path(), base_path('app'), storage_path('app/../app')] as $inside) {
            $this->assertStringStartsWith('Esa carpeta es parte del sistema.', $extra->problemWith($inside), $inside);
        }
        $this->assertNull($extra->problemWith($this->folder));
        $this->assertNull($extra->problemWith($this->folder.'/'));
        // La prueba de escritura no deja nada.
        $this->assertSame([], $this->copiesInTheFolder());
    }

    public function test_the_dentist_admin_chooses_the_folder_and_the_page_says_where_todays_copy_is(): void
    {
        $dentist = User::factory()->admin(isDentist: true)->create(['name' => 'Ana Torres']);
        $this->actingAs($dentist);
        $this->artisan('respaldo:crear')->assertSuccessful();

        Livewire::test(Backups::class)
            ->assertSee('Todavía no hay una carpeta elegida: las copias se quedan solo aquí.')
            ->callAction('extraFolder', data: ['folder' => storage_path('app'), 'keep' => 7])
            ->assertHasActionErrors(['folder']);

        Livewire::test(Backups::class)
            ->callAction('extraFolder', data: ['folder' => $this->folder, 'keep' => 5])
            ->assertHasNoActionErrors()
            ->assertNotified('La copia más reciente ya está también en esa carpeta')
            ->assertSee(['Copia de hoy guardada también en', $this->folder, 'Ahí se guardan las últimas 5']);

        $this->assertSame(['respaldo_2026-10-23_030000.sql.gz'], $this->copiesInTheFolder());
        // Dónde van las historias clínicas queda en el historial de cambios.
        $this->assertTrue(AuditLog::query()->where('auditable_type', Setting::class)->where('user_id', $dentist->id)->where('new_values->value', $this->folder)->exists());
        $this->assertSame('Copia de hoy guardada también en otra carpeta', Backups::sidebarStatus()['detail']);

        // "Crear copia ahora" también la guarda ahí.
        $this->travel(2)->hours();
        Livewire::test(Backups::class)->callAction('create')->assertNotified('Copia creada');
        $this->assertCount(2, $this->copiesInTheFolder());

        // Quien administra sin ser odontólogo ve dónde está, pero no la cambia: ahí van las historias clínicas.
        $this->actingAs(User::factory()->admin(isDentist: false)->create());
        Livewire::test(Backups::class)
            ->assertSee('Copia de hoy guardada también en')
            ->assertActionHidden('extraFolder')
            ->assertActionHidden('stopExtraCopy');

        // Dejar de copiar no borra lo que ya está en la carpeta.
        $this->actingAs($dentist);
        Livewire::test(Backups::class)
            ->callAction('stopExtraCopy')
            ->assertSee('Todavía no hay una carpeta elegida');
        $this->assertCount(2, $this->copiesInTheFolder());
        $this->assertNull(app(ExtraCopy::class)->folder());
    }

    public function test_the_foot_of_the_menu_says_which_copy_is_in_the_folder(): void
    {
        $this->actingAs(User::factory()->admin(isDentist: true)->create());
        $this->artisan('respaldo:crear')->assertSuccessful();

        // Pasada la medianoche, antes de la copia de las 3:00, en la carpeta queda la de ayer: no es "la copia de hoy".
        $this->travelTo(CarbonImmutable::parse('2026-10-24 01:00', 'America/Lima'));
        $this->chooseFolder();
        $this->artisan('respaldo:copia-extra')->assertSuccessful();

        $this->assertSame('Respaldo de ayer ✓', Backups::sidebarStatus()['text']);
        $this->assertSame('Copia de ayer guardada también en otra carpeta', Backups::sidebarStatus()['detail']);

        // Con la de hoy creada y guardada ahí, sí.
        $this->travelTo(CarbonImmutable::parse('2026-10-24 03:00', 'America/Lima'));
        $this->artisan('respaldo:crear')->assertSuccessful();

        $this->assertSame('Copia de hoy guardada también en otra carpeta', Backups::sidebarStatus()['detail']);

        // Si la del día siguiente no llega a la carpeta (el USB no está), ya no lo dice.
        File::deleteDirectory($this->folder);
        $this->travel(1)->days();
        $this->artisan('respaldo:crear')->assertSuccessful();

        $this->assertSame('Aún no se bajó una copia fuera del servidor', Backups::sidebarStatus()['detail']);
    }

    public function test_with_the_extra_copy_working_the_weekly_download_asks_only_for_the_files(): void
    {
        $this->artisan('respaldo:crear')->assertSuccessful();
        $this->travel(8)->days();
        $this->artisan('respaldo:crear')->assertSuccessful();

        $this->assertSame('Todavía no se ha descargado ninguna copia de los datos', app(OffsiteCopies::class)->due());

        $this->chooseFolder();
        $this->artisan('respaldo:copia-extra')->assertSuccessful();

        $this->assertNull(app(OffsiteCopies::class)->due());

        // Si la carpeta deja de recibirlas por una semana, vuelve a pedirse la descarga.
        $this->travel(8)->days();
        $this->assertSame('Todavía no se ha descargado ninguna copia de los datos', app(OffsiteCopies::class)->due());
    }
}
