<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Concerns\HasSettingsMenuGroup;
use App\Models\Setting;
use App\Models\User;
use App\Services\Backup\DatabaseBackup;
use App\Services\Backup\ExtraCopy;
use App\Services\Backup\FilesBackup;
use App\Services\Backup\OffsiteCopies;
use App\Services\ScheduledTasks;
use App\Support\Dates;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Number;

/**
 * Las copias de seguridad, para bajarlas y guardarlas fuera del hosting: la de los datos, que se crea
 * sola cada día, y las radiografías, fotos, documentos y firmas, que se bajan en archivos .zip.
 * Cualquier administrador ve si están al día, crea una y borra las viejas. Descargarlas es otra cosa: traen
 * datos clínicos, así que solo las baja el administrador que es odontólogo (Ley N.º 29733).
 */
class Backups extends Page
{
    use HasSettingsMenuGroup;

    protected string $view = 'filament.pages.backups';

    protected static ?string $title = 'Copias de seguridad';

    protected static ?string $slug = 'copias-de-seguridad';

    protected static ?string $navigationParentItem = 'Configuración';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    public static function settingsMenuGroup(): string
    {
        return 'Consultorio';
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->isAdmin();
    }

    /** Las copias traen la historia clínica de todos los pacientes: las baja solo quien puede verla. */
    public static function canDownload(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->isAdmin() && $user->canSeeClinical();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Crear copia ahora')
                ->icon(Heroicon::OutlinedPlus)
                ->action(function (DatabaseBackup $backup, ExtraCopy $extra) {
                    $name = $backup->create();
                    // Con la copia extra encendida, también va a la otra carpeta, como la de cada día.
                    $savedElsewhere = $extra->folder() === null ? null : $extra->copy($name);

                    Notification::make()
                        ->title('Copia creada')
                        ->body(match ($savedElsewhere) {
                            true => 'También quedó en '.$extra->folder().'.',
                            false => 'No se pudo guardar también en la otra carpeta. '.$extra->problem(),
                            null => null,
                        })
                        ->status($savedElsewhere === false ? 'warning' : 'success')
                        ->send();
                }),
        ];
    }

    /** El tacho de cada copia vieja. La más reciente no lo tiene: es la que protege al consultorio hoy. */
    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Borrar esta copia')
            ->icon(Heroicon::OutlinedTrash)
            ->iconButton()
            ->color('gray')
            ->size(Size::Small)
            ->tooltip('Borrar esta copia')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'Borrar la copia del '.self::dayOf((string) ($arguments['file'] ?? '')))
            ->modalDescription('Se borra del sistema y no se puede recuperar. Si ya la descargaste, la que guardaste fuera sigue sirviendo.')
            ->modalSubmitActionLabel('Borrar copia')
            ->action(function (array $arguments, DatabaseBackup $backup, OffsiteCopies $copies): void {
                $file = (string) ($arguments['file'] ?? '');

                if (! $backup->delete($file)) {
                    Notification::make()->title('Esa copia no se puede borrar')->body('La copia más reciente se queda siempre.')->danger()->send();

                    return;
                }

                $copies->note(OffsiteCopies::DELETED, $file);

                Notification::make()->title('Copia borrada')->success()->send();
            });
    }

    /**
     * Cuántas copias de los datos se guardan. Antes solo se cambiaba en el servidor. Al bajar el número no se borra
     * nada en el momento: las que sobran se van con la próxima copia, y hasta entonces se puede volver atrás.
     */
    public function keepAction(): Action
    {
        return Action::make('keep')
            ->label('Cambiar cuántas se guardan')
            ->link()
            ->size(Size::Small)
            ->modalHeading('Cuántas copias de los datos se guardan')
            ->modalDescription('Se crea una copia cada día. Con 14 puedes volver hasta dos semanas atrás; con 30, hasta un mes. Mientras más guardes, más espacio ocupan en el hosting.')
            ->modalWidth('md')
            ->fillForm(fn (DatabaseBackup $backup) => ['keep' => $backup->keep()])
            ->schema([
                TextInput::make('keep')
                    ->label('Guardar las últimas')
                    ->suffix('copias')
                    ->numeric()
                    ->integer()
                    ->minValue(DatabaseBackup::KEEP_AT_LEAST)
                    ->maxValue(DatabaseBackup::KEEP_AT_MOST)
                    ->required()
                    ->helperText('Entre '.DatabaseBackup::KEEP_AT_LEAST.' y '.DatabaseBackup::KEEP_AT_MOST.'. Si bajas el número, las copias más viejas se borran al crear la próxima copia, no ahora.'),
            ])
            ->modalSubmitActionLabel('Guardar')
            ->action(function (array $data, DatabaseBackup $backup): void {
                $keep = (int) $data['keep'];
                Setting::set(DatabaseBackup::KEEP_SETTING, $keep);
                $extra = count($backup->list()) - $keep;

                Notification::make()
                    ->title("Se guardarán las últimas {$keep} copias")
                    ->body($extra > 0 ? self::extraCopies($extra) : null)
                    ->success()
                    ->send();
            });
    }

    /**
     * La carpeta de la copia extra y cuántas se guardan ahí. La elige solo quien puede descargar las copias: ahí van
     * las historias clínicas de todos los pacientes, igual que en una descarga. Al guardar, se copia enseguida la más
     * reciente, para saber en el momento si la carpeta sirve.
     */
    public function extraFolderAction(): Action
    {
        return Action::make('extraFolder')
            ->label(fn () => app(ExtraCopy::class)->folder() === null ? 'Elegir carpeta' : 'Cambiar carpeta')
            ->icon(Heroicon::OutlinedFolder)
            ->button()
            ->size(Size::Small)
            ->color(fn () => app(ExtraCopy::class)->folder() === null ? 'primary' : 'gray')
            ->visible(fn () => self::canDownload())
            ->modalHeading('Copia extra en otra carpeta')
            ->modalDescription('Cada día, apenas se crea la copia, se guarda otra igual en esta carpeta. Elige una que no se quede solo en esta computadora: la de OneDrive o Google Drive, que la suben solas a la nube, o un USB.')
            ->modalWidth('lg')
            ->fillForm(fn (ExtraCopy $extra) => ['folder' => $extra->folder(), 'keep' => $extra->keep()])
            ->schema([
                TextInput::make('folder')
                    ->label('Carpeta')
                    ->placeholder('C:\Users\consultorio\OneDrive\Copias del consultorio')
                    ->required()
                    ->maxLength(255)
                    ->rule(fn () => function (string $attribute, $value, $fail) {
                        $problem = app(ExtraCopy::class)->problemWith((string) $value);

                        if ($problem !== null) {
                            $fail($problem);
                        }
                    })
                    ->helperText('Copia la ruta desde la barra de arriba del Explorador de archivos. Con Google Drive, usa la carpeta «Mi unidad» en modo «Reflejar archivos».'),
                TextInput::make('keep')
                    ->label('Guardar ahí las últimas')
                    ->suffix('copias')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(DatabaseBackup::KEEP_AT_MOST)
                    ->required()
                    ->helperText('Las más viejas se borran solas de esa carpeta. Solo se tocan las copias: lo demás que tenga la carpeta no se toca.'),
            ])
            ->modalSubmitActionLabel('Guardar y copiar ahora')
            ->action(function (array $data, ExtraCopy $extra): void {
                Setting::set(ExtraCopy::FOLDER_SETTING, trim((string) $data['folder']));
                Setting::set(ExtraCopy::KEEP_SETTING, (int) $data['keep']);
                $saved = $extra->copyLatest();

                Notification::make()
                    ->title(match ($saved) {
                        true => 'La copia más reciente ya está también en esa carpeta',
                        false => 'Carpeta guardada, pero la copia no se pudo guardar ahí',
                        null => 'Carpeta guardada',
                    })
                    ->body($saved === false ? $extra->problem() : 'Desde ahora, la copia de cada día se guarda también ahí.')
                    ->status($saved === false ? 'warning' : 'success')
                    ->send();
            });
    }

    public function stopExtraCopyAction(): Action
    {
        return Action::make('stopExtraCopy')
            ->label('Dejar de copiar ahí')
            ->link()
            ->color('gray')
            ->size(Size::Small)
            ->visible(fn () => self::canDownload())
            ->requiresConfirmation()
            ->modalHeading('Dejar de guardar la copia extra')
            ->modalDescription('Las copias que ya están en esa carpeta se quedan ahí. Desde ahora, la copia de cada día queda solo aquí.')
            ->modalSubmitActionLabel('Dejar de copiar')
            ->action(function (): void {
                Setting::set(ExtraCopy::FOLDER_SETTING, null);

                Notification::make()->title('Ya no se guarda la copia extra')->success()->send();
            });
    }

    /** Cualquier administrador puede reintentar en la misma carpeta, por ejemplo después de conectar el USB. */
    public function retryExtraCopyAction(): Action
    {
        return Action::make('retryExtraCopy')
            ->label('Probar de nuevo')
            ->icon(Heroicon::OutlinedArrowPath)
            ->button()
            ->color('gray')
            ->size(Size::Small)
            ->action(function (ExtraCopy $extra): void {
                if ($extra->copyLatest()) {
                    Notification::make()->title('Listo: la copia ya está también en la otra carpeta')->success()->send();

                    return;
                }

                Notification::make()->title('Todavía no se puede guardar ahí')->body($extra->problem())->danger()->send();
            });
    }

    /**
     * La copia extra para la pantalla: la carpeta, si falló y, si salió, "Copia de hoy guardada también en …".
     *
     * @return array{folder: string|null, keep: int, problem: string|null, saved: string|null}
     */
    private static function extraCopy(): array
    {
        $extra = app(ExtraCopy::class);
        $latest = app(DatabaseBackup::class)->latestAt();
        $savedAt = $extra->lastSavedAt();

        return [
            'folder' => $extra->folder(),
            'keep' => $extra->keep(),
            'problem' => $extra->problem(),
            'saved' => match (true) {
                $latest === null || $savedAt === null => null,
                ! $extra->hasTheLatest() => 'La última se guardó '.$savedAt->diffForHumans().' en',
                $latest->isToday() => 'Copia de hoy guardada también en',
                $latest->isYesterday() => 'Copia de ayer guardada también en',
                default => 'Copia del '.$latest->translatedFormat('j \d\e F').' guardada también en',
            },
        ];
    }

    /**
     * Para el administrador, al pie del menú: "Respaldo de hoy ✓" y cuándo se bajó la última copia fuera del hosting.
     * En ámbar si toca bajarla, en rojo si la copia diaria dejó de hacerse. Sin la palabra "cifrado": las copias
     * todavía no lo están.
     *
     * @return array{tone: 'success'|'warning'|'danger', text: string, detail: string, url: string}|null
     */
    public static function sidebarStatus(): ?array
    {
        if (! self::canAccess()) {
            return null;
        }

        $database = app(DatabaseBackup::class);
        $copies = app(OffsiteCopies::class);
        $latest = $database->latestAt();
        $downloaded = $copies->lastDatabaseDownload();
        $due = $copies->due();

        return [
            'tone' => match (true) {
                $database->isStale() => 'danger',
                $due !== null => 'warning',
                default => 'success',
            },
            'text' => match (true) {
                $latest === null => 'Todavía no hay respaldo',
                $database->isStale() => 'Respaldo atrasado: el último es de '.$latest->diffForHumans(),
                $latest->isToday() => 'Respaldo de hoy ✓',
                default => 'Respaldo de ayer ✓',
            },
            'detail' => match (true) {
                // La copia extra en otra carpeta la guarda fuera cada día, sin descargar nada.
                app(ExtraCopy::class)->lastSavedAt()?->isToday() === true => 'Copia de hoy guardada también en otra carpeta',
                $downloaded === null => 'Aún no se bajó una copia fuera del hosting',
                default => 'Copia fuera del hosting '.$downloaded['at']->diffForHumans(),
            },
            'url' => self::getUrl(),
        ];
    }

    /**
     * Por qué falta la copia de las últimas horas y qué hacer. Casi siempre es que las tareas automáticas de la
     * computadora están apagadas; si están corriendo, la copia falló por otra cosa y toca crearla a mano.
     */
    private static function missedBackup(bool $noneYet): string
    {
        $tasks = app(ScheduledTasks::class);

        if ($tasks->areRunning()) {
            return 'Las tareas automáticas están corriendo, pero la copia automática no se creó. Toca «Crear copia ahora»: si sale un error, avísale a quien instaló el sistema.';
        }

        return ($noneYet ? 'Todavía no se creó la primera copia automática.' : 'La copia automática de anoche no corrió.')
            .' Revisa que las tareas automáticas '.$tasks->ofMachine().' estén encendidas. '.$tasks->howToTurnOn()
            .' Mientras tanto, toca «Crear copia ahora».';
    }

    /** "Hay 4 copias de más: las más viejas se borran al crear la próxima copia." */
    private static function extraCopies(int $extra): string
    {
        return ($extra === 1 ? 'Hay 1 copia de más: la más vieja se borra' : "Hay {$extra} copias de más: las más viejas se borran").' al crear la próxima copia.';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $service = app(DatabaseBackup::class);
        $copies = app(OffsiteCopies::class);
        $list = $service->list();
        $downloads = $copies->lastDownloadOfEach(array_column($list, 'file'));
        $backups = collect($list)->map(fn (array $backup) => [
            'file' => $backup['file'],
            'date' => ucfirst($backup['created_at']->translatedFormat('l j \d\e F \d\e Y')).', '.Dates::time($backup['created_at']),
            'ago' => $backup['created_at']->diffForHumans(),
            'size' => Number::fileSize($backup['size'], precision: 1),
            'downloaded' => isset($downloads[$backup['file']])
                ? 'La descargó '.($downloads[$backup['file']]['by'] ?? 'alguien').' '.$downloads[$backup['file']]['at']->diffForHumans()
                : null,
            'url' => Filament::getPanel('admin')->route('backups.download', ['file' => $backup['file']]),
        ]);

        $files = app(FilesBackup::class);
        $databaseDownload = $copies->lastDatabaseDownload();
        $lastFiles = $files->last();

        return [
            'canDownload' => self::canDownload(),
            'whoDownloads' => self::whoDownloads(),
            'backups' => $backups,
            'extraCopy' => self::extraCopy(),
            'stale' => $service->isStale(),
            'missedBackup' => self::missedBackup($list === []),
            'keep' => $keep = $service->keep(),
            'extra' => count($list) > $keep ? self::extraCopies(count($list) - $keep) : null,
            'due' => $copies->due(),
            'databaseDownloaded' => $databaseDownload ? self::when($databaseDownload['at'], $databaseDownload['by']) : null,
            'pending' => self::batch(FilesBackup::PENDING, $files->pending()),
            'last' => $lastFiles ? [...self::batch(FilesBackup::LAST, $lastFiles), 'when' => self::when($lastFiles['at'], $lastFiles['by'])] : null,
            // Mientras no se haya bajado nada, "lo nuevo" ya es todo.
            'everything' => $lastFiles ? self::batch(FilesBackup::EVERYTHING, $files->everything()) : null,
        ];
    }

    /** A quién pedirle la descarga: "Ana Torres" o "Ana Torres y Luis Paz"; null si hoy nadie puede bajarlas. */
    private static function whoDownloads(): ?string
    {
        $names = User::query()
            ->where('role', UserRole::Admin)
            ->where('is_dentist', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return $names === [] ? null : Arr::join($names, ', ', ' y ');
    }

    /** "viernes 23 de octubre, 3:00 a. m.", del nombre del archivo de la copia. */
    private static function dayOf(string $file): string
    {
        if (! DatabaseBackup::isBackupName($file)) {
            return 'día elegido';
        }

        $created = CarbonImmutable::createFromFormat('Y-m-d_His', substr($file, 9, 17));

        return $created->translatedFormat('l j \d\e F').', '.Dates::time($created);
    }

    /**
     * @param  array{files: int, bytes: int, parts: list<array{number: int, files: int, bytes: int, done: bool}>}  $batch
     * @return array{summary: string, parts: list<array{number: int, of: int, summary: string, done: bool, url: string}>}
     */
    private static function batch(string $which, array $batch): array
    {
        return [
            'summary' => self::amount($batch['files'], $batch['bytes']),
            'parts' => array_map(fn (array $part) => [
                'number' => $part['number'],
                'of' => count($batch['parts']),
                'summary' => self::amount($part['files'], $part['bytes']),
                'done' => $part['done'],
                'url' => Filament::getPanel('admin')->route('backups.files.download', ['batch' => $which, 'part' => $part['number']]),
            ], $batch['parts']),
        ];
    }

    private static function amount(int $files, int $bytes): string
    {
        return ($files === 1 ? '1 archivo' : Number::format($files).' archivos').' · '.Number::fileSize($bytes, precision: 1);
    }

    private static function when(CarbonImmutable $at, ?string $by): string
    {
        return $at->diffForHumans().', el '.$at->translatedFormat('l j \d\e F').($by ? ', por '.$by : '');
    }
}
