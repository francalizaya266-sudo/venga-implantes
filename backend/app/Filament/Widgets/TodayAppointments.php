<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Filament\Actions\AppointmentActions;
use App\Filament\Concerns\RefreshesWithToday;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Tables\AppointmentsTable;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Support\Dates;
use App\Support\Phone;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Session;

/**
 * La agenda de hoy, en el orden en que pide algo: arriba quien está en atención o en sala, quien terminó y debe
 * (para quien cobra) y las citas que pasaron su hora sin cerrar (en ámbar); después la línea de "ahora" y lo que
 * viene; al final lo terminado, plegado en una línea ("4 atendidas · 1 no asistió") que se abre con "Ver". Dentro
 * de cada parte, por hora, para que una fila no salte de lugar mientras se la busca.
 *
 * El primer botón de cada cita es el paso que sigue (confirmar, llegó, atender, cobrar, reagendar) y se ve como
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

    /** La próxima cita que todavía no empieza: antes de ella va la línea de "ahora". */
    private int|false|null $nextId = null;

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
                        TextColumn::make('patient.full_name')
                            ->weight(FontWeight::SemiBold)
                            ->wrap(),
                        TextColumn::make('treatment.name')
                            ->color('gray')
                            ->size(TextSize::Small)
                            ->wrap(),
                        Split::make([
                            TextColumn::make('phone')
                                ->state(fn (Appointment $record) => $record->patient->account ? Phone::display($record->patient->account->phone) : null)
                                ->icon('heroicon-m-phone')
                                ->color('gray')
                                ->size(TextSize::Small)
                                ->grow(false),
                            TextColumn::make('doctor.name')
                                ->icon('heroicon-m-user')
                                ->color('gray')
                                ->size(TextSize::Small)
                                ->visible(fn () => $this->shownDoctor() === null && AppointmentsTable::showsDoctor($user))
                                ->grow(false),
                        ]),
                    ])->space(1),
                    Stack::make([
                        TextColumn::make('status')->badge(),
                        TextColumn::make('waiting')
                            ->state(fn (Appointment $record) => self::waitingLabel($record))
                            ->icon('heroicon-m-clock')
                            ->color(fn (Appointment $record) => $record->waitsTooLong() ? 'danger' : 'warning')
                            ->size(TextSize::Small),
                        AppointmentsTable::flagsColumn(withBalance: $seesMoney),
                    ])->space(1)->alignEnd()->grow(false),
                ]),
            ])
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
            ->emptyStateHeading(fn () => $this->shownDoctor() !== null ? 'No tiene citas hoy' : 'No hay citas hoy')
            ->emptyStateDescription('Las citas de hoy aparecen aquí en orden, con el botón del paso que sigue.')
            ->emptyStateIcon('heroicon-o-calendar');
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
     * Las citas de hoy de la agenda que se ve, sin las canceladas.
     *
     * @return Builder<Appointment>
     */
    private function dayQuery(): Builder
    {
        /** @var Builder<Appointment> $query */
        $query = AppointmentResource::getEloquentQuery();

        return $query
            ->when($this->shownDoctor(), fn (Builder $query, int $doctor) => $query->where('doctor_id', $doctor))
            ->whereDate('starts_at', today())
            ->where('status', '!=', AppointmentStatus::Cancelled);
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

    private function nextId(): ?int
    {
        if ($this->nextId === null) {
            $shownDoctor = $this->shownDoctor();

            $this->nextId = AppointmentResource::getEloquentQuery()
                ->when($shownDoctor, fn ($query) => $query->where('doctor_id', $shownDoctor))
                ->whereDate('starts_at', today())
                ->where('starts_at', '>=', now())
                ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Confirmed])
                ->orderBy('starts_at')
                ->toBase()
                ->value('id') ?? false;
        }

        return $this->nextId ?: null;
    }
}
