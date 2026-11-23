@echo off
rem ===========================================================================
rem  Quita la tarea de Windows que corre cada minuto las tareas automáticas del
rem  consultorio (la que crea encender-tareas-automaticas.bat).
rem
rem  Desde ese momento ya no salen solos los recordatorios ni las copias de
rem  seguridad, y a la hora el panel lo avisa en Hoy. Para volver a encenderlas,
rem  usa encender-tareas-automaticas.bat, o abre el sistema con
rem  iniciar-consultorio.bat (corren mientras el sistema esté abierto).
rem ===========================================================================
setlocal
chcp 65001 >nul
set "TAREA=Consultorio - tareas automaticas"

rem  Quitar la tarea pide permiso de administrador: si no lo tiene, el archivo se vuelve a abrir pidiéndolo.
fltmc >nul 2>nul
if errorlevel 1 if not "%~1"=="elevado" (
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -ArgumentList 'elevado' -Verb RunAs"
    exit /b
)

schtasks /query /tn "%TAREA%" >nul 2>nul
if errorlevel 1 (
    echo No hay ninguna tarea que quitar: las tareas automáticas no estaban encendidas con encender-tareas-automaticas.bat.
    pause
    exit /b 0
)

schtasks /delete /tn "%TAREA%" /f >nul
echo Listo: se quitó la tarea "%TAREA%".
echo Las tareas automáticas corren solo mientras abras el sistema con iniciar-consultorio.bat.
pause
