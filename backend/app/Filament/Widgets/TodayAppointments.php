<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Filament\Actions\AppointmentActions;
use App\Filament\Concerns\RefreshesWithToday;
use App\Filament\Pages\Agenda;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Tables\AppointmentsTable;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Dashboard\OpenDays;
use App\Support\Dates;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Session;

/**
 * La agenda de hoy, en el orden en que pide algo y con un rótulo encima de cada parte, porque no va por hora: "En la
 * clínica" (en sala o en atención, como en "Citas de hoy"), "Por cobrar" (terminó y debe, para quien cobra), "Sin
 * cerrar" (pasó su hora y nadie la cerró, en ámbar), "Por llegar" (con la línea de "ahora" si alguna ya debía haber
 * llegado) y "Terminadas", plegadas en una línea ("4 atendidas · 1 no asistió") que se abre con "Ver". Dentro de cada
 * parte, por hora, para que una fila no salte de lugar mientras se la busca.
 *
 * Cada cita ocupa dos líneas: la hora con el nombre, el estado y los avisos; debajo, la duración con el tratamiento,
 * el celular y (con varios doctores, en "Todos") el doctor. El nombre muy largo llega a dos líneas y se lee entero al
 * pasar el ratón. El primer botón es el paso que sigue (confirmar, llegó, atender, cobrar, reagendar) y se ve como
 * botón; los demás quedan más discretos. Quien está en sala muestra cuánto lleva esperando. Con más de un doctor,
 * quien ve todas las agendas puede ver la de uno solo ("Todos · Dra. Ana · Dr. Jassmanny") y la elección se recuerda.
 */
class TodayAppointments extends TableWidget
{
    use RefreshesWithToday;

    /** La agenda de hoy se ve apenas abre "Hoy", sin un recuadro vacío mientras carga. */
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** El doctor elegido arriba de la agenda; null es "Todos". */
    #[Session]
    public ?int $doctor = null;

    /** Si se ven las terminadas (atendidas sin deuda y las que no asistió) o solo su resumen. */
    #[Session]
    public bool $showFinished = false;

    /** Las partes de la agenda, en su orden (orderByWhatNeedsAction), con el rótulo que va encima de cada una. */
    private const PARTS = [
        'inClinic' => 'En la clínica',
        'toCollect' => 'Por cobrar',
        'unclosed' => 'Sin cerrar',
        'toCome' => 'Por llegar',
        'finished' => 'Terminadas',
    ];

    /** La próxima cita que todavía no empieza: antes de ella va la línea de "ahora" (nextId). */
    private int|false|null $nextId = null;

    /** Si cada fila dice de qué doctor es: con varios doctores y en "Todos" (showsDoctorInRows). */
    private ?bool $showsDoctorInRows = null;

    /** @var array{date: CarbonImmutable|null, appointments: int}|false|null Si hoy no se atiende: el próximo día (closedToday). */
    private array|false|null $closedToday = null;

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        $seesMoney = $user->can('viewAny', Charge::class);

