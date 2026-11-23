<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Forms\WebPhotoUpload;
use App\Filament\Forms\WeeklySchedule;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Services\Agenda\DoctorCalendarFeed;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * "Mi perfil". Además de su nombre, correo y contraseña, cada doctor cambia aquí cómo sale en la página web
 * (foto, presentación y formación), ve su horario de atención, que lo cambia el administrador, y saca el enlace
 * para ver su agenda en el calendario del celular.
 */
class EditProfile extends BaseEditProfile
{
    /** Lo que cada doctor cambia de su propia ficha. Lo demás (especialidad, colegiatura, horario) es del administrador. */
    private const OWN = ['photo_path', 'bio', 'education'];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
                Section::make('Cómo sales en la página web')
                    ->description('Tu foto, tu presentación y tu formación, como las ven los pacientes en la web.')
                    ->statePath('doctor')
                    ->visible(fn () => $this->doctor() !== null)
                    ->schema([
                        WebPhotoUpload::make('photo_path', 'equipo')
                            ->label('Foto')
                            ->avatar()
                            ->imageEditor()
                            ->circleCropper()
                            ->helperText('De frente, con buena luz. Si no hay foto, la web muestra tus iniciales.'),
                        Textarea::make('bio')
                            ->label('Presentación')
                            ->rows(4)
                            ->helperText('En primera persona y cercana: a quién atiendes y cómo trabajas.'),
                        Textarea::make('education')
                            ->label('Formación')
                            ->rows(3)
                            ->placeholder("Cirujano dentista, Universidad …\nEspecialidad en …")
                            ->helperText('Una línea por estudio.'),
                    ]),
                Section::make('Tu horario de atención')
                    ->description('Lo cambia el administrador, en Configuración › Doctores. Si no es el que haces, avísale.')
                    ->inlineLabel(false)
                    ->visible(fn () => $this->doctor() !== null)
                    ->schema([
                        TextEntry::make('schedule')
                            ->hiddenLabel()
                            ->state(fn () => $this->scheduleInWords())
                            ->listWithLineBreaks()
                            ->placeholder('Todavía no tienes horario: no se te pueden dar citas.'),
                    ]),
                $this->calendarSection(),
            ]);
    }

    /**
     * "Ver mi agenda en el calendario del celular": el enlace secreto para suscribirse, cómo agregarlo y el botón
     * para cambiarlo. Las citas llegan con la hora, el tratamiento y el nombre corto del paciente; nada clínico.
     */
    private function calendarSection(): Section
    {
        return Section::make('Ver mi agenda en el calendario del celular')
            ->description('Tus citas aparecen solas en el calendario de tu celular, con la hora, el tratamiento y el nombre del paciente. Sin datos clínicos ni celulares.')
            ->inlineLabel(false)
            // Quien ya no atiende (su ficha de doctor está desactivada) no tiene agenda: el enlace no abriría nada.
            ->visible(fn () => (bool) $this->doctor()?->is_active)
            ->schema([
                TextEntry::make('calendar_link')
                    ->label('Tu enlace')
                    ->state(fn () => $this->calendarUrl())
                    ->copyable()
                    ->copyMessage('Enlace copiado')
                    ->placeholder('Todavía no tienes enlace. Créalo y agrégalo una sola vez en tu celular.')
                    ->helperText(fn () => $this->calendarUrl() !== null ? 'Es solo para ti: quien lo tenga ve tus citas. Si lo compartiste por error, cámbialo.' : null),
                TextEntry::make('calendar_only_here')
                    ->hiddenLabel()
                    ->state('Este enlace solo funciona en esta computadora. Para el celular, abre el panel con su dirección de internet y copia el enlace de ahí.')
                    ->color('warning')
                    ->visible(fn () => $this->calendarUrl() !== null && self::isOnlyOnThisComputer($this->calendarUrl())),
                TextEntry::make('calendar_steps')
                    ->hiddenLabel()
                    ->state([
                        'iPhone: abre esta página en el iPhone y toca «Agregar al iPhone». O ve a Ajustes › Calendario › Cuentas › Añadir cuenta › Otra › Añadir calendario suscrito y pega el enlace.',
                        'Android (Google Calendar): en una computadora, entra a calendar.google.com › Otros calendarios › + › Desde URL, pega el enlace y toca «Añadir calendario». Luego aparece en tu celular.',
                        'Los cambios llegan solos, pero no al instante: el iPhone revisa cada hora y Google, cada varias horas.',
                    ])
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->visible(fn () => $this->calendarUrl() !== null),
                Actions::make([
                    Action::make('createCalendarLink')
                        ->label('Crear mi enlace')
                        ->icon(Heroicon::OutlinedLink)
                        ->visible(fn () => $this->calendarUrl() === null)
                        ->action(function (): void {
                            app(DoctorCalendarFeed::class)->renew($this->doctor());

                            Notification::make()->success()->title('Enlace creado')->body('Cópialo y agrégalo en tu celular.')->send();
                        }),
                    Action::make('addToIphone')
                        ->label('Agregar al iPhone')
                        ->icon(Heroicon::OutlinedDevicePhoneMobile)
                        ->color('gray')
                        ->url(fn () => preg_replace('#^https?://#', 'webcal://', (string) $this->calendarUrl()))
                        ->visible(fn () => $this->calendarUrl() !== null),
                    Action::make('changeCalendarLink')
                        ->label('Cambiar el enlace')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('¿Cambiar el enlace?')
                        ->modalDescription('El enlace de ahora deja de funcionar al instante: tu celular ya no verá las citas hasta que agregues el nuevo.')
                        ->modalSubmitActionLabel('Cambiar el enlace')
                        ->visible(fn () => $this->calendarUrl() !== null)
                        ->action(function (): void {
                            app(DoctorCalendarFeed::class)->renew($this->doctor());

                            Notification::make()->success()->title('Enlace cambiado')->body('El anterior ya no funciona. Agrega el nuevo en tu celular.')->send();
                        }),
                ])->key('calendarActions'),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'doctor' => $this->doctor()?->only(self::OWN)];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $own = Arr::pull($data, 'doctor');

        if (is_array($own)) {
            $this->doctor()?->update(Arr::only($own, self::OWN));
        }

        return parent::handleRecordUpdate($record, $data);
    }

    /** La ficha de doctor de quien entró: la tienen los doctores y el administrador que también atiende. */
    private function doctor(): ?Doctor
    {
        return once(fn () => Doctor::query()->where('user_id', $this->getUser()->getKey())->first());
    }

    /** El enlace de su agenda para el celular, o null si todavía no lo creó. */
    private function calendarUrl(): ?string
    {
        $doctor = $this->doctor();

        return $doctor ? app(DoctorCalendarFeed::class)->url($doctor) : null;
    }

    /** Con el panel abierto en la misma computadora (localhost), el enlace apunta a ella: el celular no llega ahí. */
    private static function isOnlyOnThisComputer(string $url): bool
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true) || Str::endsWith($host, ['.localhost', '.test']);
    }

    /** @return list<string> "Lunes: 09:00–13:00 y 15:00–19:00", un renglón por día. */
    private function scheduleInWords(): array
    {
        return $this->doctor()?->schedules()->orderBy('weekday')->orderBy('starts_at')->get()
            ->groupBy('weekday')
            ->map(fn (Collection $ranges, int $weekday) => (WeeklySchedule::DAYS[$weekday] ?? 'Día').': '.$ranges->map(self::rangeInWords(...))->join(', ', ' y '))
            ->values()
            ->all() ?? [];
    }

    private static function rangeInWords(DoctorSchedule $range): string
    {
        $season = WeeklySchedule::seasonInWords($range->valid_from, $range->valid_until);

        return substr($range->starts_at, 0, 5).'–'.substr($range->ends_at, 0, 5).($season ? ' ('.mb_strtolower($season).')' : '');
    }
}
