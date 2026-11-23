<?php

namespace App\Filament\Imports;

use App\Enums\DocumentType;
use App\Enums\Sex;
use App\Models\Account;
use App\Models\Patient;
use App\Services\Patients\PatientRegistrar;
use App\Support\Phone;
use Closure;
use Filament\Actions\Imports\Downloaders\Contracts\Downloader;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Str;

/**
 * Pacientes desde una lista en Excel o CSV (Pacientes › Importar pacientes), una fila por paciente. Cada fila se revisa
 * como en el alta del panel (nombres y apellidos, DNI de 8 dígitos, celular peruano, fecha de nacimiento, correo) y se
 * guarda con el mismo registro: el celular agrupa a la familia. Quien ya está registrado (mismo DNI, mismo celular y
 * nombre, o mismo nombre y fecha de nacimiento) no se repite: se deja como está y la fila va al archivo de errores, o
 * solo se completan sus datos vacíos, según se elija al importar.
 */
class PatientImporter extends Importer
{
    protected static ?string $model = Patient::class;

    /** Qué hacer con quien ya está registrado: opción del formulario de importación. */
    public const EXISTING = 'existing';

    public const SKIP = 'skip';

    public const FILL = 'fill';

    /** Con esto empieza el motivo de una fila que no se importó porque el paciente ya estaba. */
    public const ALREADY_REGISTERED = 'Ya está registrado';

    /** Lo que se completa de un paciente que ya estaba, solo si lo tiene vacío. */
    private const FILLABLE = ['document_number', 'birth_date', 'sex', 'email', 'admin_notes'];

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('first_name')
                ->label('Nombres')
                ->exampleHeader('Nombres')
                ->requiredMapping()
                ->guess(self::guesses('nombres', 'nombre', 'nombre(s)', 'nombres del paciente', 'nombre del paciente', 'primer nombre', 'first name', 'first_name'))
                ->examples(['Rosa María', 'Mateo'])
                ->castStateUsing(fn (mixed $state): ?string => self::name($state))
                ->rules(['required', 'max:100']),
            ImportColumn::make('last_name')
                ->label('Apellidos')
                ->exampleHeader('Apellidos')
                ->requiredMapping()
                ->guess(self::guesses('apellidos', 'apellido', 'apellido(s)', 'apellidos del paciente', 'apellido paterno', 'primer apellido', 'ap. paterno', 'last name', 'last_name'))
                ->examples(['Huamán Quispe', 'Quispe Huamán'])
                ->castStateUsing(fn (mixed $state): ?string => self::name($state))
                ->rules(['required', 'max:100']),
            ImportColumn::make('second_last_name')
                ->label('Apellido materno (si va aparte)')
                ->exampleHeader('Apellido materno')
                ->guess(self::guesses('apellido materno', 'segundo apellido', 'ap. materno'))
                ->castStateUsing(fn (mixed $state): ?string => self::name($state)),
            ImportColumn::make('document_number')
                ->label('DNI')
                ->exampleHeader('DNI')
                ->guess(self::guesses('dni', 'n° dni', 'nº dni', 'nro dni', 'nro. dni', 'número de dni', 'dni n°', 'documento', 'documento de identidad', 'doc. identidad', 'n° documento', 'nº documento', 'nro documento', 'nro. documento', 'número de documento'))
                ->examples(['45678912', '71234567'])
                ->castStateUsing(fn (mixed $state): ?string => self::dni($state))
                ->rules(['nullable', 'regex:/^\d{8}$/']),
            ImportColumn::make('phone')
                ->label('Celular')
                ->exampleHeader('Celular')
                ->guess(self::guesses('celular', 'cel', 'cel.', 'n° celular', 'nro celular', 'nro. celular', 'número de celular', 'teléfono', 'teléfono celular', 'tel', 'tel.', 'telf', 'telf.', 'móvil', 'whatsapp', 'celular / whatsapp', 'celular/whatsapp', 'phone'))
                ->examples(['987654321', '987654321'])
                ->castStateUsing(fn (mixed $state): ?string => self::text($state))
                ->rules(['nullable', fn (string $attribute, mixed $value, Closure $fail) => filled($value) && ($problem = Phone::problem((string) $value)) !== null ? $fail($problem) : null]),
            ImportColumn::make('birth_date')
                ->label('Fecha de nacimiento')
                ->exampleHeader('Fecha de nacimiento')
                ->guess(self::guesses('fecha de nacimiento', 'fecha nacimiento', 'fecha de nac.', 'fecha nac.', 'fecha nac', 'f. nacimiento', 'f. nac.', 'fec. nac.', 'fec. nacimiento', 'nacimiento', 'cumpleaños'))
                ->examples(['12/04/1985', '03/09/2015'])
                ->castStateUsing(fn (mixed $state): ?string => self::date($state))
                ->rules(['nullable', 'bail', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1900-01-01']),
            ImportColumn::make('email')
                ->label('Correo')
                ->exampleHeader('Correo')
                ->guess(self::guesses('correo', 'correo electrónico', 'email', 'e-mail', 'mail'))
                ->examples(['rosa.huaman@gmail.com', ''])
                ->castStateUsing(fn (mixed $state): ?string => ($text = self::text($state)) !== null ? Str::lower(str_replace(' ', '', $text)) : null)
                ->rules(['nullable', 'email', 'max:255']),
            ImportColumn::make('sex')
                ->label('Sexo')
                ->exampleHeader('Sexo')
                ->guess(self::guesses('sexo', 'género', 'sexo (f/m)', 'f/m'))
                ->examples(['F', 'M'])
                ->castStateUsing(fn (mixed $state): ?string => self::sex($state))
                ->rules(['nullable', 'in:F,M']),
            ImportColumn::make('admin_notes')
                ->label('Notas')
                ->exampleHeader('Notas')
                ->guess(self::guesses('notas', 'nota', 'observaciones', 'observación', 'obs', 'obs.', 'comentarios', 'comentario'))
                ->examples(['Prefiere citas en la tarde', 'Hijo de Rosa María: usa su celular'])
                ->castStateUsing(fn (mixed $state): ?string => self::text($state))
                ->rules(['nullable', 'max:2000']),
        ];
    }

