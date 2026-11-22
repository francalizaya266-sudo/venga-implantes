<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Enums\AppointmentOrigin;
use App\Enums\DocumentType;
use App\Enums\PatientRelationship;
use App\Enums\Sex;
use App\Exceptions\DniLookupException;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Support\PageLayout;
use App\Models\Account;
use App\Models\Patient;
use App\Services\Patients\DniLookup;
use App\Services\Patients\PatientRegistrar;
use App\Support\Phone;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Unique;

/**
 * La ficha del paciente para registrarlo o corregirlo. Al registrar, el documento va primero (con un DNI, RENIEC trae
 * los nombres); a la derecha se ve al instante si ya está registrado, a qué familia va y si necesita apoderado; lo
 * que casi nunca se llena (correo, dirección, emergencia) queda plegado abajo. Al corregir, esos datos van a la
 * derecha, abiertos.
 */
class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        if ($schema->getOperation() !== 'create') {
            return $schema
                ->columns(PageLayout::COLUMNS)
                ->components([
                    Group::make([self::mainSection(), self::guardianSection()])->columnSpan(PageLayout::MAIN),
                    self::extraSection(['default' => 1, '@6xl' => 2])->columnSpan(PageLayout::SIDE),
                ]);
        }

        // Una búsqueda de parecidos por cada cambio, compartida por la revisión y la pregunta "¿es él?".
        $candidates = self::duplicatesFinder();

        return $schema
            ->columns(PageLayout::COLUMNS)
            ->components([
                Group::make([
                    self::mainSection(),
                    self::guardianSection(),
                    self::extraSection(2)->collapsed(),
                ])->columnSpan(PageLayout::MAIN),
                self::reviewSection($candidates)->columnSpan(PageLayout::SIDE),
            ]);
    }

    /** Lo indispensable para abrir una ficha, en el orden en que se pide en el mostrador: primero el documento. */
    private static function mainSection(): Section
    {
        return Section::make('Datos del paciente')
            ->columns(2)
            ->schema([
                // El número, con "Buscar en RENIEC" al lado, se lleva más espacio que el tipo.
                Grid::make(['default' => 1, 'sm' => 5])
                    ->columnSpanFull()
                    ->schema([
                        Select::make('document_type')
                            ->label('Documento')
                            ->options(DocumentType::class)
                            ->default(DocumentType::Dni->value)
                            ->live()
                            ->columnSpan(['sm' => 2]),
                        self::documentNumberInput()
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('document_type', $get('document_type') instanceof DocumentType ? $get('document_type')->value : $get('document_type')))
                            ->validationMessages(['unique' => 'Ya hay un paciente con este documento.'])
                            ->columnSpan(['sm' => 3]),
                    ]),
                TextInput::make('first_name')
                    ->label('Nombres')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true),
                TextInput::make('last_name')
                    ->label('Apellidos')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true),
                self::birthDateInput(),
                ToggleButtons::make('sex')
                    ->label('Sexo')
                    ->options(Sex::class)
                    ->inline()
                    ->grouped(),
                self::phoneInput()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::syncRelationshipWithPhone($get, $set))
                    ->visibleOn('create'),
                // Se corrige después si se registró mal (un niño que quedó como "Titular" del celular de su mamá).
                self::relationshipSelect()
                    ->visible(fn (string $operation, ?Patient $record) => $operation === 'create' || $record?->account_id !== null),
            ]);
    }

    /** Para un menor: quién firma por él los consentimientos y el plan. */
    private static function guardianSection(): Section
    {
        return Section::make('Apoderado')
            ->description('Es menor de edad: su apoderado firma los consentimientos y el plan de tratamiento.')
            ->visible(fn (Get $get) => (self::ageOf($get('birth_date')) ?? 18) < 18)
            ->schema([
                TextInput::make('guardian_name')
                    ->label('Nombre completo')
                    ->maxLength(150)
                    ->placeholder(fn (?Patient $record) => self::holderOf($record)?->full_name),
                TextInput::make('guardian_document')
                    ->label('DNI u otro documento')
                    ->maxLength(20)
                    ->dehydrateStateUsing(fn (?string $state) => DocumentType::clean($state)),
                Select::make('guardian_relationship')
                    ->label('Es su')
                    ->options([
                        PatientRelationship::Parent->value => 'Padre / madre',
                        PatientRelationship::Grandparent->value => 'Abuelo(a)',
                        PatientRelationship::Sibling->value => 'Hermano(a)',
                        PatientRelationship::Other->value => 'Otro (tutor)',
                    ])
                    ->default(PatientRelationship::Parent->value),
            ]);
    }

    /**
     * Lo que casi nunca se llena al registrar. Si al guardar algo de aquí está mal, la sección se abre sola.
     *
     * @param  array<string, int>|int  $columns
     */
    private static function extraSection(array|int $columns): Section
    {
        return Section::make('Datos adicionales')
            ->description('Correo, dirección, ocupación, contacto de emergencia, cómo nos encontró y notas.')
            ->columns($columns)
            ->collapsible()
            ->schema([
                TextInput::make('email')
                    ->label('Correo')
                    ->email(),
                TextInput::make('occupation')
                    ->label('Ocupación'),
                TextInput::make('address')
                    ->label('Dirección'),
                TextInput::make('district')
                    ->label('Distrito'),
                TextInput::make('emergency_contact_name')
                    ->label('Contacto de emergencia'),
                TextInput::make('emergency_contact_phone')
                    ->label('Celular de emergencia')
                    ->tel(),
                Select::make('origin')
                    ->label('¿Cómo nos encontró?')
                    ->options(AppointmentOrigin::class),
                Textarea::make('admin_notes')
                    ->label('Notas internas (no clínicas)')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * A la derecha, mientras se escribe: si ya está registrado (y entonces hay que elegir si es él), a qué familia va
     * por su celular y si necesita apoderado.
     *
     * @param  Closure(Get): Collection<int, array{patient: Patient, reason: string}>  $candidates
     */
    private static function reviewSection(Closure $candidates): Section
    {
        return Section::make('Revisión')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->description('Antes de crear: que no esté repetido y a qué familia va.')
            ->schema([
                View::make('filament.patients.registration-check')
                    ->viewData(fn (Get $get) => ['rows' => self::reviewRows($get, $candidates($get))]),
                self::duplicatesCheck(candidates: $candidates),
            ]);
    }

    /**
     * Las líneas de la revisión: duplicados, familia y apoderado. Si hay parecidos, la pregunta "¿es él?" los
     * muestra y aquí no se repiten.
     *
     * @param  Collection<int, array{patient: Patient, reason: string}>  $found
     * @return list<array{tone: string, text: string}>
     */
    public static function reviewRows(Get $get, Collection $found): array
    {
        $type = self::documentType($get('document_type'));
        $document = DocumentType::clean($get('document_number'));
        $validDocument = filled($document) && ($type->pattern() === null || preg_match($type->pattern(), $document));
        $typedSomething = $validDocument || (filled($get('first_name')) && filled($get('last_name')));
        $available = $type->getLabel().' '.$document.' disponible';
        $age = self::ageOf($get('birth_date'));

        return array_values(array_filter([
            match (true) {
                $found->contains('reason', 'mismo documento') => null,
                // Los parecidos los muestra la pregunta "¿es él?"; el documento igual está libre.
                $found->isNotEmpty() => $validDocument ? ['tone' => 'success', 'text' => $available] : null,
                $typedSomething => ['tone' => 'success', 'text' => 'Sin duplicados'.($validDocument ? ' · '.$available : '')],
                default => ['tone' => 'gray', 'text' => 'Al escribir el documento o el nombre, aquí ves si ya está registrado.'],
            },
            self::familyRow($get('phone')),
            match (true) {
                $age === null => null,
                $age < 18 => ['tone' => 'warning', 'text' => 'Es menor de edad: llena los datos del apoderado.'],
                default => ['tone' => 'success', 'text' => 'Mayor de edad: no necesita apoderado.'],
            },
        ]));
    }

    /**
     * A qué familia va por su celular: la del titular si el número ya está registrado.
     *
     * @return array{tone: string, text: string}
     */
    private static function familyRow(mixed $phone): array
    {
        if (blank($phone) || ! Phone::isValid((string) $phone)) {
            return ['tone' => 'gray', 'text' => 'Sin celular todavía: con él le llegan los recordatorios por WhatsApp.'];
        }

        $holder = self::holderOfPhone((string) $phone);

        if ($holder !== null) {
            return ['tone' => 'info', 'text' => 'Quedará en la familia de '.$holder->full_name.', titular de este celular.'];
        }

        return Account::query()->where('phone', Phone::normalize((string) $phone))->whereHas('patients')->exists()
            ? ['tone' => 'info', 'text' => 'Este celular ya es de una familia registrada: quedarán agrupados.']
            : ['tone' => 'success', 'text' => 'Celular nuevo: será el titular.'];
    }

    /** Edad en años según la fecha de nacimiento escrita; null si todavía no hay una fecha válida. */
    private static function ageOf(mixed $birthDate): ?int
    {
        try {
            $date = filled($birthDate) ? Carbon::parse($birthDate) : null;
        } catch (InvalidFormatException) {
            return null;
        }

        return $date === null || $date->isFuture() ? null : (int) $date->age;
    }

    /** El titular del celular del paciente, si no es él mismo: suele ser quien lo trae. */
    private static function holderOf(?Patient $patient): ?Patient
    {
        return $patient?->account_id && $patient->relationship !== PatientRelationship::Self
            ? Patient::query()->where('account_id', $patient->account_id)->where('relationship', PatientRelationship::Self)->first()
            : null;
    }

    /**
     * Parentesco con el titular del celular. Un celular tiene un solo titular: con un número nuevo queda "Titular";
     * si el número ya tiene titular, se pide el parentesco con esa persona (así un niño no queda como "Titular" del
     * celular de su mamá) y lo dice debajo, antes de guardar.
     *
     * @param  bool  $withHint  Con la ayuda '"Titular" si el celular es suyo' cuando el número no tiene titular.
     */
    public static function relationshipSelect(bool $withHint = true): Select
    {
        // Un titular por cada cambio de los datos, no uno por cada parte del campo.
        $holders = [];
        $holder = function (Get $get, ?Model $record) use (&$holders): ?Patient {
            $phone = $get('phone');
            $key = (string) $phone.'|'.($record instanceof Patient ? $record->getKey() : '');

            if (! array_key_exists($key, $holders)) {
                $holders[$key] = filled($phone) && Phone::isValid((string) $phone)
                    ? self::holderOfPhone((string) $phone, $record instanceof Patient ? $record : null)
                    : ($record instanceof Patient && $record->account_id ? self::holderInAccount($record->account_id, $record) : null);
            }

            return $holders[$key];
        };

        return Select::make('relationship')
            ->label('Parentesco con el titular del celular')
            ->options(PatientRelationship::class)
            ->default(PatientRelationship::Self->value)
            ->required(fn (Get $get, ?Model $record) => $holder($get, $record) !== null)
            ->validationMessages(['required' => 'Elige qué es del titular del celular.'])
            ->helperText(fn (Get $get, ?Model $record) => ($found = $holder($get, $record))
                ? 'Este celular es de '.$found->full_name.': elige qué es de esa persona.'
                : ($withHint ? '"Titular" si el celular es suyo.' : null))
            ->rule(fn (Get $get, ?Model $record) => function (string $attribute, mixed $value, Closure $fail) use ($get, $record, $holder) {
                $found = self::relationshipOf($value) === PatientRelationship::Self ? $holder($get, $record) : null;

                if ($found !== null) {
                    $fail('Este celular ya es de '.$found->full_name.': elige el parentesco con esa persona.');
                }
            });
    }

    /**
     * Al escribir el celular, el parentesco se acomoda: si el número ya tiene titular, "Titular" se quita para que se
     * elija el parentesco; si es un número nuevo y no se eligió nada, queda "Titular".
     */
    public static function syncRelationshipWithPhone(Get $get, Set $set): void
    {
        $phone = (string) $get('phone');
        $relationship = self::relationshipOf($get('relationship'));
        $holder = Phone::isValid($phone) ? self::holderOfPhone($phone) : null;

        if ($holder !== null && $relationship === PatientRelationship::Self) {
            $set('relationship', null);
        } elseif ($holder === null && $relationship === null) {
            $set('relationship', PatientRelationship::Self->value);
        }
    }

    /** El titular de un celular ya registrado (sin contar a $except, el paciente que se está corrigiendo). */
    private static function holderOfPhone(string $phone, ?Patient $except = null): ?Patient
    {
        $accountId = Account::query()->where('phone', Phone::normalize($phone))->value('id');

        return $accountId === null ? null : self::holderInAccount((int) $accountId, $except);
    }

    private static function holderInAccount(int $accountId, ?Patient $except = null): ?Patient
    {
        return Patient::query()
            ->where('account_id', $accountId)
            ->where('relationship', PatientRelationship::Self)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->first();
    }

    private static function relationshipOf(mixed $value): ?PatientRelationship
    {
        return $value instanceof PatientRelationship ? $value : PatientRelationship::tryFrom((string) $value);
    }

    /**
     * Fecha de nacimiento, con la fecha en palabras y la edad debajo. El navegador la muestra como día/mes o
     * mes/día según su idioma: así se ve al instante si se escribió al revés.
     */
    public static function birthDateInput(): DatePicker
    {
        return DatePicker::make('birth_date')
            ->label('Fecha de nacimiento')
            ->maxDate(today())
            ->live()
            ->helperText(fn (mixed $state) => self::birthDateInWords($state));
    }

    /** "12 de abril de 1985 · 41 años"; nada si aún no hay fecha o no es una fecha que ya pasó. */
    public static function birthDateInWords(mixed $state): ?string
    {
        try {
            $date = filled($state) ? Carbon::parse($state) : null;
        } catch (InvalidFormatException) {
            $date = null;
        }

        if ($date === null || $date->isFuture()) {
            return null;
        }

        $age = $date->age;

        return $date->translatedFormat('j \d\e F \d\e Y').' · '.match (true) {
            $age >= 2 => $age.' años',
            $age === 1 => '1 año',
            default => 'menos de un año',
        };
    }

    /**
     * Número de documento: se guarda sin espacios ni guiones y se revisa según el tipo (el DNI tiene 8 dígitos).
     * Con un DNI, la lupa trae de RENIEC los nombres y apellidos.
     */
    public static function documentNumberInput(string $typeField = 'document_type'): TextInput
    {
        return TextInput::make('document_number')
            ->label('Número de documento')
            ->maxLength(20)
            ->live(onBlur: true)
            ->mutateStateForValidationUsing(fn (?string $state) => DocumentType::clean($state))
            ->dehydrateStateUsing(fn (?string $state) => DocumentType::clean($state))
            ->rule(fn (Get $get) => function (string $attribute, mixed $value, Closure $fail) use ($get, $typeField) {
                $type = self::documentType($get($typeField));

                if (filled($value) && $type->pattern() !== null && ! preg_match($type->pattern(), (string) $value)) {
                    $fail($type->invalidMessage());
                }
            })
            ->afterContent(self::dniLookupAction($typeField));
    }

    /**
     * "Buscar en RENIEC", al lado del DNI y con su nombre (una lupa sola se pasaba por alto): pone los nombres y
     * apellidos como están en RENIEC, sin escribirlos (ni escribirlos mal en la historia clínica). Solo con DNI y
     * con la clave de la consulta puesta; si no, ni aparece.
     */
    private static function dniLookupAction(string $typeField): Action
    {
        return Action::make('lookupDni')
            ->label('Buscar en RENIEC')
            ->tooltip('Trae los nombres y apellidos como están en RENIEC')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->button()
            ->extraAttributes(['class' => 'reniec-button'])
            ->visible(fn (Get $get) => self::documentType($get($typeField)) === DocumentType::Dni && app(DniLookup::class)->isEnabled())
            ->action(function (Get $get, Set $set): void {
                $dni = (string) DocumentType::clean($get('document_number'));

                if (! preg_match((string) DocumentType::Dni->pattern(), $dni)) {
                    Notification::make()->warning()->title('Escribe primero el DNI completo: tiene 8 dígitos.')->send();

                    return;
                }

                try {
                    $person = app(DniLookup::class)->find($dni);
                } catch (DniLookupException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                if ($person === null) {
                    Notification::make()->warning()->title("RENIEC no tiene el DNI {$dni}.")->body('Revisa el número o escribe los nombres a mano.')->send();

                    return;
                }

                $set('first_name', $person['first_name']);
                $set('last_name', $person['last_name']);

                Notification::make()->success()->title("{$person['first_name']} {$person['last_name']}")->body('Nombres y apellidos puestos como están en RENIEC.')->send();
            });
    }

    /** El tipo elegido (o DNI si todavía no se eligió). */
    private static function documentType(mixed $type): DocumentType
    {
        return $type instanceof DocumentType ? $type : (DocumentType::tryFrom((string) $type) ?? DocumentType::Dni);
    }

    /**
     * Los pacientes que podrían ser el que se está registrando, buscados una vez por cada cambio de los datos (no
     * una vez por cada parte del campo que los muestra).
     *
     * @param  string  $path  Dónde están los datos del paciente ('' o '../patient.' por ejemplo).
     * @return Closure(Get): Collection<int, array{patient: Patient, reason: string}>
     */
    public static function duplicatesFinder(string $path = ''): Closure
    {
        $found = [];

        return function (Get $get) use ($path, &$found): Collection {
            $data = collect(['first_name', 'last_name', 'birth_date', 'document_number', 'phone'])
                ->mapWithKeys(fn (string $field) => [$field => $get($path.$field)])
                ->all();

            return $found[md5(serialize($data))] ??= app(PatientRegistrar::class)->possibleDuplicates($data);
        };
    }

    /**
     * Mientras se escribe el nombre, el documento, la fecha de nacimiento o el celular, los pacientes que ya
     * están y podrían ser el mismo. Un paciente repetido parte su historia en dos: hay que elegir si es uno de
     * ellos o es otra persona. Al elegir uno, $onPick decide qué hacer (abrir su ficha, usarlo en la cita).
     *
     * @param  string  $path  Dónde están los datos del paciente respecto de este campo ('' o '../patient.' por ejemplo).
     * @param  Closure(int, Set, mixed): void|null  $onPick
     * @param  (Closure(Get): Collection<int, array{patient: Patient, reason: string}>)|null  $candidates  La búsqueda, si otro campo la comparte (duplicatesFinder).
     */
    public static function duplicatesCheck(string $path = '', ?Closure $onPick = null, ?Closure $candidates = null): Radio
    {
        $candidates ??= self::duplicatesFinder($path);

        return Radio::make('same_as')
            ->label(function (Get $get) use ($candidates) {
                $found = $candidates($get);

                return match (true) {
                    $found->contains('reason', 'mismo documento') => 'Ya hay un paciente con este documento: ¿es él?',
                    $found->every(fn (array $row) => $row['reason'] === 'mismo celular') => 'Este celular ya tiene a '.Arr::join($found->map(fn (array $row) => $row['patient']->first_name)->all(), ', ', ' y ').': ¿es uno de ellos?',
                    default => '¿Ya está registrado? Encontramos pacientes parecidos',
                };
            })
            ->helperText('Un paciente repetido parte su historia en dos.')
            ->options(fn (Get $get) => $candidates($get)
                ->mapWithKeys(fn (array $row) => [$row['patient']->getKey() => 'Es '.$row['patient']->full_name])
                ->put('new', 'No, es otra persona')
                ->all())
            ->descriptions(fn (Get $get) => $candidates($get)
                ->mapWithKeys(fn (array $row) => [$row['patient']->getKey() => collect([
                    $row['patient']->document_number ? $row['patient']->document_type?->getLabel().' '.$row['patient']->document_number : null,
                    $row['patient']->age !== null ? $row['patient']->age.' años' : null,
                    $row['patient']->account ? Phone::display($row['patient']->account->phone) : null,
                    $row['patient']->relationship && $row['reason'] === 'mismo celular' ? $row['patient']->relationship->getLabel() : null,
                    ucfirst($row['reason']),
                ])->filter()->implode(' · ')])
                ->all())
            ->visible(fn (Get $get) => $candidates($get)->isNotEmpty())
            ->required()
            ->validationMessages(['required' => 'Elige si es uno de ellos o es otra persona.'])
            ->live()
            ->afterStateUpdated(function (mixed $state, Set $set, $livewire) use ($onPick) {
                if (filled($state) && $state !== 'new') {
                    $onPick
                        ? $onPick((int) $state, $set, $livewire)
                        : $livewire->redirect(PatientResource::getUrl('view', ['record' => $state]));
                }
            })
            ->dehydrated(false)
            ->columnSpanFull();
    }

    /** Celular peruano; el de un menor o familiar a cargo es el del titular. */
    public static function phoneInput(string $name = 'phone'): TextInput
    {
        return TextInput::make($name)
            ->label('Celular')
            ->tel()
            ->placeholder('987 654 321')
            ->helperText('Si es un familiar a cargo, usa el celular del titular: quedan agrupados como familia.')
            ->rule(fn () => function (string $attribute, $value, $fail) {
                if (filled($value) && ($problem = Phone::problem((string) $value)) !== null) {
                    $fail($problem);
                }
            });
    }
}
