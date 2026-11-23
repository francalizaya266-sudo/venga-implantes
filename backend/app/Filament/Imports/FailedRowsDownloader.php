<?php

namespace App\Filament\Imports;

use App\Support\Spreadsheet;
use Filament\Actions\Imports\Downloaders\Contracts\Downloader;
use Filament\Actions\Imports\Downloaders\CsvDownloader;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las filas que no entraron en una importación, en Excel: las mismas columnas del archivo y una más, "Qué corregir".
 * Se corrigen ahí mismo y el archivo se vuelve a importar tal cual. Todo va como texto, así el DNI no pierde su cero.
 * Sin la extensión zip de PHP, el CSV de siempre.
 */
class FailedRowsDownloader implements Downloader
{
    public function __invoke(Import $import): Response
    {
        if (! Spreadsheet::canWriteXlsx()) {
            return app(CsvDownloader::class)($import);
        }

        /** @var Collection<int, FailedImportRow> $rows */
        $rows = $import->failedRows()->orderBy('id')->get();
        $columns = self::columns($import, $rows);
        $header = [...$columns, __('filament-actions::import.failure_csv.error_header')];

        $book = (new Spreadsheet)->sheet(
            'Por corregir',
            $header,
            array_fill(0, count($header), Spreadsheet::TEXT),
            $rows->map(fn (FailedImportRow $row): array => [
                ...array_map(fn (string $column): mixed => $row->data[$column] ?? null, $columns),
                $row->validation_error ?? __('filament-actions::import.failure_csv.system_error'),
            ])->all(),
        );

        return response($book->toXlsx(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="filas-sin-importar-'.$import->getKey().'.xlsx"',
        ]);
    }

    /**
     * Las columnas del archivo, en el orden de la plantilla. MySQL guarda el JSON de cada fila con las claves en su
     * propio orden (primero las más cortas), así que de ahí no se puede leer el orden del archivo. Cada columna va en el
     * lugar de la columna del importador que la reconoce, igual que al importar; las que no reconoce, al final.
     *
     * @param  Collection<int, FailedImportRow>  $rows
     * @return list<string>
     */
    private static function columns(Import $import, Collection $rows): array
    {
        $columns = array_keys($rows->first()->data ?? []);
        $positions = [];

        foreach (array_values($import->importer::getColumns()) as $position => $column) {
            foreach ($column->getGuesses() as $guess) {
                $positions[$guess] ??= $position;
            }
        }

        $position = fn (string $column): int => $positions[Str::lower($column)] ?? PHP_INT_MAX;
        usort($columns, fn (string $a, string $b): int => $position($a) <=> $position($b));

        return $columns;
    }
}