    /** @return list<Radio> */
    public static function getOptionsFormComponents(): array
    {
        return [
            Radio::make(self::EXISTING)
                ->label('Si alguien ya está registrado (mismo DNI, o mismo celular y nombre)')
                ->options([
                    self::SKIP => 'Dejarlo como está y anotarlo en el archivo de errores',
                    self::FILL => 'Completar solo los datos que le falten',
                ])
                ->default(self::SKIP)
                ->required(),
        ];
    }

    /** El apellido materno, si vino en su propia columna, va con los apellidos. */
    public function castData(): void
    {
        parent::castData();

        if (filled($this->data['second_last_name'] ?? null)) {
            $this->data['last_name'] = trim(($this->data['last_name'] ?? '').' '.$this->data['second_last_name']);
        }

        unset($this->data['second_last_name']);
    }

    /**
     * Un paciente nuevo, o el que ya estaba si se eligió completar sus datos. Si ya estaba y se eligió dejarlo como
     * está, la fila no se importa y queda en el archivo de errores con el motivo.
     */
    public function resolveRecord(): Patient
    {
        [$existing, $reason] = $this->alreadyRegistered();

        if ($existing === null) {
            return new Patient;
        }

        if (($this->options[self::EXISTING] ?? self::SKIP) !== self::FILL) {
            throw new RowImportFailedException(self::ALREADY_REGISTERED." ({$reason}): {$existing->full_name}. No se cambió nada.");
        }

        return $existing;
    }

    /** @return array<string, string> */
    public function getValidationMessages(): array
    {
        return [
            'first_name.required' => 'Faltan los nombres.',
            'first_name.max' => 'Los nombres son muy largos: hasta 100 letras.',
            'last_name.required' => 'Faltan los apellidos.',
            'last_name.max' => 'Los apellidos son muy largos: hasta 100 letras.',
            'document_number.regex' => 'El DNI tiene 8 dígitos.',
            'birth_date.date_format' => 'Escribe la fecha de nacimiento como día/mes/año, por ejemplo 12/04/1985.',
            'birth_date.before_or_equal' => 'La fecha de nacimiento es de un día que todavía no llega.',
            'birth_date.after_or_equal' => 'Revisa el año de nacimiento.',
            'email.email' => 'Revisa el correo: le falta algo.',
            'email.max' => 'El correo es muy largo.',
            'sex.in' => 'En sexo escribe F o M.',
            'admin_notes.max' => 'Las notas son muy largas: hasta 2000 letras.',
        ];
    }

