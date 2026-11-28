<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Concerns\HasSettingsMenuGroup;
use App\Filament\Concerns\HasSpanishLabels;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Support\DoubleCheck;
use App\Models\User;
use App\Support\Phone;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;

/** Personas que entran al panel. Nadie se borra: se desactiva para conservar el historial. */
class UserResource extends Resource
{
    use HasSettingsMenuGroup, HasSpanishLabels;

    protected static ?string $slug = 'usuarios';

    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $navigationParentItem = 'Configuración';

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    public static function settingsMenuGroup(): string
    {
        return 'Equipo';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(100),
                TextInput::make('email')
                    ->label('Correo (para entrar)')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                // Los tres roles a la vista, cada uno con lo que puede ver y hacer: antes había que elegir uno para leer
                // su explicación, o probar.
                Radio::make('role')
                    ->label('Rol')
                    // Un doctor se crea en Doctores, con su ficha y su acceso: aquí no se puede volver doctor a nadie
                    // (quedaría sin agenda). Quien ya es doctor conserva su rol.
                    ->options(fn (?User $record) => collect(UserRole::cases())
                        ->reject(fn (UserRole $role) => $role === UserRole::Doctor && ! $record?->isDoctor())
                        ->mapWithKeys(fn (UserRole $role) => [$role->value => $role->getLabel()])
                        ->all())
                    ->descriptions([
                        UserRole::Admin->value => 'Todo el panel: agenda, pacientes, caja, reportes, inventario y Configuración. La historia clínica, solo si además es dentista.',
                        UserRole::Reception->value => 'La agenda de todos los doctores, los pacientes, los avisos, los cobros y la caja. No ve la historia clínica, los reportes ni Configuración.',
                        UserRole::Doctor->value => 'Su agenda, sus pacientes y la historia clínica: atiende, arma planes y receta. No ve la caja del consultorio.',
                    ])
                    ->required()
                    // Nadie se cambia el rol a sí mismo: un administrador podría quedarse fuera de Configuración.
                    ->disabled(fn (?User $record) => self::isMe($record))
                    ->live()
                    ->columnSpanFull(),
                Toggle::make('is_dentist')
                    ->label('Es dentista (puede ver historias clínicas)')
                    ->visible(fn (Get $get) => in_array($get('role'), [UserRole::Admin, UserRole::Admin->value], true)),
                // A este celular va el resumen del día que se arma al cerrar la caja (DaySummary).
                TextInput::make('phone')
                    ->label('Celular')
                    ->tel()
                    ->placeholder('987 654 321')
                    ->maxLength(20)
                    ->helperText('Al cerrar la caja, el resumen del día queda en Avisos para enviárselo por WhatsApp.')
                    ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                        if (filled($value) && ($problem = Phone::problem((string) $value)) !== null) {
                            $fail($problem);
                        }
                    })
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) && Phone::isValid($state) ? Phone::normalize($state) : $state)
                    ->visible(fn (Get $get) => in_array($get('role'), [UserRole::Admin, UserRole::Admin->value], true)),
                Toggle::make('is_active')
                    ->label('Puede entrar al panel')
                    ->default(true)
                    // Ni se quita su propio acceso: si es el único administrador, nadie se lo podría devolver.
                    ->disabled(fn (?User $record) => self::isMe($record))
                    ->helperText(fn (?User $record) => self::isMe($record) ? 'Tu propio acceso y tu rol solo los puede cambiar otra persona que administre el panel.' : null),
                TextInput::make('password')
                    ->label(fn (string $operation) => $operation === 'create' ? 'Contraseña' : 'Nueva contraseña')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->rule(Password::default())
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    // Quien la escribe aquí la conoce: por eso vale solo para entrar una vez.
                    ->helperText(fn (string $operation, ?User $record) => 'Mínimo 8 caracteres, con letras y números.'.match (true) {
                        $operation === 'create' => ' Es solo para su primer ingreso: al entrar, el panel le pide elegir la suya.',
                        self::isMe($record) => ' Déjala vacía para no cambiarla.',
                        default => ' Déjala vacía para no cambiarla. Si le pones una nueva, al entrar el panel le pide elegir la suya.',
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->description(fn (User $record) => $record->email.($record->must_change_password ? ' · Todavía no eligió su contraseña' : '')),
                TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->description(fn (User $record) => $record->isAdmin() && $record->is_dentist ? 'Dentista' : null),
                TextColumn::make('last_login_at')
                    ->label('Último ingreso')
                    ->since()
                    ->placeholder('Nunca'),
                self::doubleCheckColumn(),
                // Con el mismo nombre que en la ficha: en la lista decía "Activo" y en la ficha "Puede entrar al panel".
                ToggleColumn::make('is_active')
                    ->label('Puede entrar al panel')
                    ->disabled(fn (User $record) => self::isMe($record))
                    ->afterStateUpdated(fn (User $record, mixed $state) => Notification::make()
                        ->success()
                        ->title($record->name.($state ? ' ya puede entrar al panel' : ' ya no puede entrar al panel'))
                        ->send()),
            ])
            ->defaultSort('name')
            ->defaultPaginationPageOption(25)
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(fn (array $data, User $record) => self::withTemporaryPassword($data, $record))
                    ->successNotificationTitle('Usuario guardado'),
                ActionGroup::make([
                    self::lostPhoneAction(),
                    self::stopDoubleCheckAction(),
                    self::resumeDoubleCheckAction(),
                    // La misma regla que en el resto de Configuración: se borra solo a quien se registró por error y nunca
                    // entró; a los demás se les quita el acceso.
                    DeleteAction::make()
                        ->authorizationNotification()
                        ->modalDescription('Solo se borra a quien nunca entró al panel. A los demás se les apaga "Puede entrar al panel".')
                        ->successNotificationTitle('Usuario borrado')
                        ->visible(fn (User $record) => ! self::isMe($record)),
                ])->dropdownWidth(Width::TwoExtraSmall),
            ]);
    }

    /** De un vistazo, a quién se le pide el código del celular y quién ya lo activó. */
    private static function doubleCheckColumn(): TextColumn
    {
        return TextColumn::make('double_check')
            ->label('Doble verificación')
            ->state(fn (User $record): string => self::doubleCheckInWords($record))
            ->badge()
            ->color(fn (string $state): string => match ($state) {
                'Activada' => 'success',
                'Sin activar' => 'gray',
                default => 'warning',
            });
    }

    /**
     * Perdió el celular y los códigos de recuperación: al volver a entrar activa la doble verificación de nuevo. Es otra
     * cosa que dejar de pedírsela: aquí se le sigue pidiendo, con otro celular.
     */
    private static function lostPhoneAction(): Action
    {
        return Action::make('resetMfa')
            ->label('Perdió su celular')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => 'Quitar la doble verificación de '.$record->name)
            ->modalDescription('Úsalo solo si perdió el celular y sus códigos de recuperación. La próxima vez que entre la activará de nuevo con su celular.')
            ->visible(fn (User $record) => ! self::isMe($record) && filled($record->getAppAuthenticationSecret()))
            ->action(function (User $record): void {
                $record->saveAppAuthenticationRecoveryCodes(null);
                $record->saveAppAuthenticationSecret(null);

                Notification::make()->success()->title('Doble verificación quitada')->send();
            });
    }

    /**
     * A una persona se le puede dejar de pedir el código del celular: entra solo con su contraseña. También a uno mismo.
     * Se confirma con la contraseña de quien lo hace y queda en el historial de cambios.
     */
    private static function stopDoubleCheckAction(): Action
    {
        return Action::make('stopDoubleCheck')
            ->label(fn (User $record) => self::isMe($record) ? 'No pedirme doble verificación' : 'No pedirle doble verificación')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('danger')
            ->modalIcon(Heroicon::OutlinedLockOpen)
            ->modalHeading(fn (User $record) => self::isMe($record) ? 'Entrar sin doble verificación' : $record->name.' entrará sin doble verificación')
            ->modalDescription(fn (User $record) => (self::isMe($record) ? 'Entrarás solo con tu contraseña' : 'Entrará solo con su contraseña').', sin el código del celular. Quien llegue a saber esa contraseña podrá abrir el panel desde cualquier lugar. Queda anotado en el historial de cambios.')
            ->schema([DoubleCheck::ownPasswordField()])
            ->modalSubmitActionLabel('Dejar de pedirla')
            ->rateLimit(5)
            ->visible(fn (User $record) => DoubleCheck::isAskedOf($record))
            ->action(function (User $record): void {
                $record->forceFill(['skips_double_check' => true])->save();

                Notification::make()
                    ->success()
                    ->title(self::isMe($record) ? 'Ya no se te pide la doble verificación' : 'A '.$record->name.' ya no se le pide la doble verificación')
                    ->body('Para volver a pedirla, este mismo menú.')
                    ->send();
            });
    }

    /** Lo contrario: se le vuelve a pedir. Devuelve una protección, así que basta con confirmarlo. */
    private static function resumeDoubleCheckAction(): Action
    {
        return Action::make('resumeDoubleCheck')
            ->label(fn (User $record) => self::isMe($record) ? 'Pedirme doble verificación' : 'Pedirle doble verificación')
            ->icon(Heroicon::OutlinedLockClosed)
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => self::isMe($record) ? 'Volver a entrar con doble verificación' : $record->name.' volverá a entrar con doble verificación')
            ->modalDescription(fn (User $record) => filled($record->getAppAuthenticationSecret())
                ? 'Desde la próxima vez que entre, además de la contraseña se pide el código del celular.'
                : 'Como todavía no la activó, lo primero que verá es la pantalla para activarla con su celular.')
            ->modalSubmitActionLabel('Volver a pedirla')
            ->visible(fn (User $record) => $record->skips_double_check)
            ->action(function (User $record): void {
                $record->forceFill(['skips_double_check' => false])->save();

                Notification::make()
                    ->success()
                    ->title(self::isMe($record) ? 'Se te vuelve a pedir la doble verificación' : 'A '.$record->name.' se le vuelve a pedir la doble verificación')
                    ->send();
            });
    }

    /** La doble verificación de una persona, como sale en la lista. */
    private static function doubleCheckInWords(User $record): string
    {
        return match (true) {
            ! DoubleCheck::isOnForEveryone() => 'Apagada para todos',
            (bool) $record->skips_double_check => 'No se le pide',
            filled($record->getAppAuthenticationSecret()) => 'Activada',
            default => 'Sin activar',
        };
    }

    /**
     * Una contraseña puesta por otra persona es temporal: quien la recibe elige la suya al entrar. La que cada
     * uno se pone a sí mismo ya es la suya.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withTemporaryPassword(array $data, ?User $record = null): array
    {
        return filled($data['password'] ?? null) && ! self::isMe($record) ? [...$data, 'must_change_password' => true] : $data;
    }

    /** La ficha es la de quien está usando el panel. */
    private static function isMe(?User $record): bool
    {
        return $record !== null && $record->is(auth()->user());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
