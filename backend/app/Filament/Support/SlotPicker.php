<?php

namespace App\Filament\Support;

use App\Enums\AppointmentStatus;
use App\Enums\DayPeriod;
use App\Filament\Forms\ChoiceChips;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Agenda\AvailabilityService;
use App\Services\Agenda\GapFinder;
use App\Services\Agenda\Slot;
use App\Services\Patients\PatientInsights;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Campos para elegir doctor, día y un horario libre según la agenda inteligente.
 * Recepción puede usar "otra hora" (por ejemplo, fuera del horario), pero el
 * sistema igual impide cruzar dos citas.
 */
final class SlotPicker
{
    /** Con "Otra hora" se ofrecen las horas de 06 a 22: lo que un consultorio puede llegar a atender fuera de su horario. */
    private const FIRST_MANUAL_HOUR = 6;

    private const LAST_MANUAL_HOUR = 22;

    /** Los días que se ven como botones: hoy y los seis siguientes. */
    private const DAYS_SHOWN = 7;

    /**
     * @param  Appointment|null  $moving  Cita que se mueve: su tratamiento es fijo y su horario no cuenta como ocupado.
     * @return list<Component|Field>
     */
    public static function fields(?Appointment $moving = null): array
    {
        // Con otro tratamiento o doctor: si el día elegido ya no tiene lugar (ya pasó o está lleno) y no se eligió a
        // propósito, se pasa solo al siguiente con lugar. Y queda puesto el primer horario libre desde la hora que
        // se tocó en la agenda.
        $suggest = function (Set $set, Get $get) use ($moving): void {
            if ($moving === null) {
                self::moveToDayWithSlots($get, $set);
            }

            $set('slot', self::suggestedSlot($get, $moving));
        };

        return [
            // Hora que se tocó en la agenda: se propone el primer horario libre desde ahí.
            Hidden::make('preferred_time')
                ->default(fn () => preg_match('/^\d{2}:\d{2}$/', (string) request('time')) ? request('time') : null)
                ->dehydrated(false),

            // El día que se eligió a propósito (en la agenda o con un toque): entonces no se cambia por el día en que
            // suele venir el paciente ni por el siguiente con lugar.
            Hidden::make('date_from_agenda')
                ->default(fn () => self::requestedDate()?->toDateString())
                ->dehydrated(false),

            // Primero qué se hace y después con quién: así lo cuenta el paciente al llamar.
            self::treatmentSelect($moving)->afterStateUpdated(function (Set $set, Get $get) use ($suggest): void {
                $set('treatment_shortcut', filled($get('treatment_id')) ? (string) $get('treatment_id') : null);
                $suggest($set, $get);
            }),
            self::doctorSelect($moving)->afterStateUpdated($suggest),

            // Los tratamientos que más se agendan, a un toque (antes siempre había que abrir la lista y buscar).
            ChoiceChips::make('treatment_shortcut')
                ->label('Los más pedidos')
                ->options(fn (Get $get) => self::quickTreatments($get('doctor_id')))
                ->afterStateHydrated(fn (ChoiceChips $component, Get $get) => $component->state(filled($get('treatment_id')) ? (string) $get('treatment_id') : null))
                ->extraAttributes(['class' => 'treatment-chips'])
                ->live()
                ->dehydrated(false)
                ->hidden(fn (Get $get) => $moving !== null || self::quickTreatments($get('doctor_id')) === [])
                ->columnSpanFull()
                ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($suggest): void {
                    $set('treatment_id', filled($state) ? (int) $state : null);
                    $suggest($set, $get);
                }),

            // Los próximos siete días, cada uno con cuántos horarios libres tiene (antes era un calendario y había
            // que probar día por día). Un día más lejos, con "Otro día".
            ChoiceChips::make('date')
                ->label('Día')
                ->options(fn (Get $get) => self::dayOptions($get('date')))
                ->descriptions(fn (Get $get) => self::freeSlotsPerDay($get, $moving))
                // Desde la ficha ("Agendar"), el próximo día en que suele venir el paciente.
                ->default(fn () => (self::requestedDate() ?? ($moving ? null : self::nextUsualDay(Patient::find(request()->integer('patient')))) ?? today())->toDateString())
                ->extraAttributes(['class' => 'day-chips'])
                ->required()
                ->rule('after_or_equal:today')
                ->live()
                // Si algo suyo sigue en el laboratorio y llega después de ese día, se avisa antes de agendar.
                ->helperText(fn (Get $get) => self::labWorkArrivingAfter($moving?->patient_id ?? $get('patient_id'), $get('date')))
                ->hintAction(self::otherDayAction($moving))
                ->columnSpanFull()
                ->afterStateUpdated(function (Get $get, Set $set) use ($moving): void {
                    $set('date_from_agenda', $get('date'));
                    $set('slot', self::suggestedSlot($get, $moving));
                }),

            // Todos los horarios a la vista, un toque cada uno, separados en mañana y tarde.
            ChoiceChips::make('slot')
                ->label('Horario libre')
                ->helperText(fn (Get $get) => self::slotsHint($get, $moving))
                // Día lleno: un toque lleva al siguiente con lugar (antes había que probar día por día).
                ->hintAction(Action::make('nextDayWithSlots')
                    ->label(fn (Get $get) => 'Ir al '.self::nextDayWithSlots($get, $moving)?->translatedFormat('l j'))
                    ->link()
                    ->visible(fn (Get $get) => self::slotOptions($get, $moving) === [] && self::nextDayWithSlots($get, $moving) !== null)
                    ->action(function (Get $get, Set $set) use ($moving): void {
                        $next = self::nextDayWithSlots($get, $moving);

                        if ($next) {
                            $set('date', $next->toDateString());
                            $set('slot', self::suggestedSlot($get, $moving));
                        }
                    }))
                ->options(fn (Get $get) => self::slotOptions($get, $moving))
                ->groups(fn (Get $get) => self::slotGroups($get, $moving))
                ->highlighted(fn (Get $get) => self::recommendedSlots($get, $moving))
                ->descriptions(fn (Get $get) => array_fill_keys(self::recommendedSlots($get, $moving), 'Recomendada'))
                ->extraAttributes(['class' => 'slot-chips'])
                // Al tocar un horario, el resumen dice a qué hora termina.
                ->live()
                ->columnSpanFull()
                ->required(fn (Get $get) => ! $get('manual'))
                ->hidden(fn (Get $get) => (bool) $get('manual')),

            // Para una hora que la agenda no ofrece: fuera del horario de atención o, en una cita nueva, un
            // sobrecupo. Llega con la hora que se había elegido o tocado en la agenda, si todavía no pasó.
            Toggle::make('manual')
                ->label($moving === null ? 'Otra hora (fuera del horario, o un sobrecupo)' : 'Otra hora (fuera del horario de atención)')
                // Se usa poco: más chico que los horarios, para no competir con ellos.
                ->extraFieldWrapperAttributes(['class' => 'quiet-toggle'])
                ->columnSpanFull()
                ->live()
                ->afterStateUpdated(function (?bool $state, Get $get, Set $set): void {
                    $carried = $get('slot') ?? $get('preferred_time');

                    if ($state && blank($get('manual_time')) && filled($carried) && ! self::isPast($get('date'), $carried)) {
                        $set('manual_time', $carried);
                    }

                    self::showManualTime($get, $set);
                }),

            // La hora de "Otra hora". Ya no se escribe: la arman los dos grupos de botones de abajo.
            Hidden::make('manual_time')
                ->afterStateUpdated(fn (Get $get, Set $set) => self::showManualTime($get, $set)),

            // Dos toques, la hora y los minutos, como los horarios libres (antes había que teclearla en un cuadro
            // vacío). Con solo tocar la hora queda en punto (o en el primer minuto que todavía no pasó). Hoy, solo
            // desde la hora actual.
            ToggleButtons::make('manual_hour')
                ->label('Hora')
                ->options(fn (Get $get) => self::hourOptions($get('manual_time'), $get('date')))
                ->inline()
                ->fullWidth()
                ->extraAttributes(['class' => 'slot-buttons time-buttons'])
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::composeManualTime($get, $set))
                ->visible(fn (Get $get) => (bool) $get('manual'))
                // Obligatoria mientras no haya una hora completa; el asterisco no se quita al elegirla.
                ->required(fn (Get $get) => $get('manual') && blank($get('manual_time')))
                ->markAsRequired()
                ->dehydrated(false),