    /** Lo de la fila se guarda al registrar (paciente nuevo) o solo en lo que le falta (paciente que ya estaba). */
    public function fillRecord(): void
    {
        if (! $this->record->exists) {
            return;
        }

        foreach (self::FILLABLE as $attribute) {
            if (blank($this->record->getAttribute($attribute)) && filled($this->data[$attribute] ?? null)) {
                $this->record->setAttribute($attribute, $this->data[$attribute]);
            }
        }

        if ($this->record->isDirty('document_number') && $this->record->getAttribute('document_type') === null) {
            $this->record->setAttribute('document_type', DocumentType::Dni);
        }
    }

    /** El paciente nuevo entra con el alta de siempre: su celular lo agrupa con su familia, si ya está en el sistema. */
    public function saveRecord(): void
    {
        $phone = $this->data['phone'] ?? null;

        if (! $this->record->exists) {
            $this->record = app(PatientRegistrar::class)->register(array_filter(
                [...array_intersect_key($this->data, array_flip(['first_name', 'last_name', ...self::FILLABLE])), 'phone' => $phone],
                fn (mixed $value): bool => filled($value),
            ));

            return;
        }

        $patient = $this->patient();
        $patient->save();

        if ($patient->account_id === null && filled($phone)) {
            app(PatientRegistrar::class)->moveToPhone($patient, $phone);
        }
    }

    /** Las filas que no entraron, en Excel (se abren bien en Excel en español) y listas para corregir y volver a subir. */
    public static function getFailedRowsDownloader(): Downloader
    {
        return app(FailedRowsDownloader::class);
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return 'Importación de pacientes terminada';
    }

    /** "Se importaron 120 pacientes. 5 filas no se importaron: 3 ya estaban registrados y 2 tienen datos por corregir." */
    public static function getCompletedNotificationBody(Import $import): string
    {
        $imported = (int) $import->successful_rows;
        $failed = $import->getFailedRowsCount();
        $already = $import->failedRows()->where('validation_error', 'like', self::ALREADY_REGISTERED.'%')->count();
        $toFix = $failed - $already;

        $body = match ($imported) {
            0 => 'No se importó ningún paciente.',
            1 => 'Se importó 1 paciente.',
            default => 'Se importaron '.number_format($imported).' pacientes.',
        };

        if ($failed === 0) {
            return $body;
        }

        $alreadyText = $already === 1 ? '1 ya estaba registrado' : number_format($already).' ya estaban registrados';
        $toFixText = $toFix === 1 ? '1 tiene datos por corregir' : number_format($toFix).' tienen datos por corregir';

        $failures = match (true) {
            $already > 0 && $toFix > 0 => number_format($failed).' filas no se importaron: '.$alreadyText.' y '.$toFixText.'.',
            $already > 0 => $alreadyText.($already === 1 ? ' y quedó como estaba.' : ' y quedaron como estaban.'),
            default => $toFix === 1 ? '1 fila tiene datos por corregir.' : number_format($toFix).' filas tienen datos por corregir.',
        };

        return $body.' '.$failures.' Descarga el archivo para ver '.($failed === 1 ? 'cuál' : 'cuáles').' y por qué.';
    }

    /** El paciente de la fila: el que ya estaba o el nuevo (resolveRecord). */
    private function patient(): Patient
    {
        /** @var Patient $patient */
        $patient = $this->record;

        return $patient;
    }

    /**
     * El paciente que ya está registrado y por qué se sabe, o [null, null]. Primero por DNI; luego por celular, con el
     * mismo nombre (otro nombre con ese celular es un familiar, y se suma a la familia); y sin DNI ni celular, por
     * nombre y fecha de nacimiento. Un DNI que ya es de otra persona (otro nombre) no se toca: hay que revisarlo.
     *
     * @return array{0: ?Patient, 1: ?string}
     */
    private function alreadyRegistered(): array
    {
        $firstName = (string) ($this->data['first_name'] ?? '');
        $lastName = (string) ($this->data['last_name'] ?? '');
        $document = $this->data['document_number'] ?? null;

        if (filled($document) && preg_match('/^\d{8}$/', $document) === 1) {
            $owner = Patient::withTrashed()->where('document_type', DocumentType::Dni)->where('document_number', $document)->first();

            if ($owner?->trashed()) {
                throw new RowImportFailedException("Hubo un paciente con el DNI {$document} y se borró: {$owner->full_name}. Si es la misma persona, pide que lo restauren.");
            }

            if ($owner !== null && ! self::samePerson($owner, $firstName, $lastName)) {
                throw new RowImportFailedException("El DNI {$document} ya es de {$owner->full_name}. Revisa el DNI.");
            }

            if ($owner !== null) {
                return [$owner, 'mismo DNI'];
            }
        }

        $phone = $this->data['phone'] ?? null;

        if (filled($phone) && Phone::isValid($phone)) {
            $account = Account::query()->where('phone', Phone::normalize($phone))->first();
            $relative = $account === null ? null : Patient::query()->whereBelongsTo($account)->get()
                ->first(fn (Patient $patient): bool => self::samePerson($patient, $firstName, $lastName));

            if ($relative !== null) {
                return [$relative, 'mismo celular y nombre'];
            }
        }

        $birthDate = $this->data['birth_date'] ?? null;

        if (filled($birthDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate) === 1) {
            $twin = Patient::query()->whereDate('birth_date', $birthDate)->get()
                ->first(fn (Patient $patient): bool => self::samePerson($patient, $firstName, $lastName));

            if ($twin !== null) {
                return [$twin, 'mismo nombre y fecha de nacimiento'];
            }
        }

        return [null, null];
    }

