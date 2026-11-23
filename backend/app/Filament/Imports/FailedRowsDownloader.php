<?php

namespace App\Filament\Imports;

use App\Support\Spreadsheet;
use Filament\Actions\Imports\Downloaders\Contracts\Downloader;
use Filament\Actions\Imports\Downloaders\CsvDownloader;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
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

        $rows = $import->failedRows()->orderBy('id')->get();
        $header = [...array_keys($rows->first()->data ?? []), __('filament-actions::import.failure_csv.error_header')];

        $book = (new Spreadsheet)->sheet(
            'Por corregir',
            $header,
            array_fill(0, count($header), Spreadsheet::TEXT),
            $rows->map(fn (FailedImportRow $row): array => [
                ...array_values($row->data),
                $row->validation_error ?? __('filament-actions::import.failure_csv.system_error'),
            ])->all(),
        );

        return response($book->toXlsx(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="filas-sin-importar-'.$import->getKey().'.xlsx"',
        ]);
    }
}
