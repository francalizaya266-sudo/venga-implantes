@echo off
rem ===========================================================================
rem  Enciende para siempre las tareas automáticas del consultorio.
rem
rem  Crea en el Programador de tareas de Windows una tarea que cada minuto
rem  ejecuta "php artisan schedule:run": prepara los recordatorios, carga las
rem  cuotas, libera la lista de espera y crea la copia de seguridad de cada día.
rem  La tarea se llama: Consultorio - tareas automaticas
rem
rem  Corre sola desde que se prende la computadora, aunque el sistema esté
rem  cerrado o nadie haya iniciado sesión, y también con la laptop desenchufada.
rem  No abre ventanas.
rem
rem  Se hace una sola vez: doble clic y acepta el permiso que pide Windows (hace
rem  falta para crear la tarea).
rem
rem  Con la computadora apagada, dormida o en hibernación no corre nada. Al
rem  prenderla se pone al día sola: la copia del día se crea en ese momento y los
rem  recordatorios que tocaban mientras tanto salen un poco tarde.
rem
rem  La tarea no ve la unidad G: de Google Drive (esa unidad solo la ve quien
rem  inició sesión). Para la copia extra en otra carpeta usa OneDrive, un USB o
rem  Google Drive en modo "Reflejar archivos" (la carpeta "Mi unidad" de tu
rem  usuario).
rem
rem  Para quitarla: doble clic en quitar-tareas-automaticas.bat. O en una ventana
rem  de comandos abierta como administrador:
rem      schtasks /delete /tn "Consultorio - tareas automaticas" /f
rem  También se ve, se pausa o se borra en Inicio > Programador de tareas >
rem  Biblioteca del Programador de tareas.
rem ===========================================================================
setlocal
chcp 65001 >nul
set "TAREA=Consultorio - tareas automaticas"

rem  Crear la tarea pide permiso de administrador: si no lo tiene, el archivo se vuelve a abrir pidiéndolo.
fltmc >nul 2>nul
if errorlevel 1 if not "%~1"=="elevado" (
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -ArgumentList 'elevado' -Verb RunAs"
    exit /b
)

set "BACKEND=%~dp0..\..\backend"
for %%I in ("%BACKEND%") do set "BACKEND=%%~fI"
if not exist "%BACKEND%\artisan" (
    echo No se encontró la carpeta backend. Este archivo tiene que estar en infra\windows, dentro de la carpeta del sistema.
    pause
    exit /b 1
)

set "PHP="
for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP set "PHP=%%P"
if not defined PHP (
    echo No se encontró PHP en esta computadora. Pide ayuda a quien instaló el sistema.
    pause
    exit /b 1
)

rem  /np: corre aunque nadie haya iniciado sesión, sin ventanas y sin guardar la contraseña de Windows.
schtasks /create /f /tn "%TAREA%" /sc minute /mo 1 /ru "%USERDOMAIN%\%USERNAME%" /np /tr "\"%PHP%\" \"%BACKEND%\artisan\" schedule:run"
if errorlevel 1 (
    echo.
    echo No se pudo crear la tarea de Windows. Mientras tanto, abre el sistema con iniciar-consultorio.bat:
    echo así las tareas automáticas corren mientras el sistema esté abierto.
    pause
    exit /b 1
)

rem  Windows crea las tareas para que corran solo con el cargador enchufado: en una laptop tienen que correr
rem  también a batería. Además, si una pasada se cuelga, se corta a la hora y no frena a las siguientes.
powershell -NoProfile -Command "$s = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances Parallel -ExecutionTimeLimit (New-TimeSpan -Hours 1); Set-ScheduledTask -TaskName '%TAREA%' -Settings $s | Out-Null"
if errorlevel 1 (
    echo.
    echo Ojo: no se pudo dejar que corra a batería. Abre Inicio, Programador de tareas, la tarea "%TAREA%",
    echo pestaña Condiciones, y desmarca "Iniciar la tarea solo si el equipo está conectado a la corriente alterna".
)

schtasks /run /tn "%TAREA%" >nul 2>nul
echo.
echo Listo: las tareas automáticas corren cada minuto, aunque el sistema esté cerrado.
echo En unos minutos desaparece el aviso de Hoy.
echo.
pause