    /** El mismo primer nombre y el mismo primer apellido, sin importar tildes: "Rosa Huamán" es "Rosa María Huamán Quispe". */
    private static function samePerson(Patient $patient, string $firstName, string $lastName): bool
    {
        return self::firstWord($patient->first_name) === self::firstWord($firstName) && self::firstWord($patient->last_name) === self::firstWord($lastName);
    }

    private static function firstWord(string $text): string
    {
        return Str::before(Patient::searchable($text).' ', ' ');
    }

    /**
     * Los títulos de columna que se reconocen solos, con y sin tildes ("teléfono" y "telefono").
     *
     * @return list<string>
     */
    private static function guesses(string ...$names): array
    {
        $plain = array_map(fn (string $name): string => strtr($name, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']), $names);

        return array_values(array_unique([...$names, ...$plain]));
    }

    /** Sin espacios de más; vacío es null. */
    private static function text(mixed $state): ?string
    {
        $text = Str::squish((string) $state);

        return $text === '' ? null : $text;
    }

    /** Un nombre escrito todo en mayúsculas (o todo en minúsculas) queda como se escribe: "ROSA DE LA CRUZ" → "Rosa de la Cruz". */
    private static function name(mixed $state): ?string
    {
        $text = self::text($state);

        if ($text === null || ($text !== mb_strtoupper($text) && $text !== mb_strtolower($text))) {
            return $text;
        }

        return collect(explode(' ', mb_convert_case($text, MB_CASE_TITLE)))
            ->map(fn (string $word, int $index): string => $index > 0 && in_array(mb_strtolower($word), ['de', 'del', 'la', 'las', 'los', 'y'], true) ? mb_strtolower($word) : $word)
            ->implode(' ');
    }

    /** Sin puntos ni guiones; si Excel le quitó el cero de adelante ("7654321"), se le devuelve. */
    private static function dni(mixed $state): ?string
    {
        $number = DocumentType::clean(self::text($state));

        if ($number !== null && preg_match('/^\d{6,7}$/', $number) === 1) {
            return str_pad($number, 8, '0', STR_PAD_LEFT);
        }

        return $number;
    }

    /**
     * Día/mes/año, como se escribe en Perú ("12/04/1985", "12-04-85"), o año-mes-día. Lo que no es una fecha se deja
     * como vino, para que la fila diga qué revisar.
     */
    private static function date(mixed $state): ?string
    {
        $text = self::text($state);

        if ($text === null) {
            return null;
        }

        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4}|\d{2})$/', $text, $parts) === 1) {
            [, $day, $month, $year] = $parts;
        } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T].*)?$/', $text, $parts) === 1) {
            [, $year, $month, $day] = $parts;
        } else {
            return $text;
        }

        if (strlen($year) === 2) {
            $year = (int) $year + ((int) $year > (int) now()->format('y') ? 1900 : 2000);
        }

        return checkdate((int) $month, (int) $day, (int) $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : $text;
    }

    /** "F", "Femenino", "Mujer" → F; "M", "Masculino", "Hombre", "Varón" → M. */
    private static function sex(mixed $state): ?string
    {
        $text = self::text($state);

        return match ($text === null ? null : Str::lower(Str::ascii($text))) {
            null => null,
            'f', 'fem', 'fem.', 'femenino', 'mujer' => Sex::Female->value,
            'm', 'masc', 'masc.', 'masculino', 'hombre', 'varon', 'h' => Sex::Male->value,
            default => $text,
        };
    }
}
