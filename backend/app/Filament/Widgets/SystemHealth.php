<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Backups;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Backup\DatabaseBackup;
use App\Services\Backup\OffsiteCopies;
use App\Services\ScheduledTasks;
use Filament\Widgets\Widget;

/**
 * Solo aparece si algo anda mal y nadie lo vería: las tareas automáticas no corren, las copias de seguridad
 * dejaron de hacerse, pasó una semana sin bajarlas para guardarlas fuera del sistema, o la API de WhatsApp no pudo
 * enviar avisos.
 */
class SystemHealth extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.system-health';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->isAdmin() && self::issues() !== [];
    }

    /** @return array{issues: list<array{heading: string, description: string, url: string|null, link: string|null}>} */
    protected function getViewData(): array
    {
        return ['issues' => self::issues()];
    }

    /** @return list<array{heading: string, description: string, url: string|null, link: string|null}> */
    private static function issues(): array
    {
        $issues = [];
        $tasks = app(ScheduledTasks::class);

        // Primero, porque explica los demás: sin las tareas automáticas tampoco salen las copias ni los recordatorios.
        if ($tasks->areStopped()) {
            $lastRun = $tasks->lastRunAt();
            $issues[] = [
                'heading' => 'Las tareas automáticas no están corriendo',
                'description' => 'Sin ellas no salen los recordatorios ni las copias de seguridad.'
                    .($lastRun ? ' Corrieron por última vez '.$lastRun->diffForHumans().'.' : '')
                    .' '.$tasks->howToTurnOn(),
                'url' => null,
                'link' => null,
            ];
        }

        $backups = app(DatabaseBackup::class);

        if ($backups->isStale()) {
            $latest = $backups->latestAt();
            $issues[] = [
                'heading' => $latest ? 'La última copia de seguridad es de '.$latest->diffForHumans() : 'Todavía no hay copias de seguridad',
                'description' => $tasks->areRunning()
                    ? 'Las tareas automáticas están corriendo, pero la copia no se creó. Entra a «Ver copias» y toca «Crear copia ahora»: si sale un error, avísale a quien instaló el sistema.'
                    : 'La copia automática no corrió: revisa que las tareas automáticas '.$tasks->ofMachine().' estén encendidas. Mientras tanto, entra a «Ver copias» y toca «Crear copia ahora».',
                'url' => Backups::canAccess() ? Backups::getUrl() : null,
                'link' => 'Ver copias',
            ];
        }

        $due = app(OffsiteCopies::class)->due();

        if ($due !== null) {
            $issues[] = [
                'heading' => 'Toca descargar la copia de seguridad de esta semana',
                'description' => $due.'. Descárgalas y guárdalas fuera del sistema, por ejemplo en el Google Drive del consultorio.',
                'url' => Backups::canAccess() ? Backups::getUrl() : null,
                'link' => 'Ver copias',
            ];
        }

        $failed = WhatsappMessage::query()->failedRecently()->count();

        if ($failed > 0) {
            $issues[] = [
                'heading' => $failed === 1 ? '1 aviso de WhatsApp no se pudo enviar esta semana' : "{$failed} avisos de WhatsApp no se pudieron enviar esta semana",
                'description' => 'La API de WhatsApp los rechazó. Revisa el motivo en cada aviso y envíalos a mano si hace falta.',
                'url' => WhatsappMessageResource::getUrl('index', ['tab' => 'failed']),
                'link' => 'Ver avisos',
            ];
        }

        return $issues;
    }
}
