<?php

namespace Tests\Feature\Filament;

use App\Enums\DocumentType;
use App\Enums\PatientRelationship;
use App\Enums\Sex;
use App\Filament\Imports\PatientImporter;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Support\Spreadsheet;
use Carbon\CarbonImmutable;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\TestCase;

/** Pacientes › Importar pacientes: la lista en Excel o CSV entra revisada como el alta del panel, sin repetir a nadie. */
class PatientImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-22 20:00', 'America/Lima'));
    }

    private function import(UploadedFile $file, array $options = []): Testable
    {
        return Livewire::test(ListPatients::class)
            ->callAction('importPatients', data: ['file' => $file, ...$options])
            ->assertHasNoFormErrors();
    }

    private function lastImport(): Import
    {
        return Import::query()->latest('id')->firstOrFail();
    }

    /** @return list<list<mixed>> Las filas de la primera hoja de un Excel. */
    private function excelRows(string $contents): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $contents);
        $reader = new XlsxReader;
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            break;
        }

        $reader->close();
        unlink($path);

        return $rows;
    }

    /** @return list<string> Qué hay que corregir en cada fila que no entró. */
    private function failures(Import $import): array
    {
        return $import->failedRows()->orderBy('id')->pluck('validation_error')->all();
    }

    public function test_reception_imports_the_list_saved_from_excel_in_spanish_and_reads_the_summary(): void
    {
        Patient::factory()->create(['first_name' => 'Carla', 'last_name' => 'Ríos', 'document_number' => '41234567']);
        $this->actingAs(User::factory()->reception()->create());

        // Como lo guarda Excel en español: separado por punto y coma, con tildes de Windows y celdas con espacios.
        $csv = mb_convert_encoding(implode("\r\n", [
            'Nombres;Apellidos;N° DNI;Celular;Fecha de nacimiento;Correo;Sexo;Observaciones',
            'ROSA MARÍA;HUAMÁN DE LA CRUZ;45678912;987 654 321;12/04/1985; Rosa.Huaman@Gmail.com ;Femenino;Prefiere la tarde',
            'Mateo;Quispe Huamán;7123456;987654321;03/09/15;;M;Hijo de Rosa',
            ';;;;;;;',
            'Luis;Torres;1234;912345678;;;;',
            'Ana;Pérez;;81234567;31/02/1990;ana@;X;',
            'Carla;Ríos Vega;41234567;;;;;',
        ]), 'Windows-1252', 'UTF-8');

        $this->import(UploadedFile::fake()->createWithContent('pacientes.csv', $csv))
            ->assertNotified('Importación de pacientes terminada');

        $rosa = Patient::query()->where('document_number', '45678912')->sole();
        $this->assertSame(['Rosa María', 'Huamán de la Cruz', DocumentType::Dni, '1985-04-12', 'rosa.huaman@gmail.com', Sex::Female, 'Prefiere la tarde'], [
            $rosa->first_name, $rosa->last_name, $rosa->document_type, $rosa->birth_date->toDateString(), $rosa->email, $rosa->sex, $rosa->admin_notes,
        ]);
        $this->assertSame('+51987654321', $rosa->account->phone);
        $this->assertSame(PatientRelationship::Self, $rosa->relationship);

        // Excel le quitó el cero al DNI de Mateo; con el celular de su mamá queda en su familia.
        $mateo = Patient::query()->where('first_name', 'Mateo')->sole();
        $this->assertSame(['07123456', '2015-09-03', $rosa->account_id, PatientRelationship::Other], [
            $mateo->document_number, $mateo->birth_date->toDateString(), $mateo->account_id, $mateo->relationship,
        ]);

        $import = $this->lastImport();
        $this->assertSame([5, 2], [$import->total_rows, $import->successful_rows]);
        $this->assertSame([
            'El DNI tiene 8 dígitos.',
            'El celular tiene 9 dígitos y escribiste 8. Escribe la fecha de nacimiento como día/mes/año, por ejemplo 12/04/1985. Revisa el correo: le falta algo. En sexo escribe F o M.',
            'Ya está registrado (mismo DNI): Carla Ríos. No se cambió nada.',
        ], $this->failures($import));
        $this->assertSame(1, Patient::query()->where('first_name', 'Carla')->count());
        $this->assertSame(
            'Se importaron 2 pacientes. 3 filas no se importaron: 1 ya estaba registrado y 2 tienen datos por corregir. Descarga el archivo para ver cuáles y por qué.',
            PatientImporter::getCompletedNotificationBody($import),
        );

        // Las filas que no entraron se bajan en Excel, con lo que hay que corregir en cada una.
        $download = $this->get(URL::signedRoute('filament.imports.failed-rows.download', ['authGuard' => 'web', 'import' => $import], absolute: false))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $rows = $this->excelRows($download->getContent());
        $this->assertSame(['Nombres', 'Apellidos', 'N° DNI', 'Celular', 'Fecha de nacimiento', 'Correo', 'Sexo', 'Observaciones', 'Qué corregir'], $rows[0]);
        $this->assertSame(['Luis', 'Torres', '1234', '912345678', '', '', '', '', 'El DNI tiene 8 dígitos.'], array_pad($rows[1], 9, ''));

        // Cada alta queda en el historial a nombre de quien importó.
        $this->assertSame(2, AuditLog::query()->where('auditable_type', Patient::class)->where('event', 'created')->where('user_id', auth()->id())->count());
    }

    public function test_an_excel_file_is_read_too_even_with_a_title_above_the_headers(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $book = (new Spreadsheet)->sheet(
            'Lista',
            ['Lista de pacientes del consultorio', '', '', '', ''],
            [Spreadsheet::TEXT, Spreadsheet::COUNT, Spreadsheet::COUNT, Spreadsheet::DATE, Spreadsheet::TEXT],
            [
                ['', '', '', null, ''],
                ['Nombre', 'DNI', 'Teléfono', 'Fecha de nacimiento', 'Apellido paterno'],
                ['Jorge', 7654321, 999888777, CarbonImmutable::parse('1990-01-31'), 'Salas'],
            ],
        );

        $this->import(UploadedFile::fake()->createWithContent('pacientes.xlsx', $book->toXlsx()));

        $jorge = Patient::query()->where('first_name', 'Jorge')->sole();
        $this->assertSame(['Salas', '07654321', '1990-01-31', '+51999888777'], [
            $jorge->last_name, $jorge->document_number, $jorge->birth_date->toDateString(), $jorge->account->phone,
        ]);
        $this->assertSame([1, 1], [$this->lastImport()->total_rows, $this->lastImport()->successful_rows]);
    }

    public function test_the_template_downloads_in_excel_and_imports_as_it_comes(): void
    {
        $this->actingAs(User::factory()->reception()->create());

        $page = Livewire::test(ListPatients::class)
            ->callAction([TestAction::make('importPatients'), TestAction::make('downloadExample')])
            ->assertFileDownloaded('plantilla-pacientes.xlsx');

        $template = base64_decode($page->effects['download']['content']);

        $this->import(UploadedFile::fake()->createWithContent('plantilla-pacientes.xlsx', $template));

        $rosa = Patient::query()->where('first_name', 'Rosa María')->sole();
        $mateo = Patient::query()->where('first_name', 'Mateo')->sole();
        $this->assertSame('45678912', $rosa->document_number);
        $this->assertSame([$rosa->account_id, '71234567'], [$mateo->account_id, $mateo->document_number]);
    }

    public function test_someone_already_registered_can_get_only_the_missing_data_completed(): void
    {
        $this->actingAs(User::factory()->reception()->create());
        $account = Account::factory()->create(['phone' => '+51987654321']);
        $rosa = Patient::factory()->for($account)->create([
            'first_name' => 'Rosa María', 'last_name' => 'Huamán', 'document_number' => null, 'email' => null, 'birth_date' => '1985-04-12', 'admin_notes' => 'Alérgica al látex',
        ]);
        $son = Patient::factory()->for($account)->create(['first_name' => 'Mateo', 'last_name' => 'Huamán', 'relationship' => PatientRelationship::Other]);

        $csv = implode("\n", [
            'Nombres,Apellidos,DNI,Celular,Fecha de nacimiento,Correo,Notas',
            'Rosa,Huamán Quispe,45678912,987654321,01/01/1980,rosa@gmail.com,Otra nota',
            'Lucía,Huamán,,987654321,,,',
        ]);

        $this->import(UploadedFile::fake()->createWithContent('pacientes.csv', $csv), [PatientImporter::EXISTING => PatientImporter::FILL]);

        $rosa->refresh();
        // Lo que tenía vacío se completa; lo que ya tenía no se toca.
        $this->assertSame(['45678912', 'rosa@gmail.com', '1985-04-12', 'Alérgica al látex', 'Huamán'], [
            $rosa->document_number, $rosa->email, $rosa->birth_date->toDateString(), $rosa->admin_notes, $rosa->last_name,
        ]);

        // Otro nombre con el mismo celular es otra persona de la familia.
        $this->assertSame(3, $account->patients()->count());
        $this->assertSame(1, Patient::query()->where('first_name', 'Mateo')->count());
        $this->assertSame([], $this->failures($this->lastImport()));
        $this->assertTrue($son->is($account->patients()->where('first_name', 'Mateo')->sole()));
    }

    public function test_a_dni_that_belongs_to_someone_else_is_not_touched(): void
    {
        $pedro = Patient::factory()->create(['first_name' => 'Pedro', 'last_name' => 'Ramos', 'document_number' => '45678912', 'email' => null]);

        PatientImporter::test(options: [PatientImporter::EXISTING => PatientImporter::FILL])
            ->import(['first_name' => 'Rosa', 'last_name' => 'Huamán', 'document_number' => '45678912', 'email' => 'rosa@gmail.com'])
            ->assertHasRowFailure('El DNI 45678912 ya es de Pedro Ramos. Revisa el DNI.');

        $this->assertNull($pedro->refresh()->email);
    }

    public function test_dates_sex_and_names_are_understood_as_they_are_usually_written(): void
    {
        $importer = PatientImporter::test();

        foreach ([['12-04-1985', 'mujer', '1985-04-12', Sex::Female], ['1990-01-31', 'Varón', '1990-01-31', Sex::Male], ['5/6/07', 'h', '2007-06-05', Sex::Male]] as $i => [$date, $sex, $expectedDate, $expectedSex]) {
            $importer->import(['first_name' => 'JOSÉ', 'last_name' => 'DEL VALLE '.$i, 'birth_date' => $date, 'sex' => $sex])->assertImported();

            $patient = Patient::query()->where('last_name', 'Del Valle '.$i)->sole();
            $this->assertSame(['José', $expectedDate, $expectedSex], [$patient->first_name, $patient->birth_date->toDateString(), $patient->sex]);
        }

        $importer->import(['first_name' => 'Ana', 'last_name' => 'Ruiz', 'birth_date' => '22/11/2027'])->assertHasErrors(['birth_date']);
    }

    public function test_only_reception_and_admin_can_import(): void
    {
        $this->actingAs(Doctor::factory()->create()->user);

        Livewire::test(ListPatients::class)->assertActionHidden('importPatients');

        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(ListPatients::class)->assertActionVisible('importPatients');

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListPatients::class)->assertActionVisible('importPatients');
    }

    public function test_a_file_that_is_not_a_list_is_refused_with_a_clear_message(): void
    {
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(ListPatients::class)
            ->callAction('importPatients', data: ['file' => UploadedFile::fake()->createWithContent('pacientes.xlsx', 'no es un excel')])
            ->assertHasFormErrors(['file']);

        $this->assertSame(0, Import::query()->count());
    }
}
