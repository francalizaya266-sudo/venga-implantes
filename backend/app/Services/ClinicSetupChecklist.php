<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Filament\Pages\Backups;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Resources\Treatments\TreatmentResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Doctor;
use App\Models\Setting;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Backup\DatabaseBackup;
use App\Services\Backup\ExtraCopy;
use Illuminate\Support\Collection;

/**
 * Lo que hay que dejar listo para empezar a atender con el sistema, cada cosa con el enlace a donde se hace: el horario
 * de cada doctor, los precios, el usuario de recepción, los datos de las hojas impresas, las tareas automáticas y las
 * copias de seguridad. Lo muestra la puesta a punto del consultorio, como la de la web.
 */
final class ClinicSetupChecklist
{
    /** Lo marca quien administra cuando nadie trabaja en recepción: quien atiende también recibe a los pacientes. */
    public const NO_RECEPTION = 'setup.no_reception';

    public function __construct(private ScheduledTasks $tasks, private DatabaseBackup $backups, private ExtraCopy $extra) {}

    /**
     * @return list<array{key: string, title: string, why: string, done: bool, url: string|null, link: string|null, action?: string}>
     */
    public function items(): array
    {
        $hasDoctors = Doctor::query()->active()->exists();
        $withoutSchedule = Doctor::query()->active()->whereDoesntHave('schedules')->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $hasTreatments = Treatment::query()->active()->exists();
        $withoutPrice = Treatment::query()->active()->whereNull('price_from')->orderBy('sort_order')->orderBy('name')->pluck('name');
        $hasReception = User::query()->where('role', UserRole::Reception)->where('is_active', true)->exists();
        $extraFolder = $this->extra->folder();
        $extraProblem = $this->extra->problem();

        return [
            [
                'key' => 'schedules',
                'title' => 'El horario de cada doctor',
                'why' => match (true) {
                    ! $hasDoctors => 'Todavía no hay doctores: sin ellos no hay agenda ni reservas.',
                    $withoutSchedule->isEmpty() => 'Todos tienen su horario.',
                    default => 'Falta el de '.self::joinNames($withoutSchedule->pluck('name')).'. Sin horario no se le pueden dar citas, ni aquí ni en la web.',
                },
                'done' => $hasDoctors && $withoutSchedule->isEmpty(),
                'url' => $withoutSchedule->count() === 1 ? DoctorResource::getUrl('edit', ['record' => $withoutSchedule->first()]) : DoctorResource::getUrl('index'),
                'link' => $withoutSchedule->count() === 1 ? 'Poner su horario' : 'Ver doctores',
            ],
            [
                'key' => 'prices',
                'title' => 'Los tratamientos con su precio',
                'why' => match (true) {
                    ! $hasTreatments => 'Todavía no hay tratamientos: sin ellos no se dan citas ni se cobra.',
                    $withoutPrice->isEmpty() => 'Todos tienen precio.',
                    default => 'Falta el precio de '.self::joinNames($withoutPrice).'. Sin precio, la caja no sabe cuánto cobrar.',
                },
                'done' => $hasTreatments && $withoutPrice->isEmpty(),
                'url' => TreatmentResource::getUrl('index'),
                'link' => 'Ver tratamientos',
            ],
            [
                'key' => 'reception',
                'title' => 'Un usuario para recepción',
                'why' => 'Quien recibe a los pacientes entra con su propio usuario: ve la agenda y cobra, pero no las historias clínicas. Si nadie trabaja en recepción, márcalo.',
                'done' => $hasReception || Setting::get(self::NO_RECEPTION) === true,
                'url' => UserResource::getUrl('index'),
                'link' => 'Crear el usuario',
                'action' => 'skipReceptionAction',
            ],
            [
                'key' => 'legal',
                'title' => 'El RUC y la razón social',
                'why' => 'Salen en los presupuestos, recibos y recetas impresos.',
                'done' => filled(Setting::get('clinic.ruc')) && filled(Setting::get('clinic.legal_name')),
                'url' => Settings::getUrl(),
                'link' => 'Ponerlos en Ajustes',
            ],
            [
                'key' => 'phone',
                'title' => 'El celular del consultorio',
                'why' => 'Es el WhatsApp al que escriben los pacientes desde la web.',
                'done' => filled(Setting::get('clinic.whatsapp')),
                'url' => Settings::getUrl(),
                'link' => 'Ponerlo en Ajustes',
            ],
            [
                'key' => 'tasks',
                'title' => 'Las tareas automáticas encendidas',
                'why' => 'Sin ellas no salen los recordatorios ni las copias de seguridad. '.$this->tasks->howToTurnOn(),
                'done' => $this->tasks->areRunning(),
                // Se encienden en la computadora, no en el panel: el texto dice cómo.
                'url' => null,
                'link' => null,
            ],
            [
                'key' => 'first-backup',
                'title' => 'La primera copia de seguridad',
                'why' => 'Se crea sola cada madrugada con las tareas automáticas. También puedes crearla ahora.',
                'done' => $this->backups->latestAt() !== null,
                'url' => Backups::getUrl(),
                'link' => 'Ir a Copias',
            ],
            [
                'key' => 'extra-copy',
                'title' => 'La copia extra en otra carpeta',
                'why' => $extraProblem !== null
                    ? 'La última no se pudo guardar: '.$extraProblem
                    : 'Para que la copia no se pierda junto con la computadora: en OneDrive, Google Drive o un USB.',
                'done' => $extraFolder !== null && $extraProblem === null,
                'url' => Backups::getUrl().'#copia-extra',
                'link' => $extraFolder === null ? 'Elegir carpeta' : 'Revisarla',
            ],
        ];
    }

    /** @return list<array{key: string, title: string, why: string, done: bool, url: string|null, link: string|null, action?: string}> */
    public function missing(): array
    {
        return array_values(array_filter($this->items(), fn (array $item) => ! $item['done']));
    }

    /** @param  Collection<int, string>  $names  "Dra. Ana Torres", "Dra. Ana Torres y Dr. Luis Ramos", "… y 2 más". */
    private static function joinNames(Collection $names): string
    {
        return $names->count() <= 2 ? $names->join(' y ') : $names->take(2)->join(', ').' y '.($names->count() - 2).' más';
    }
}
