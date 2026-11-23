<?php

namespace App\Filament\Actions;

use App\Filament\Imports\PatientImporter;
use App\Support\Spreadsheet;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\FileUpload;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use League\Csv\Reader as CsvReader;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

/**
 * "Importar pacientes" en Pacientes: la lista que el consultorio ya tiene en Excel (.xlsx) o en CSV, con una fila por
 * paciente. Solo para quien administra y recepción. Antes de leerla se limpia: se salta un título encima de los
 * encabezados y las filas vacías, y un número que Excel guardó como número vuelve a ser texto. Las filas se guardan
 * en la cola (PatientImporter) y, al terminar, la campana avisa cuántas entraron y cuáles no, con el archivo para
 * corregirlas.
 */
class ImportPatientsAction extends ImportAction
{
    /** Más que esto, en partes: la cola las procesa de a cien. */
    public const MAX_ROWS = 5000;

    private const EXCEL_TYPES = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/x-zip-compressed'];

    private const UNREADABLE = 'No pudimos leer el archivo. Guárdalo otra vez en Excel (.xlsx) o como CSV y vuelve a subirlo.';

    public static function getDefaultName(): ?string
    {
        return 'importPatients';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer(PatientImporter::class)
            ->label('Importar pacientes')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->authorize('import')
            ->modalHeading('Importar pacientes')
            ->modalDescription(fn (ImportAction $action): HtmlString => new HtmlString(
                e('Sube tu lista en Excel o CSV: una fila por paciente, con los títulos de las columnas en la primera fila. Si empiezas de cero, usa la plantilla y borra los ejemplos.').
                ' '.$action->getModalAction('downloadExample')->toHtml()
            ))
            ->modalSubmitActionLabel('Importar')
            ->maxRows(self::MAX_ROWS)
            // Lo que se sube se limpia y se entrega siempre separado por comas.
            ->csvDelimiter(',')
            ->fileRules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if ($value instanceof TemporaryUploadedFile && $this->rows($value) === null) {
                    $fail(self::UNREADABLE);
                }
            }]);

        $parentSchema = $this->schema;

        $this->schema(function (ImportAction $action) use ($parentSchema): array {
            $components = $action->evaluate($parentSchema);

            foreach ($components as $component) {
                if ($component instanceof FileUpload) {
                    $component
                        ->acceptedFileTypes([...$component->getAcceptedFileTypes() ?? [], ...(self::readsExcel() ? self::EXCEL_TYPES : [])])
                        ->placeholder(self::readsExcel() ? 'Elige o arrastra tu archivo de Excel (.xlsx) o CSV' : 'Elige o arrastra tu archivo CSV')
                        ->validationMessages([
                            'extensions' => 'Sube un archivo de Excel (.xlsx) o CSV. Si es un .xls antiguo, ábrelo en Excel y guárdalo como .xlsx.',
                            'mimetypes' => 'Sube un archivo de Excel (.xlsx) o CSV. Si es un .xls antiguo, ábrelo en Excel y guárdalo como .xlsx.',
                        ]);
                }
            }

            return $components;
        });

        // La plantilla en Excel, con el DNI y el celular como texto (así no se pierde el cero de adelante).
        if (self::readsExcel()) {
            $this->registerModalActions([
                Action::make('downloadExample')
                    ->label('Descargar la plantilla')
                    ->link()
                    ->action(fn (): StreamedResponse => response()->streamDownload(
                        fn () => print (self::template()->toXlsx()),
                        'plantilla-pacientes.xlsx',
                        ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                    )),
            ]);
        }
    }

    /** También .xlsx, si el servidor puede abrirlos. */
    public function getFileValidationRules(): array
    {
        return array_map(
            fn (mixed $rule): mixed => $rule === 'extensions:csv,txt' && self::readsExcel() ? 'extensions:csv,txt,xlsx' : $rule,
            parent::getFileValidationRules(),
        );
    }

    /**
     * El archivo ya limpio, como CSV separado por comas: lo que lee la importación de Filament. Falso si no se pudo
     * leer (la revisión del archivo ya avisó por qué).
     *
     * @return resource|false
     */
    public function getUploadedFileStream(TemporaryUploadedFile $file)
    {
        $rows = $this->rows($file);

        if ($rows === null) {
            return false;
        }

        $stream = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }

        rewind($stream);

        return $stream;
    }

    /**
     * Las filas del archivo, desde la de los títulos y sin las vacías; cada celda como texto. Null si no se pudo leer.
     *
     * @return list<list<string>>|null
     */
    private function rows(TemporaryUploadedFile $file): ?array
    {
        try {
            $rows = Str::lower($file->getClientOriginalExtension()) === 'xlsx' ? self::excelRows($file) : $this->csvRows($file);
        } catch (Throwable) {
            return null;
        }

        $rows = array_map(fn (array $row): array => array_map(self::cell(...), array_values($row)), $rows);

        // Un título encima de los encabezados ("Pacientes 2025") ocupa una sola celda: los encabezados son la primera fila con dos o más.
        while ($rows !== [] && count(array_filter($rows[0], filled(...))) < 2) {
            array_shift($rows);
        }

        if ($rows === []) {
            return null;
        }

        $header = array_map(fn (string $title): string => Str::squish($title), $rows[0]);

        while ($header !== [] && end($header) === '') {
            array_pop($header);
        }

        $body = array_filter(array_slice($rows, 1), fn (array $row): bool => count(array_filter($row, filled(...))) > 0);

        return [$header, ...array_map(fn (array $row): array => array_pad(array_slice($row, 0, count($header)), count($header), ''), array_values($body))];
    }

    /**
     * UTF-8, o el de Excel en Windows (tildes y "N°" en Windows-1252). La revisión de Filament prueba antes juegos de
     * caracteres asiáticos que confunden las tildes: "HUAMÁN" salía con signos japoneses. Se revisa el archivo entero.
     */
    protected function detectCsvEncoding(mixed $resource): ?string
    {
        rewind($resource);
        $sample = (string) stream_get_contents($resource, 5 * 1024 * 1024);
        rewind($resource);

        return mb_check_encoding($sample, 'UTF-8') ? 'UTF-8' : 'Windows-1252';
    }

    /**
     * Las filas de un CSV, con la codificación ya pasada a UTF-8 y el separador que use (Excel en español separa
     * con punto y coma).
     *
     * @return list<array<int, mixed>>
     */
    private function csvRows(TemporaryUploadedFile $file): array
    {
        $stream = parent::getUploadedFileStream($file);

        if (! $stream) {
            return [];
        }

        $reader = CsvReader::from($stream);

        if (filled($delimiter = $this->guessCsvDelimiter($reader))) {
            $reader->setDelimiter($delimiter);
        }

        $rows = iterator_to_array($reader->getRecords(), false);

        if (isset($rows[0][0]) && is_string($rows[0][0])) {
            $rows[0][0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]);
        }

        return $rows;
    }

    /**
     * Las filas de la primera hoja de un Excel. De una celda con fórmula vale lo que Excel muestra (=NOMPROPIO(A2)
     * da "Rosa María"), no la fórmula.
     *
     * @return list<array<int, mixed>>
     */
    private static function excelRows(TemporaryUploadedFile $file): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'pacientes');
        $reader = new XlsxReader;

        try {
            $source = $file->readStream();
            $target = fopen($path, 'w');
            stream_copy_to_stream($source, $target);
            fclose($target);
            fclose($source);

            $reader->open($path);
            $rows = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(fn (Cell $cell): mixed => $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue(), $row->getCells());
                }

                break;
            }

            return $rows;
        } finally {
            $reader->close();
            @unlink($path);
        }
    }

    /** Una celda como texto: la fecha como día/mes/año y el número entero sin decimales (el DNI y el celular). */
    private static function cell(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof DateTimeInterface => CarbonImmutable::instance($value)->format('d/m/Y'),
            is_float($value) && floor($value) === $value && abs($value) < 1e15 => sprintf('%.0f', $value),
            is_bool($value) => $value ? '1' : '0',
            default => trim((string) $value),
        };
    }

    /** Abrir un .xlsx necesita la extensión zip de PHP; sin ella, solo CSV. */
    private static function readsExcel(): bool
    {
        return Spreadsheet::canWriteXlsx() && class_exists(ZipArchive::class);
    }

    /** La plantilla: los títulos que se reconocen solos y dos ejemplos (madre e hijo con el mismo celular). */
    private static function template(): Spreadsheet
    {
        $columns = collect(PatientImporter::getColumns())->reject(fn ($column): bool => $column->getName() === 'second_last_name')->values();
        $types = $columns->map(fn ($column): string => $column->getName() === 'birth_date' ? Spreadsheet::DATE : Spreadsheet::TEXT)->all();
        $rows = [];

        foreach ([0, 1] as $example) {
            $rows[] = $columns->map(function ($column) use ($example): mixed {
                $value = $column->getExamples()[$example] ?? null;

                return $column->getName() === 'birth_date' && filled($value) ? CarbonImmutable::createFromFormat('d/m/Y', $value)->startOfDay() : $value;
            })->all();
        }

        return (new Spreadsheet)->sheet('Pacientes', $columns->map(fn ($column): string => $column->getExampleHeader())->all(), $types, $rows);
    }
}
