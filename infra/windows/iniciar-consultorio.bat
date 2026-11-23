@echo off
rem ===========================================================================
rem  Abre el sistema del consultorio en esta computadora.
rem
rem  Haz doble clic en este archivo cada vez que vayas a usar el sistema. Hace
rem  dos cosas a la vez:
rem   1. Enciende las tareas automáticas: recordatorios, cuotas, lista de espera
rem      y copias de seguridad ("php artisan schedule:work"). Si ya las encendiste
rem      para siempre con encender-tareas-automaticas.bat, se salta este paso.
rem   2. Enciende el sistema ("php artisan serve") y abre el panel en el
rem      navegador.
rem
rem  Deja abiertas sus ventanas negras mientras uses el sistema: al cerrarlas, el
rem  sistema y las tareas se apagan. La base de datos (MySQL o MariaDB) tiene que
rem  estar encendida antes.
rem
rem  Para tenerlo a mano: clic derecho en este archivo > Enviar a > Escritorio.
rem ===========================================================================
setlocal
chcp 65001 >nul

rem  El panel queda en http://127.0.0.1:8000/panel. Cambia el numero si otro programa ya usa ese puerto.
set "PUERTO=8000"
rem  El nombre de la tarea de Windows que crea encender-tareas-automaticas.bat.
set "TAREA=Consultorio - tareas automaticas"

cd /d "%~dp0..\..\backend" 2>nul
if errorlevel 1 (
    echo No se encontró la carpeta backend. Este archivo tiene que estar en infra\windows, dentro de la carpeta del sistema.
    pause
    exit /b 1
)

where php >nul 2>nul
if errorlevel 1 (
    echo No se encontró PHP en esta computadora. Pide ayuda a quien instaló el sistema.
    pause
    exit /b 1
)

rem  Si la tarea de Windows ya las corre cada minuto, no hace falta otra ventana.
rem  Si no, corren una vez enseguida (así Hoy no avisa que están apagadas mientras
rem  se espera el próximo minuto) y después cada minuto.
schtasks /query /tn "%TAREA%" >nul 2>nul
if errorlevel 1 (
    start "Tareas automáticas del consultorio - no cierres esta ventana" /min cmd /c "php artisan schedule:run & php artisan schedule:work"
)

rem  Abre el panel en el navegador cuando el sistema ya responde, unos segundos después.
start "" /min cmd /c "timeout /t 4 /nobreak >nul & start http://127.0.0.1:%PUERTO%/panel"

title Sistema del consultorio - no cierres esta ventana
echo.
echo  El sistema está encendido en http://127.0.0.1:%PUERTO%/panel
echo  No cierres esta ventana mientras lo uses: al cerrarla, el sistema se apaga.
echo.
call php artisan serve --host=127.0.0.1 --port=%PUERTO%