            ToggleButtons::make('manual_minute')
                ->label('Minutos')
                ->helperText(fn (Get $get) => self::manualTimeHint($get('manual_time'), $get('date')))
                ->options(fn (Get $get) => self::minuteOptions($get('manual_time'), $get('date'), $get('manual_hour')))
                ->inline()
                ->fullWidth()
                ->extraAttributes(['class' => 'slot-buttons time-buttons'])
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::composeManualTime($get, $set))
                ->visible(fn (Get $get) => (bool) $get('manual'))
                ->dehydrated(false),

            // "Otra hora" deja elegir una que ya pasó (sirve para anotar a quien vino sin cita): antes se pregunta.
            Checkbox::make('past_time_ok')
                ->label('Esa hora ya pasó: registrar la cita igual')
                ->helperText('No se prepara aviso al paciente. Si fue un error, corrige la hora.')
                ->visible(fn (Get $get) => self::manualTimeIsPast($get))
                ->accepted(fn (Get $get) => self::manualTimeIsPast($get))
                ->validationMessages(['accepted' => 'Esa hora ya pasó. Corrígela o marca la casilla para registrar la cita igual.'])
                ->dehydrated(false),

            // Sobrecupo: la urgencia que el doctor aceptó ver aunque esa hora ya tenga cita. Solo con "Otra hora" en
            // una cita nueva, y se pregunta antes: sin marcarlo, dos citas nunca se cruzan.
            Checkbox::make('overbook')
                ->label(fn (Get $get) => 'Sobrecupo: agendar igual, encima de la cita de '.self::appointmentInTheWay($get)?->patient->full_name)
                ->helperText(fn (Get $get) => self::overbookWarning(self::appointmentInTheWay($get)))
                ->visible(fn (Get $get) => $moving === null && self::appointmentInTheWay($get) !== null)
                ->accepted(fn (Get $get) => self::appointmentInTheWay($get) !== null)
                ->validationMessages(['accepted' => 'Esa hora se cruza con otra cita. Elige otra hora, o marca "Sobrecupo" si el doctor aceptó verlo igual.']),
        ];
    }

    /** "Otro día": un día más allá de los siete que se ven, en un calendario. */
    private static function otherDayAction(?Appointment $moving): Action
    {
        return Action::make('otherDay')
            ->label('Otro día')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->link()
            ->modalHeading('Otro día')
            ->modalWidth(Width::Small)
            // Encima de "Nueva cita", sin cerrarla mientras se elige.
            ->overlayParentActions()
            ->modalSubmitActionLabel('Elegir')
            ->fillForm(fn (Get $get) => ['day' => $get('date')])
            ->schema([
                DatePicker::make('day')
                    ->label('Día')
                    ->native(false)
                    ->displayFormat('D d/m/Y')
                    ->firstDayOfWeek(1)
                    ->closeOnDateSelection()
                    ->minDate(today())
                    ->required(),
            ])
            ->action(function (array $data, Get $get, Set $set) use ($moving): void {
                $day = CarbonImmutable::parse($data['day'])->toDateString();

                $set('date', $day);
                $set('date_from_agenda', $day);
                $set('slot', self::suggestedSlot($get, $moving));
            });
    }

    /**
     * Los botones de los días: hoy y los seis siguientes, más el elegido si cae más lejos.
     *
     * @return array<string, string> "2026-11-19" => "Mañana"
     */
    private static function dayOptions(mixed $chosen): array
    {
        $days = collect(range(0, self::DAYS_SHOWN - 1))->map(fn (int $offset) => today()->addDays($offset)->toDateString());

        if (is_string($chosen) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $chosen) === 1) {
            $days->push($chosen);
        }

        return $days->unique()->sort()->mapWithKeys(fn (string $date) => [$date => self::dayLabel(CarbonImmutable::parse($date))])->all();
    }

    /** "Hoy", "Mañana", "Vie 20" (y, si es de otro mes, "Lun 4 ene"). */
    private static function dayLabel(CarbonImmutable $day): string
    {
        return match (true) {
            $day->isToday() => 'Hoy',
            $day->isTomorrow() => 'Mañana',
            default => ucfirst(rtrim($day->translatedFormat('D'), '.')).' '.$day->day.($day->month !== today()->month ? ' '.rtrim($day->translatedFormat('M'), '.') : ''),
        };
    }

    /**
     * Bajo cada día, cuántos horarios libres tiene para el tratamiento y el doctor elegidos.
     *
     * @return array<string, string> "2026-11-19" => "12 libres"
     */
    private static function freeSlotsPerDay(Get $get, ?Appointment $moving): array
    {
        if (! self::canSearch($get, $moving)) {
            return [];
        }

        return collect(self::dayOptions($get('date')))
            ->map(fn (string $label, string $date) => match ($count = self::slotsOn($get, $moving, $date)->count()) {
                0 => 'Sin lugar',
                1 => '1 libre',
                default => "{$count} libres",
            })
            ->all();
    }

    /**
     * Las horas que se ofrecen con "Otra hora": de 6 de la mañana a 10 de la noche (hoy, desde la hora actual: una
     * hora que ya pasó no se ofrece), más la que ya esté puesta si cae fuera (una hora que llegó de otro lado nunca
     * queda sin su botón).
     *
     * @return array<int|string, string> "16" => "16"
     */
    private static function hourOptions(?string $current, mixed $date): array
    {
        $first = self::FIRST_MANUAL_HOUR;

        if (self::isToday($date)) {
            // En los últimos cinco minutos de la hora ya no queda ningún minuto por delante: desde la siguiente.
            $first = max($first, now()->minute >= 55 ? now()->hour + 1 : now()->hour);
        }

        $hours = $first <= self::LAST_MANUAL_HOUR ? range($first, self::LAST_MANUAL_HOUR) : [];

        if (($time = self::manualTimeParts($current)) !== null) {
            $hours[] = $time['hour'];
        }

        sort($hours);

        return collect($hours)->unique()->mapWithKeys(fn (int $hour) => [sprintf('%02d', $hour) => sprintf('%02d', $hour)])->all();
    }

    /**
     * Los minutos, de cinco en cinco (hoy, en la hora actual, los que todavía no pasaron), más los que ya estén
     * puestos si no son de esos.
     *
     * @return array<int|string, string> "05" => ":05"
     */
    private static function minuteOptions(?string $current, mixed $date, mixed $hour): array
    {
        $minutes = range(0, 55, 5);

        if (self::isToday($date) && filled($hour) && (int) $hour === now()->hour) {
            $minutes = array_values(array_filter($minutes, fn (int $minute) => $minute > now()->minute));
        }

        if (($time = self::manualTimeParts($current)) !== null) {
            $minutes[] = $time['minute'];
        }

        sort($minutes);

        return collect($minutes)->unique()->mapWithKeys(fn (int $minute) => [sprintf('%02d', $minute) => sprintf(':%02d', $minute)])->all();
    }

    /** Bajo los minutos: la hora que quedó (por la tarde, también como se dice: "4:20 p. m.") o qué falta tocar. */
    private static function manualTimeHint(?string $current, mixed $date): string
    {
        $time = self::manualTimeParts($current);

        return match (true) {
            $time === null && self::hourOptions(null, $date) === [] => 'Hoy ya no quedan horas por delante: elige otro día.',
            $time === null => 'Toca la hora y, si no es en punto, los minutos.',
            $time['hour'] >= 13 => "La cita empieza a las {$current} (".Dates::time($current).').',
            default => "La cita empieza a las {$current}.",
        };
    }

    /**
     * Al tocar la hora o los minutos: la hora completa. Sin minutos todavía, queda en punto (hoy, en la hora actual,
     * en el primer minuto que todavía no pasó); sin hora, espera.
     */
    private static function composeManualTime(Get $get, Set $set): void
    {
        if (blank($get('manual_hour'))) {
            return;
        }

        if (blank($get('manual_minute'))) {
            $set('manual_minute', '00');
        }

        if (self::isPast($get('date'), sprintf('%02d:%02d', (int) $get('manual_hour'), (int) $get('manual_minute')))) {
            $ahead = self::minuteOptions(null, $get('date'), $get('manual_hour'));

            if ($ahead !== []) {
                $set('manual_minute', sprintf('%02d', array_key_first($ahead)));
            }
        }

        $set('manual_time', sprintf('%02d:%02d', (int) $get('manual_hour'), (int) $get('manual_minute')));
    }

    /** La hora que ya está puesta (la que se eligió o se tocó en la agenda) queda marcada en los botones. */
    private static function showManualTime(Get $get, Set $set): void
    {
        if (($time = self::manualTimeParts($get('manual_time'))) !== null) {
            $set('manual_hour', sprintf('%02d', $time['hour']));
            $set('manual_minute', sprintf('%02d', $time['minute']));
        }
    }

    /** @return array{hour: int, minute: int}|null Null si todavía no hay una hora completa. */
    private static function manualTimeParts(?string $time): ?array
    {
        return preg_match('/^(\d{1,2}):(\d{2})/', (string) $time, $parts) === 1
            ? ['hour' => (int) $parts[1], 'minute' => (int) $parts[2]]
            : null;
    }

    /** "Luis Quispe tiene esa hora (de 10:00 a 10:30). Márcalo solo si…" */
    private static function overbookWarning(?Appointment $inTheWay): ?string
    {
        return $inTheWay
            ? $inTheWay->patient->full_name.' ya tiene esa hora (de '.$inTheWay->starts_at->format('H:i').' a '.$inTheWay->ends_at->format('H:i')
                .'). Márcalo solo para una urgencia que el doctor aceptó ver: los dos quedan a la misma hora y esta cita se marca como sobrecupo.'
            : null;
    }

    /**
     * Con "Otra hora" en una cita nueva: la cita que ya ocupa la hora elegida. Null si esa hora está libre, si falta
     * algún dato o si lo que estorba es un bloqueo (encima de un bloqueo no hay sobrecupo).
     */
    private static function appointmentInTheWay(Get $get): ?Appointment
    {
        $doctor = Doctor::find($get('doctor_id'));
        $treatment = Treatment::find($get('treatment_id'));

        if (! $get('manual') || blank($get('date')) || blank($get('manual_time')) || ! $doctor || ! $treatment) {
            return null;
        }

        $startsAt = CarbonImmutable::parse($get('date'))->setTimeFromTimeString($get('manual_time'));
        $duration = self::chosenDuration($get) ?? $doctor->durationFor($treatment);
        $patientId = filled($get('patient_id')) ? (int) $get('patient_id') : null;

        // Lo piden el texto, la casilla y su validación en el mismo pedido: se busca una sola vez.
        $memo = 'slot-picker.in-the-way.'.implode('|', [$doctor->getKey(), $treatment->getKey(), $startsAt->getTimestamp(), $duration, $patientId]);

        if (! request()->attributes->has($memo)) {
            $clash = app(AvailabilityService::class)->forPatient($patientId)->clashAt($doctor, $startsAt, $duration, $treatment->bufferMinutes());

            request()->attributes->set($memo, $clash instanceof Appointment ? $clash : null);
        }

        return request()->attributes->get($memo);
    }

    /** Con "Otra hora", la fecha y hora elegidas ya quedaron atrás. */
    private static function manualTimeIsPast(Get $get): bool
    {
        if (! $get('manual') || blank($get('date')) || blank($get('manual_time'))) {
            return false;
        }

        return self::isPast($get('date'), $get('manual_time'));
    }

    /** Ese día a esa hora ya quedó atrás. */
    private static function isPast(mixed $date, mixed $time): bool
    {
        return filled($date) && filled($time) && CarbonImmutable::parse($date)->setTimeFromTimeString((string) $time)->isPast();
    }

    private static function isToday(mixed $date): bool
    {
        return filled($date) && CarbonImmutable::parse($date)->isToday();
    }

    /** Qué se hace: solo lo que realiza el doctor elegido (si ya hay uno). */
    public static function treatmentSelect(?Appointment $moving = null): Select
    {
        return Select::make('treatment_id')
            ->label('Tratamiento')
            ->options(fn (Get $get) => Treatment::query()
                ->active()
                ->when($get('doctor_id'), fn ($q, $doctorId) => $q->whereHas('doctors', fn ($d) => $d->whereKey($doctorId)))
                ->orderBy('sort_order')
                ->pluck('name', 'id'))
            ->default($moving?->treatment_id ?? (request()->integer('treatment') ?: null))
            ->hidden($moving !== null)
            ->dehydrated($moving === null)
            ->required($moving === null)
            // Sin la lista de doctores al lado (hay uno solo), ocupa todo el ancho.
            ->columnSpan(fn (Get $get) => $moving === null && self::doctorIsSettled($get) ? 'full' : 1)
            ->searchable()
            ->live();
    }

    /** Con quién: solo quienes hacen el tratamiento elegido (si ya hay uno). El doctor solo agenda con él mismo. */
    public static function doctorSelect(?Appointment $moving = null): Select
    {
        /** @var User $user */
        $user = auth()->user();
        $ownDoctorId = $user->managesAllAgendas() ? null : $user->doctorId();

        return Select::make('doctor_id')
            ->label('Doctor')
            ->options(fn (Get $get) => Doctor::query()
                ->active()
                ->when($moving?->treatment_id ?? $get('treatment_id'), fn ($q, $treatmentId) => $q->whereHas('treatments', fn ($t) => $t->whereKey($treatmentId)))
                ->orderBy('sort_order')
                ->pluck('name', 'id'))
            ->default(fn () => $moving?->doctor_id ?? $ownDoctorId ?? (request()->integer('doctor') ?: null) ?? self::onlyActiveDoctorId())
            // Tratamiento que ningún doctor activo atiende: la lista sale vacía, y se dice por qué y dónde se arregla.
            ->helperText(fn (Get $get) => self::nobodyAttends($moving?->treatment_id ?? $get('treatment_id'))
                ? new HtmlString('<span class="help-warning">Ningún doctor atiende este tratamiento. Márcalo en Configuración › Tratamientos › "¿Quién lo atiende?".</span>')
                : null)
            ->disabled($ownDoctorId !== null)
            // En una cita nueva, si no hay a quién más elegir, la lista sobra: el doctor sale en el resumen.
            ->hidden(fn (Get $get) => $moving === null && self::doctorIsSettled($get))
            ->dehydrated()
            ->dehydratedWhenHidden()
            ->required()
            ->live();
    }

    /**
     * El doctor ya está decidido y no hay otro que elegir: el doctor agenda con él mismo, o el consultorio tiene un
     * solo doctor activo y atiende el tratamiento elegido (si no lo atiende, la lista se muestra para decir por qué).
     */
    private static function doctorIsSettled(Get $get): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return filled($get('doctor_id'))
            && (! $user->managesAllAgendas() || self::onlyActiveDoctorId() !== null)
            && ! self::nobodyAttends($get('treatment_id'));
    }

    /**
     * El doctor que ya viene puesto en una ventana que llena sus datos a mano: el propio (el doctor solo agenda
     * con él mismo) o, si el consultorio tiene un solo doctor activo, ese.
     */
    public static function defaultDoctorId(): ?int
    {
        /** @var User $user */
        $user = auth()->user();

        return ($user->managesAllAgendas() ? null : $user->doctorId()) ?? self::onlyActiveDoctorId();
    }

    /** Fecha y hora elegidas en el formulario. */
    public static function startsAt(array $data): CarbonImmutable
    {
        $time = ($data['manual'] ?? false) ? $data['manual_time'] : $data['slot'];

        return CarbonImmutable::parse($data['date'])->setTimeFromTimeString($time);
    }

    /**
     * Al elegir al paciente, si suele venir un mismo día, se propone el próximo (salvo que el día se haya
     * elegido en la agenda). Sus horarios de siempre salen primero en la lista.
     */
    public static function followUsualDay(Get $get, Set $set): void
    {
        if (filled($get('date_from_agenda')) || filled($get('preferred_time'))) {
            return;
        }

        $date = self::nextUsualDay(filled($get('patient_id')) ? Patient::find($get('patient_id')) : null);

        if ($date) {
            $set('date', $date->toDateString());
            $set('slot', null);
        }
    }

    /** El próximo día (desde hoy) en que suele venir; null si no tiene un día fijo. */
    public static function nextUsualDay(?Patient $patient): ?CarbonImmutable
    {
        $weekday = $patient ? app(PatientInsights::class)->usualVisit($patient)['weekday'] ?? null : null;

        if ($weekday === null) {
            return null;
        }

        $date = CarbonImmutable::today();

        while ($date->dayOfWeek !== $weekday) {
            $date = $date->addDay();
        }

        return $date;
    }

    /**
     * Cuándo suele venir el paciente elegido.
     *
     * @return array{weekday: int|null, period: DayPeriod|null, label: string}|null
     */
    private static function usualVisit(Get $get): ?array
    {
        $patient = filled($get('patient_id')) ? Patient::find($get('patient_id')) : null;

        return $patient ? app(PatientInsights::class)->usualVisit($patient) : null;
    }

    /** Primer horario libre desde la hora que se tocó en la agenda. */
    private static function suggestedSlot(Get $get, ?Appointment $moving): ?string
    {
        $preferred = $get('preferred_time');

        if (! $preferred) {
            return null;
        }

        return collect(array_keys(self::slotOptions($get, $moving)))
            ->first(fn (string $time) => $time >= $preferred);
    }

    /** "Su corona de zirconio llega del laboratorio el sáb. 14 nov.: si esta cita es para colocarla, agéndala desde ese día." */
    private static function labWorkArrivingAfter(mixed $patientId, mixed $date): ?string
    {
        if (blank($patientId) || blank($date)) {
            return null;
        }

        $order = LabOrder::query()->arrivingAfter((int) $patientId, CarbonImmutable::parse($date))->orderBy('due_on')->first();

        return $order
            ? 'Ojo: "'.$order->description.'" llega del laboratorio el '.$order->due_on->translatedFormat('D j M').'. Si esta cita es para colocarlo, agéndala desde ese día.'
            : null;
    }

    /** El tratamiento elegido no lo atiende ningún doctor activo: no hay con quién agendarlo. */
    private static function nobodyAttends(mixed $treatmentId): bool
    {
        return filled($treatmentId)
            && Doctor::query()->active()->whereHas('treatments', fn ($treatments) => $treatments->whereKey($treatmentId))->doesntExist();
    }

    /** Si el consultorio tiene un solo doctor activo, ya viene elegido. */
    public static function onlyActiveDoctorId(): ?int
    {
        $ids = Doctor::query()->active()->limit(2)->pluck('id');

        return $ids->count() === 1 ? $ids->first() : null;
    }

    private static function requestedDate(): ?CarbonImmutable
    {
        $date = (string) request('date');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && CarbonImmutable::parse($date)->gte(today())
            ? CarbonImmutable::parse($date)
            : null;
    }

    /** Bajo los horarios: qué significa la estrella o, si no hay ninguno, por qué y qué hacer. */
    private static function slotsHint(Get $get, ?Appointment $moving): string
    {
        if (self::slotOptions($get, $moving) !== []) {
            $usual = self::usualVisit($get);

            return collect([
                $usual !== null && $usual['period'] !== null ? $usual['label'].': esos horarios van primero.' : null,
                self::recommendedSlots($get, $moving) !== [] ? 'Las recomendadas van pegadas a otra cita: así no quedan huecos en la agenda.' : null,
            ])->filter()->implode(' ');
        }

        if (! self::canSearch($get, $moving)) {
            // La hora que se tocó en la agenda no se pierde: se dice, y queda puesta al elegir lo que falta.
            return filled($get('preferred_time'))
                ? 'Elige tratamiento y doctor: queda puesta la primera hora libre desde las '.$get('preferred_time').', la que tocaste en la agenda.'
                : 'Elige tratamiento, doctor y día para ver los horarios libres.';
        }

        $next = self::nextDayWithSlots($get, $moving);

        return $next
            ? 'Ese día no hay horario libre. El siguiente con lugar es el '.$next->translatedFormat('l j \\d\\e F').'.'
            : 'Ni ese día ni en las '.intdiv(GapFinder::SEARCH_DAYS, 7).' semanas siguientes hay horario libre. Usa "Otra hora" o apúntalo en la lista de espera.';
    }

    /** ¿Ya se eligió tratamiento, doctor y día? (entonces, si no hay horarios, es que el día está lleno). */
    private static function canSearch(Get $get, ?Appointment $moving): bool
    {
        return filled($get('doctor_id')) && filled($moving?->treatment_id ?? $get('treatment_id')) && filled($get('date'));
    }

    /** El primer día después del elegido en que el doctor tiene un horario libre para el tratamiento. */
    private static function nextDayWithSlots(Get $get, ?Appointment $moving): ?CarbonImmutable
    {
        $doctor = Doctor::find($get('doctor_id'));
        $treatment = Treatment::find($moving?->treatment_id ?? $get('treatment_id'));

        if (! $doctor || ! $treatment || blank($get('date'))) {
            return null;
        }

        $from = CarbonImmutable::parse($get('date'))->startOfDay();
        $patientId = $moving?->patient_id ?? (filled($get('patient_id')) ? (int) $get('patient_id') : null);

        // Lo piden el texto y el botón en el mismo pedido: se busca una sola vez.
        $memo = 'slot-picker.next-day.'.implode('|', [$doctor->getKey(), $treatment->getKey(), $from->toDateString(), $patientId, $moving?->getKey(), self::chosenDuration($get, $moving)]);

        if (! request()->attributes->has($memo)) {
            request()->attributes->set($memo, self::firstDayWithSlots($doctor, $treatment, $from->addDay(), $patientId, self::chosenDuration($get, $moving), $moving?->getKey()));
        }

        return request()->attributes->get($memo);
    }

    /**
     * La duración puesta a mano: la que ya tiene la cita que se mueve o la elegida en "Detalles" de la cita nueva.
     * Null, la normal del tratamiento con ese doctor.
     */
    public static function chosenDuration(Get $get, ?Appointment $moving = null): ?int
    {
        if ($moving !== null) {
            return $moving->custom_duration ? $moving->durationMinutes() : null;
        }

        return filled($get('duration_minutes')) ? (int) $get('duration_minutes') : null;
    }

    /**
     * Para elegir cuánto dura una cita: las duraciones de siempre más las que se pidan (la normal de ese
     * tratamiento, la que la cita ya tiene), de menor a mayor.
     *
     * @return array<int, string> Minutos => "45 min"
     */
    public static function durationOptions(?int $usual = null, int ...$include): array
    {
        return collect([10, 15, 20, 30, 40, 45, 60, 75, 90, 105, 120, 150, 180, 210, 240, $usual, ...$include])
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (int $minutes) => [$minutes => Dates::duration($minutes).($minutes === $usual ? ' (lo normal)' : '')])
            ->all();
    }

    /** Al cambiar la duración, la hora elegida solo se conserva si la cita todavía cabe ahí. */
    public static function keepSlotIfItFits(Get $get, Set $set): void
    {
        if (filled($get('slot')) && ! array_key_exists($get('slot'), self::slotOptions($get, null))) {
            $set('slot', null);
        }
    }

    /** @return array<string, string> "10:00" => "10:00", los de la parte del día en que suele venir, primero. */
    private static function slotOptions(Get $get, ?Appointment $moving): array
    {
        return self::slots($get, $moving)->mapWithKeys(fn (Slot $slot) => [$slot->label() => $slot->label()])->all();
    }

    /** @return array<string, string> "10:00" => "Mañana", "15:30" => "Tarde" */
    private static function slotGroups(Get $get, ?Appointment $moving): array
    {
        return self::slots($get, $moving)
            ->mapWithKeys(fn (Slot $slot) => [$slot->label() => DayPeriod::Morning->includes($slot->startsAt) ? 'Mañana' : 'Tarde'])
            ->all();
    }

    /** @return list<string> Los horarios pegados a otra cita: no dejan huecos en la agenda. */
    private static function recommendedSlots(Get $get, ?Appointment $moving): array
    {
        return self::slots($get, $moving)->filter(fn (Slot $slot) => $slot->recommended)->map(fn (Slot $slot) => $slot->label())->values()->all();
    }

    /**
     * Los horarios libres del día elegido. Los de la parte del día en que suele venir, primero.
     *
     * @return Collection<int, Slot>
     */
    private static function slots(Get $get, ?Appointment $moving): Collection
    {
        $usualPeriod = $moving ? null : self::usualVisit($get)['period'] ?? null;

        return self::slotsOn($get, $moving, $get('date'))
            ->sortBy(fn (Slot $slot) => $usualPeriod && ! $usualPeriod->includes($slot->startsAt) ? 1 : 0)
            ->values();
    }

    /**
     * Los horarios libres de un día para el tratamiento y el doctor elegidos (hoy, desde ahora). Lo piden los
     * botones, sus grupos, el texto de abajo y la validación en el mismo pedido: se busca una sola vez.
     *
     * @return Collection<int, Slot>
     */
    private static function slotsOn(Get $get, ?Appointment $moving, mixed $date): Collection
    {
        $doctor = Doctor::find($get('doctor_id'));
        $treatment = Treatment::find($moving?->treatment_id ?? $get('treatment_id'));

        if (! $doctor || ! $treatment || blank($date)) {
            return collect();
        }

        $day = CarbonImmutable::parse($date);
        $patientId = $moving?->patient_id ?? (filled($get('patient_id')) ? (int) $get('patient_id') : null);
        $duration = self::chosenDuration($get, $moving);
        $memo = 'slot-picker.slots.'.implode('|', [$doctor->getKey(), $treatment->getKey(), $day->toDateString(), $patientId, $moving?->getKey(), $duration]);

        if (! request()->attributes->has($memo)) {
            // El horario apartado para este paciente (de la lista de espera) le sirve; el de otro, no.
            request()->attributes->set($memo, app(AvailabilityService::class)
                ->forPatient($patientId)
                ->slotsFor($doctor, $treatment, $day, earliest: $day->isToday() ? now() : null, ignoreAppointmentId: $moving?->getKey(), durationMinutes: $duration));
        }

        return request()->attributes->get($memo);
    }

    /**
     * Los botones de "Los más pedidos": los cuatro tratamientos que más se agendan (con el doctor elegido, si ya hay
     * uno). Nada si el consultorio tiene uno solo: con la lista basta.
     *
     * @return array<int, string> Id => nombre
     */
    private static function quickTreatments(mixed $doctorId): array
    {
        $memo = 'slot-picker.quick.'.(int) $doctorId;

        if (! request()->attributes->has($memo)) {
            $doctorIds = filled($doctorId) ? [(int) $doctorId] : Doctor::query()->active()->pluck('id')->all();
            $offered = Treatment::query()
                ->active()
                ->whereHas('doctors', fn ($doctors) => $doctors->whereKey($doctorIds))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'sort_order']);
            $popular = Treatment::mostBookedIds($doctorIds);

            request()->attributes->set($memo, $offered->count() < 2 ? [] : $offered
                ->sortBy(fn (Treatment $treatment) => [$popular->search($treatment->getKey()) === false ? PHP_INT_MAX : $popular->search($treatment->getKey()), $treatment->sort_order])
                ->take(4)
                ->mapWithKeys(fn (Treatment $treatment) => [$treatment->getKey() => $treatment->name])
                ->all());
        }

        return request()->attributes->get($memo);
    }

    /**
     * Si el día elegido ya no tiene horario libre (ya pasó, como a la noche, o está lleno) y no se eligió a
     * propósito, pasa al siguiente con lugar: así no se abre en un día vacío.
     */
    private static function moveToDayWithSlots(Get $get, Set $set): void
    {
        if (filled($get('date_from_agenda')) || ! self::canSearch($get, null) || self::slotOptions($get, null) !== []) {
            return;
        }

        if ($next = self::nextDayWithSlots($get, null)) {
            $set('date', $next->toDateString());
        }
    }

    /**
     * El primer día desde $from (incluido) en que el doctor tiene un horario libre para el tratamiento, dentro de
     * las semanas en que busca la agenda. Null si no hay ninguno.
     */
    public static function firstDayWithSlots(Doctor $doctor, Treatment $treatment, CarbonImmutable $from, ?int $patientId = null, ?int $duration = null, ?int $ignoreAppointmentId = null): ?CarbonImmutable
    {
        $availability = app(AvailabilityService::class)->forPatient($patientId);
        $from = $from->startOfDay();

        for ($day = $from; $day->lte($from->addDays(GapFinder::SEARCH_DAYS)); $day = $day->addDay()) {
            $earliest = $day->isToday() ? now() : null;

            if ($availability->slotsFor($doctor, $treatment, $day, earliest: $earliest, ignoreAppointmentId: $ignoreAppointmentId, durationMinutes: $duration)->isNotEmpty()) {
                return $day;
            }
        }

        return null;
    }

    /**
     * El doctor que atendió al paciente la última vez, si sigue activo y hace el tratamiento elegido. Así, con
     * varios doctores, no hay que elegirlo cada vez.
     */
    public static function lastDoctorId(?Patient $patient, mixed $treatmentId = null): ?int
    {
        if ($patient === null) {
            return null;
        }

        return Appointment::query()
            ->whereBelongsTo($patient)
            ->where('status', AppointmentStatus::Completed)
            ->whereHas('doctor', fn ($doctor) => $doctor->active()
                ->when(filled($treatmentId), fn ($query) => $query->whereHas('treatments', fn ($treatments) => $treatments->whereKey($treatmentId))))
            ->latest('starts_at')
            ->value('doctor_id');
    }

    /**
     * Al elegir al paciente, si todavía no hay doctor, queda el que lo atendió la última vez (el doctor solo agenda
     * con él mismo, así que esto es para recepción y administración).
     *
     * @return bool Si se puso el doctor.
     */
    public static function followLastDoctor(Get $get, Set $set): bool
    {
        if (filled($get('doctor_id'))) {
            return false;
        }

        $doctorId = self::lastDoctorId(filled($get('patient_id')) ? Patient::find($get('patient_id')) : null, $get('treatment_id'));

        if ($doctorId === null) {
            return false;
        }

        $set('doctor_id', $doctorId);

        return true;
    }

    /**
     * En una cita nueva, después de cambiar el doctor desde fuera de su lista (por ejemplo, al elegir al paciente):
     * lo mismo que al elegirlo a mano.
     */
    public static function refreshAfterDoctorChange(Get $get, Set $set): void
    {
        self::moveToDayWithSlots($get, $set);
        $set('slot', self::suggestedSlot($get, null));
    }
}
