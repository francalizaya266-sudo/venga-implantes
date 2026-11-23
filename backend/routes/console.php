<?php

use App\Models\Setting;
use App\Services\Backup\DatabaseBackup;
use App\Services\Backup\ExtraCopy;
use App\Services\Reports\MonthlyReport;
use App\Services\ScheduledTasks;
use Illuminate\Support\Facades\Schedule;

/*
| El hosting compartido ejecuta un solo cron cada minuto:
| * * * * * php /ruta/al/proyecto/artisan schedule:run >> /dev/null 2>&1
| En una laptop con Windows: infra/windows/iniciar-consultorio.bat (schedule:work) o encender-tareas-automaticas.bat.
*/

// El latido: cada pasada anota su hora. Si lleva más de una hora sin anotarla, Hoy le avisa a quien administra.
Schedule::call(fn () => ScheduledTasks::beat())
    ->name('latido')
    ->everyMinute();

// Cada pasada prepara lo que ya toca (desde la hora de Ajustes › Avisos, el día anterior a la cita): si una se pierde, la siguiente lo pone al día.
Schedule::command('citas:preparar-recordatorios')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Igual con las citas que a la tarde (la hora de Ajustes › Avisos) del día anterior siguen sin confirmar.
Schedule::command('citas:marcar-sin-confirmar')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Las cuotas de los planes entran al saldo el día que vencen.
Schedule::command('cuotas:cargar')
    ->dailyAt('06:00')
    ->withoutOverlapping();

// El día 1, desde las 6:00, el reporte del mes anterior queda listo y quien administra recibe el aviso en la campana.
// Si ese día la laptop estuvo apagada, se hace en cuanto se prende (una sola vez por mes).
Schedule::command('reportes:preparar-mes')
    ->everyFifteenMinutes()
    ->when(fn () => app(MonthlyReport::class)->isDue())
    ->withoutOverlapping();

// Unos días antes de cada cuota, su aviso queda listo en la bandeja de WhatsApp: desde la hora de los recordatorios
// (Ajustes › Avisos). Cada pasada prepara solo lo que falta, así que una hora cambiada en el panel vale sin reiniciar nada.
Schedule::command('cuotas:preparar-avisos')
    ->everyFifteenMinutes()
    ->when(fn () => now()->gte(today()->setTimeFromTimeString((string) Setting::get('reminders.day_before_at', '09:00'))))
    ->withoutOverlapping();

// Lo ofrecido a la lista de espera que nadie confirmó a tiempo vuelve a quedar libre (y se sugiere al siguiente).
Schedule::command('lista-espera:liberar-apartados')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// La campana muestra lo de hoy: lo de días anteriores queda leído (sigue en "Hoy" y en sus listas). Cada pasada pone
// al día lo que falte, así que con la laptop apagada a medianoche se hace apenas se prende.
Schedule::command('campana:limpiar')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('whatsapp:enviar')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Los avisos de citas y cuotas también salen en la pantalla del celular de quien los activó en Mi cuenta, apenas tocan.
Schedule::command('avisos:enviar-al-celular')
    ->everyMinute()
    ->withoutOverlapping();

// Sin servidor de colas: el mismo cron procesa los trabajos pendientes.
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('model:prune')->daily();

// Lo que cada doctor realmente tarda en cada tratamiento, con las atenciones hasta ayer.
Schedule::command('agenda:aprender-duraciones')
    ->dailyAt('02:30')
    ->withoutOverlapping();

// Copia de la base de datos una vez al día, desde las 3:00, cuando nadie está usando el sistema. Si a esa hora el
// equipo estaba apagado (una laptop), se hace en cuanto se prende: así ningún día queda sin copia.
Schedule::command('respaldo:crear')
    ->everyFifteenMinutes()
    ->when(fn () => app(DatabaseBackup::class)->isDueToday())
    ->withoutOverlapping();

// La copia extra en otra carpeta (Copias de seguridad) se guarda apenas se crea la del día. Si en ese momento la carpeta
// no estaba (un USB desconectado, Google Drive que todavía no abría), se vuelve a intentar cada 15 minutos.
Schedule::command('respaldo:copia-extra')
    ->everyFifteenMinutes()
    ->when(fn () => app(ExtraCopy::class)->isPending())
    ->withoutOverlapping();
