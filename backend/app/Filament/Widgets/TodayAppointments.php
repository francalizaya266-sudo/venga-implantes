<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\TodayView;
use App\Filament\Actions\AppointmentActions;
use App\Filament\Concerns\RefreshesWithToday;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Tables\AppointmentsTable;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Doctor;
use App\Models\User;
use App\Support\Dates;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\Session;

/**
 * La agenda de hoy como línea de tiempo: la hora grande a la izquierda, el color de su estado en el
 * borde y una línea de "ahora" antes de la próxima cita. El primer botón de cada cita es el paso que
 * sigue (confirmar, llegó, atender, cobrar) y se ve como botón; los demás quedan más discretos.
 * Quien está en sala muestra cuánto lleva esperando. Con más de un doctor, en "Toda la clínica" se puede
 * ver la de uno solo ("Todos · Dra. Ana · Dr. Jassmanny") y la elección se recuerda.
 */
class TodayAppointments extends TableWidget
{
    use RefreshesWithToday;

    /** Desde aquí la espera se marca en rojo: alguien tiene que ver qué pasa. */
    public const LONG_WAIT_MINUTES = 20;

    /** La agenda de hoy se ve apenas abre "Hoy", sin un recuadro vacío mientras carga. */
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** El doctor elegido arriba de la agenda en "Toda la clínica"; null es "Todos". */
    #[Session]
    public ?int $doctor = null;

    /** La próxima cita que todavía no empieza: antes de ella va la línea de "ahora". */
    private int|false|null $nextId = null;

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        $onlyDoctor = self::onlyDoctor($user);
        $seesMoney = $user->can('viewAny', Charge::class);

        // El doctor elegido arriba puede cambiar en esta misma petición (showDoctor): lo que depende de él se
        // calcula al pintar, no al armar la tabla.
        return $table
            ->heading($onlyDoctor !== null ? 'Mi agenda de hoy' : 'Agenda de hoy')
            ->header(fn () => ($doctors = $this->doctorOptions($user)) === [] ? null : view('filament.widgets.today-appointments-header', ['doctors' => $doctors]))
            ->query(fn () => AppointmentResource::getEloquentQuery()
                ->with(['patient' => fn ($patient) => $patient->with('account')->when($seesMoney, fn ($q) => $q->withBalance()), 'treatment', 'doctor'])
                ->when($this->shownDoctor(), fn ($query, int $doctor) => $query->where('doctor_id', $doctor))
                ->whereDate('starts_at', today())
                ->where('status', '!=', AppointmentStatus::Cancelled))
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
                            ->state(fn (Appointment $record) => ($minutes = $record->waitingMinutes()) !== null ? 'Esperando '.Dates::duration($minutes) : null)
                            ->icon('heroicon-m-clock')
                            ->color(fn (Appointment $record) => $record->waitingMinutes() >= self::LONG_WAIT_MINUTES ? 'danger' : 'warning')
                            ->size(TextSize::Small),
                        AppointmentsTable::flagsColumn(withBalance: $seesMoney),
                    ])->space(1)->alignEnd()->grow(false),
                ]),
            ])
            ->defaultSort('starts_at')
            ->paginated(false)
            ->recordClasses(fn (Appointment $record) => [
                'dash-appt',
                'dash-appt-'.$record->status->value,
                'dash-appt-next' => $record->getKey() === $this->nextId(),
            ])
            ->recordUrl(fn (Appointment $record) => AppointmentResource::getUrl('view', ['record' => $record]))
            ->recordActions(AppointmentActions::all())
            ->poll('30s')
            ->emptyStateHeading(fn () => $onlyDoctor === null && $this->shownDoctor() !== null ? 'No tiene citas hoy' : 'No hay citas hoy')
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

    /** En "Mi consulta" del administrador que atiende, solo su agenda (al doctor la lista ya le trae solo la suya). */
    private static function onlyDoctor(User $user): ?int
    {
        return $user->managesAllAgendas() && TodayView::current($user) === TodayView::Mine ? $user->doctorId() : null;
    }

    /** De quién es la agenda que se ve: la suya en "Mi consulta", o la del doctor elegido arriba; null es de todos. */
    private function shownDoctor(): ?int
    {
        /** @var User $user */
        $user = auth()->user();

        if (($onlyDoctor = self::onlyDoctor($user)) !== null) {
            return $onlyDoctor;
        }

        return collect($this->doctorOptions($user))->firstWhere('active', true)['id'] ?? null;
    }

    /**
     * Las opciones de arriba de la agenda: "Todos" y cada doctor activo. Solo para quien ve todas las agendas, en
     * "Toda la clínica" y con más de un doctor; si el elegido dejó de atender, vuelve a "Todos".
     *
     * @return list<array{id: int|null, name: string, active: bool}>
     */
    private function doctorOptions(User $user): array
    {
        if (self::onlyDoctor($user) !== null || ! AppointmentsTable::showsDoctor($user)) {
            return [];
        }

        $doctors = Doctor::query()->active()->orderBy('sort_order')->pluck('name', 'id');
        $chosen = $this->doctor !== null && $doctors->has($this->doctor) ? $this->doctor : null;

        return [
            ['id' => null, 'name' => 'Todos', 'active' => $chosen === null],
            ...$doctors->map(fn (string $name, int $id) => ['id' => $id, 'name' => $name, 'active' => $id === $chosen])->values()->all(),
        ];
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
