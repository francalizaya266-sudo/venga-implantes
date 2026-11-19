# Igual que actualizar-indice.py, pero con Microsoft Word en Windows (sin LibreOffice): abre el .docx sin mostrarlo,
# llena el índice y los campos, lo guarda y exporta su PDF de verificación al lado.
#
#   powershell -ExecutionPolicy Bypass -File plantilla/actualizar-indice-word.ps1 word/archivo.docx
param([Parameter(Mandatory = $true)][string]$Ruta)

$ErrorActionPreference = 'Stop'
$docx = (Resolve-Path $Ruta).Path
$pdf = [System.IO.Path]::ChangeExtension($docx, '.pdf')

$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
try {
    $doc = $word.Documents.Open($docx, $false, $false, $false)
    foreach ($indice in $doc.TablesOfContents) { $indice.Update() }
    $doc.Fields.Update() | Out-Null
    # Una segunda pasada: los números de página del índice cambian cuando el índice ya ocupa su lugar.
    foreach ($indice in $doc.TablesOfContents) { $indice.Update() }
    $doc.Save()
    # 17 = wdExportFormatPDF
    $doc.ExportAsFixedFormat($pdf, 17)
    $doc.Close($false)
}
finally {
    $word.Quit()
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) | Out-Null
}
