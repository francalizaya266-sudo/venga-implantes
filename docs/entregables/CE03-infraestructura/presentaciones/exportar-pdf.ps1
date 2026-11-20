# Abre cada .pptx de esta carpeta en PowerPoint (sin mostrarlo) y guarda su PDF al lado. Sirve para revisar que
# PowerPoint los abre sin pedir reparación y para repartir las presentaciones en PDF.
#
#   powershell -ExecutionPolicy Bypass -File docs/entregables/CE03-infraestructura/presentaciones/exportar-pdf.ps1
$ErrorActionPreference = 'Stop'
$carpeta = $PSScriptRoot
$app = New-Object -ComObject PowerPoint.Application
try {
    Get-ChildItem -Path $carpeta -Filter *.pptx | ForEach-Object {
        $pdf = [System.IO.Path]::ChangeExtension($_.FullName, '.pdf')
        # Open(archivo, solo lectura, sin título, sin ventana)
        $pres = $app.Presentations.Open($_.FullName, -1, 0, 0)
        # 32 = ppSaveAsPDF
        $pres.SaveAs($pdf, 32)
        Write-Output "$($_.Name): $($pres.Slides.Count) diapositivas"
        $pres.Close()
    }
}
finally {
    $app.Quit()
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject($app) | Out-Null
}