        // El doctor elegido arriba puede cambiar en esta misma petición (showDoctor): lo que depende de él se
        // calcula al pintar, no al armar la tabla.
        return $table
            ->heading('Agenda de hoy')
            ->header(fn () => ($doctors = $this->doctorOptions($user)) === [] ? null : view('filament.widgets.today-appointments-header', ['doctors' => $doctors]))
            ->query(fn () => $this->dayQuery()
                ->with(['patient' => fn ($patient) => $patient->with('account')->when($seesMoney, fn ($q) => $q->withBalance()), 'treatment', 'doctor'])
                ->when($this->hidesFinished($seesMoney), fn (Builder $query) => $query->whereNot(fn (Builder $pending) => self::whereFinished($pending, $seesMoney))))
            ->columns([
                Split::make([
                    TextColumn::make('starts_at')
                        ->formatStateUsing(fn (Appointment $record) => $record->starts_at->format('H:i'))
                        ->description(fn (Appointment $record) => Dates::duration($record->starts_at->diffInMinutes($record->ends_at)))
                        ->weight(FontWeight::Bold)
                        ->extraAttributes(['class' => 'dash-appt-time'])
                        ->grow(false),
                    Stack::make([
                        // Arriba, el nombre y a continuación el estado y los avisos; si no entran, bajan debajo del nombre.
                        Split::make([
                            TextColumn::make('patient.full_name')
                                ->weight(FontWeight::SemiBold)
                                ->wrap()
                                ->lineClamp(2)
                                // Los valores de extraAttributes no se escapan al pintarse: el nombre lo escribe cualquiera.
                                ->extraAttributes(fn (?string $state) => ['class' => 'dash-appt-name', 'title' => e($state)])
                                ->grow(false),
                            Split::make([
                                TextColumn::make('status')->badge()->grow(false),
                                TextColumn::make('waiting')
                                    ->state(fn (Appointment $record) => self::waitingLabel($record))
                                    ->icon('heroicon-m-clock')
                                    ->color(fn (Appointment $record) => $record->waitsTooLong() ? 'danger' : 'warning')
                                    ->size(TextSize::Small)
                                    ->grow(false),
                                AppointmentsTable::flagsColumn(withBalance: $seesMoney)->wrap()->grow(false),
                            ])->extraAttributes(['class' => 'dash-appt-tags'])->grow(false),
                        ])->extraAttributes(['class' => 'dash-appt-line']),
                        // Abajo, en una línea: el tratamiento, el celular y, con varios doctores en "Todos", el doctor.
                        TextColumn::make('details')
                            ->state(fn (Appointment $record) => $this->details($record))
                            ->color('gray')
                            ->extraAttributes(fn (Appointment $record) => ['class' => 'dash-appt-details', 'title' => e($this->details($record))]),
                    ]),
                ]),
            ])
            // Un rótulo encima de cada parte ("En la clínica", "Por cobrar"...): el orden lo pone defaultSort, con la
            // misma regla (part), así que el grupo no reordena nada.
            ->defaultGroup(Group::make('part')
                ->getKeyFromRecordUsing(fn (Appointment $record) => self::part($record, $seesMoney))
                ->getTitleFromRecordUsing(fn (Appointment $record) => self::PARTS[self::part($record, $seesMoney)])
                ->titlePrefixedWithLabel(false)
                ->orderQueryUsing(fn (Builder $query) => $query))
            ->extraAttributes(['class' => 'dash-agenda'])
            ->defaultSort(fn (Builder $query) => self::orderByWhatNeedsAction($query, $seesMoney))
            ->paginated(false)
            ->contentFooter(fn () => $this->finishedFooter($seesMoney))
            ->recordClasses(fn (Appointment $record) => [
                'dash-appt',
                'dash-appt-'.$record->status->value,
                'dash-appt-next' => $record->getKey() === $this->nextId(),
                'dash-appt-unclosed' => $record->endedWithoutArriving() || $record->isForgottenInClinic(),
                'dash-appt-owes' => $seesMoney && self::attendedAndOwes($record),
            ])
            ->recordUrl(fn (Appointment $record) => AppointmentResource::getUrl('view', ['record' => $record]))
            ->recordActions(AppointmentActions::all())
            ->poll('30s')
            ->emptyStateHeading(fn () => $this->emptyHeading($user))
            ->emptyStateDescription(fn () => $this->emptyDescription($user))
            ->emptyStateIcon('heroicon-o-calendar')
            ->emptyStateActions([
                // "Ver mañana" o "Ver el lunes": la Agenda en el próximo día con atención.
                Action::make('seeNextDay')
                    ->label(fn () => 'Ver '.self::dayName($this->nextOpenDay() ?? today()))
                    ->color('gray')
                    ->url(fn () => Agenda::getUrl(['date' => ($this->nextOpenDay() ?? today())->toDateString()]))
                    ->visible(fn () => $this->nextOpenDay() !== null),
            ]);
    }

    /** Llegó, pasó a consulta, se confirmó o se cobró: la sala, los números y lo que toca cambian con la fila. */
    protected function afterActionCalled(Action $action): void
    {
        $this->dispatch(Dashboard::CHANGED);
    }

    /** "Todos" o un doctor, arriba de la agenda: lo que se ve cambia al momento y se recuerda en la sesión. */
    public function showDoctor(?int $doctor = null): void
    {
        $this->doctor = $doctor;
        $this->nextId = null;
        $this->closedToday = null;
        $this->showsDoctorInRows = null;
    }

    /** Se atendió y debe: no queda en tenue ni se pliega, con su "Debe S/" y "Cobrar" a la vista hasta que pague. */
    private static function attendedAndOwes(Appointment $record): bool
    {
        /** @var AppointmentStatus $status */
        $status = $record->status;
        /** @var Patient $patient */
        $patient = $record->patient;

        return $status === AppointmentStatus::Completed && (float) $patient->getAttribute('balance_due') > 0;
    }

    /** "Ver" u "Ocultar" las terminadas, al pie de la agenda: se recuerda en la sesión. */
    public function toggleFinished(): void
    {
        $this->showFinished = ! $this->showFinished;
    }

    /**
     * Las citas de hoy (u otro día) de la agenda que se ve, sin las canceladas.
     *
     * @return Builder<Appointment>
     */
    private function dayQuery(?CarbonInterface $day = null): Builder
    {
        /** @var Builder<Appointment> $query */
        $query = AppointmentResource::getEloquentQuery();

        return $query
            ->when($this->shownDoctor(), fn (Builder $query, int $doctor) => $query->where('doctor_id', $doctor))
            ->startsOn($day ?? today())
            ->where('status', '!=', AppointmentStatus::Cancelled);
    }

    /**
     * Si hoy no se atiende (sin horario, feriado o bloqueo de todo el día, como en la Agenda): el próximo día con
     * atención, dentro de un mes, y cuántas citas tiene. Null si hoy se atiende. Solo se pide con la agenda vacía.
     *
     * @return array{date: CarbonImmutable|null, appointments: int}|null
     */
    private function closedToday(): ?array
    {
        if ($this->closedToday === null) {
            $first = app(OpenDays::class)->firstFrom(today(), $this->doctorsShown());

            $this->closedToday = $first?->isToday() ? false : [
                'date' => $first,
                'appointments' => $first === null ? 0 : $this->dayQuery($first)->count(),
            ];
        }

        return $this->closedToday ?: null;
    }

    /**
     * Los doctores de la agenda que se ve: el elegido arriba, el propio (al doctor) o todos los que atienden.
     *
     * @return Collection<int, Doctor>
     */
    private function doctorsShown(): Collection
    {
        /** @var User $user */
        $user = auth()->user();
        $only = $this->shownDoctor() ?? ($user->managesAllAgendas() ? null : $user->doctorId());

        return Doctor::query()->active()->when($only !== null, fn (Builder $query) => $query->whereKey($only))->get();
    }

    /** Si hoy no se atiende, el próximo día con atención; null si hoy se atiende o si en un mes no se atiende. */
    private function nextOpenDay(): ?CarbonImmutable
    {
        return $this->closedToday()['date'] ?? null;
    }

    /** "No hay citas hoy" o, si hoy no se atiende, quién no atiende: el consultorio, el doctor elegido o uno mismo. */
    private function emptyHeading(User $user): string
    {
        $shown = $this->shownDoctor();

        if ($this->closedToday() === null) {
            return $shown !== null ? 'No tiene citas hoy' : 'No hay citas hoy';
        }

        return match (true) {
            $shown !== null => Doctor::query()->whereKey($shown)->value('name').' no atiende hoy',
            ! $user->managesAllAgendas() => 'Hoy no atiendes',
            default => 'Hoy el consultorio no atiende',
        };
    }

    /** Si hoy no se atiende, cuántas citas hay el próximo día con atención ("Mañana hay 6 citas."). */
    private function emptyDescription(User $user): string
    {
        $closed = $this->closedToday();

        if ($closed === null) {
            return 'Las citas de hoy aparecen aquí en orden, con el botón del paso que sigue.';
        }

        if ($closed['date'] === null) {
            return 'En las próximas semanas no hay días de atención.';
        }

        $verb = match (true) {
            $this->shownDoctor() !== null => 'tiene',
            ! $user->managesAllAgendas() => 'tienes',
            default => 'hay',
        };
        $count = $closed['appointments'];

        return ucfirst(self::dayName($closed['date'])).' '.match ($count) {
            0 => 'todavía no '.$verb.' citas',
            1 => $verb.' 1 cita',
            default => "{$verb} {$count} citas",
        }.'.';
    }

    /** "mañana", "el lunes" (en esta semana) o "el lunes 7 de diciembre". */
    private static function dayName(CarbonInterface $day): string
    {
        return match (true) {
            $day->isTomorrow() => 'mañana',
            $day->lte(today()->addDays(6)) => 'el '.$day->translatedFormat('l'),
            default => 'el '.$day->translatedFormat('l j \d\e F'),
        };
    }

    /**
     * Terminadas: no asistió, o se atendió y no debe nada. A quien no ve el dinero (el doctor), toda atendida le
     * queda terminada: lo que falte cobrar es de recepción.
     *
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    private static function whereFinished(Builder $query, bool $seesMoney): Builder
    {
        return $query->where(fn (Builder $finished) => $finished
            ->where('status', AppointmentStatus::NoShow)
            ->orWhere(fn (Builder $attended) => $attended
                ->where('status', AppointmentStatus::Completed)
                ->when($seesMoney, fn (Builder $paid) => $paid->whereNotIn('patient_id', Patient::query()->owing()->select('patients.id')))));
    }

    /**
     * Las terminadas se pliegan mientras quede otra cosa en la agenda. Si ya no queda nada más (al final del día),
     * se ven: no hay nada que esconder.
     */
    private function hidesFinished(bool $seesMoney): bool
    {
        return ! $this->showFinished && $this->hasOthersThanFinished($seesMoney);
    }

    private function hasOthersThanFinished(bool $seesMoney): bool
    {
        return $this->dayQuery()->whereNot(fn (Builder $pending) => self::whereFinished($pending, $seesMoney))->exists();
    }

    /**
     * En qué parte de la agenda va la cita (el rótulo de encima), con la misma regla que su orden en
     * orderByWhatNeedsAction: si no coincidieran, un rótulo saldría dos veces.
     *
     * @return key-of<self::PARTS>
     */
    private static function part(Appointment $record, bool $seesMoney): string
    {
        /** @var AppointmentStatus $status */
        $status = $record->status;

        return match (true) {
            in_array($status, AppointmentStatus::inClinic(), true) && ! $record->isForgottenInClinic() => 'inClinic',
            $seesMoney && self::attendedAndOwes($record) => 'toCollect',
            $record->endedWithoutArriving() || $record->isForgottenInClinic() => 'unclosed',
            $status->isChangeable() => 'toCome',
            default => 'finished',
        };
    }

    /**
     * Arriba lo que pide algo ahora; después lo que viene; al final lo terminado. Dentro de cada parte, por hora:
     *  0. En atención o en sala (no la que sigue así horas después de su hora: esa es una cita sin cerrar).
     *  1. Se atendió y debe (solo para quien cobra): está en el mostrador.
     *  2. Sin cerrar: pasó su hora y nadie marcó si vino, o sigue en sala o en atención horas después.
     *  3. Por llegar.
     *  4. Terminadas.
     *
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    private static function orderByWhatNeedsAction(Builder $query, bool $seesMoney): Builder
    {
        $owing = Patient::query()->owing()->select('patients.id');
        [$pending, $confirmed] = array_map(fn (AppointmentStatus $status) => $status->value, AppointmentStatus::changeable());
        [$checkedIn, $inProgress] = array_map(fn (AppointmentStatus $status) => $status->value, AppointmentStatus::inClinic());
        $forgottenBefore = now()->subHours(Appointment::WAIT_EXPIRES_HOURS);

        return $query
            ->orderByRaw(
                'case'
                .' when status in (?, ?) and ends_at > ? then 0'
                .($seesMoney ? ' when status = ? and patient_id in ('.$owing->toSql().') then 1' : '')
                .' when status in (?, ?) and ends_at < ? then 2'
                .' when status in (?, ?) then 2'
                .' when status in (?, ?) then 3'
                .' else 4 end',
                [
                    $checkedIn, $inProgress, $forgottenBefore,
                    ...($seesMoney ? [AppointmentStatus::Completed->value, ...$owing->getBindings()] : []),
                    $pending, $confirmed, now(),
                    $checkedIn, $inProgress,
                    $pending, $confirmed,
                ],
            )
            ->orderBy('starts_at');
    }

    /**
     * Al pie, cuando hay terminadas: cuántas ("Terminadas: 4 atendidas · 1 no asistió") y "Ver" u "Ocultar". Si no
     * queda nada más en la agenda, las terminadas ya están a la vista y no hace falta.
     */
    private function finishedFooter(bool $seesMoney): ?View
    {
        if (! $this->hasOthersThanFinished($seesMoney)) {
            return null;
        }

        $finished = self::whereFinished($this->dayQuery(), $seesMoney)->toBase()->pluck('status')->countBy();
        $attended = $finished->get(AppointmentStatus::Completed->value, 0);
        $missed = $finished->get(AppointmentStatus::NoShow->value, 0);

        if ($attended + $missed === 0) {
            return null;
        }

        return view('filament.widgets.today-appointments-finished', [
            'summary' => collect([
                match ($attended) {
                    0 => null,
                    1 => '1 atendida',
                    default => "{$attended} atendidas",
                },
                match ($missed) {
                    0 => null,
                    1 => '1 no asistió',
                    default => "{$missed} no asistieron",
                },
            ])->filter()->implode(' · '),
            'shown' => $this->showFinished,
        ]);
    }

    /**
     * La segunda línea de la fila: el tratamiento, el celular y, con varios doctores en "Todos", el doctor. El celular
     * no se parte en dos si la línea baja (en el celular, donde se lee entera).
     */
    private function details(Appointment $record): string
    {
        /** @var Patient $patient */
        $patient = $record->patient;
        /** @var Account|null $account */
        $account = $patient->account;
        /** @var Treatment|null $treatment */
        $treatment = $record->treatment;
        /** @var Doctor|null $doctor */
        $doctor = $record->doctor;

        return collect([
            $treatment?->name,
            $account ? str_replace(' ', "\u{00A0}", Phone::display($account->phone)) : null,
            $this->showsDoctorInRows() ? $doctor?->name : null,
        ])->filter()->implode(' · ');
    }

    /** Con un solo doctor, o con uno elegido arriba, no hace falta decir de quién es cada cita. */
    private function showsDoctorInRows(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $this->showsDoctorInRows ??= $this->shownDoctor() === null && AppointmentsTable::showsDoctor($user);
    }

    /** De quién es la agenda que se ve: la del doctor elegido arriba; null es de todos (al doctor, la suya). */
    private function shownDoctor(): ?int
    {
        /** @var User $user */
        $user = auth()->user();

        return collect($this->doctorOptions($user))->firstWhere('active', true)['id'] ?? null;
    }

    /**
     * Las opciones de arriba de la agenda: "Todos" y cada doctor activo. Solo para quien ve todas las agendas y
     * con más de un doctor; si el elegido dejó de atender, vuelve a "Todos".
     *
     * @return list<array{id: int|null, name: string, active: bool}>
     */
    private function doctorOptions(User $user): array
    {
        if (! AppointmentsTable::showsDoctor($user)) {
            return [];
        }

        $doctors = Doctor::query()->active()->orderBy('sort_order')->pluck('name', 'id');
        $chosen = $this->doctor !== null && $doctors->has($this->doctor) ? $this->doctor : null;

        return [
            ['id' => null, 'name' => 'Todos', 'active' => $chosen === null],
            ...$doctors->map(fn (string $name, int $id) => ['id' => $id, 'name' => $name, 'active' => $id === $chosen])->values()->all(),
        ];
    }

    /**
     * "Esperando 25 min" para quien está en sala. Como en la sala de espera (Appointment::waitingNow), quien sigue
     * "en sala" horas después de su cita ya no espera: es una cita que nadie cerró, y no se le pone "Esperando 9 h".
     */
    private static function waitingLabel(Appointment $record): ?string
    {
        /** @var CarbonInterface $endsAt */
        $endsAt = $record->ends_at;
        $minutes = $record->waitingMinutes();

        return $minutes !== null && $endsAt->gt(now()->subHours(Appointment::WAIT_EXPIRES_HOURS)) ? 'Esperando '.Dates::duration($minutes) : null;
    }

    /**
     * La línea de "ahora" va solo dentro de "Por llegar", y solo si alguna de arriba ya debía haber llegado (empezó su
     * hora y todavía no termina): separa a quien se atrasa de lo que viene. Si no, quedaría pegada al rótulo.
     */
    private function nextId(): ?int
    {
        if ($this->nextId === null) {
            $toCome = $this->dayQuery()->whereIn('status', AppointmentStatus::changeable());
            $someoneIsLate = (clone $toCome)->where('starts_at', '<', now())->where('ends_at', '>=', now())->exists();

            $this->nextId = $someoneIsLate
                ? $toCome->where('starts_at', '>=', now())->orderBy('starts_at')->toBase()->value('id') ?? false
                : false;
        }

        return $this->nextId ?: null;
    }
}
