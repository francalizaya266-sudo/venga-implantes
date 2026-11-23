<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Filament\Actions\AppointmentActions;
use App\Filament\Actions\RegisterPaymentAction;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\CashClosing;
use App\Services\Billing\DaySummary;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Si el día cuadra: lo que había para cobrar, lo cobrado y quién se fue debiendo (con "Cobrar"), las citas que
 * quedaron sin cerrar (con "Atendido" y "No asistió", para resolverlas ahí mismo), las que siguen en curso y el
 * arqueo del efectivo. Cerrado el día, sus pagos ya no cambian sin quien administra, y a quien administra le llega el
 * resumen del día (DaySummary).
 */
class CashClosingCheck extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithPageFilters;
    use InteractsWithSchemas {
        // El de los esquemas resuelve las acciones desde la vista ($this->collectAction).
        InteractsWithSchemas::__get insteadof InteractsWithPageFilters;
    }

    protected string $view = 'filament.widgets.cash-closing-check';

    protected int|string|array $columnSpan = 'full';

    private ?CashClosing $closing = null;

    /** @var array<string, mixed>|null */
    private ?array $closingFilters = null;

    /** @var array<int, Appointment|null> Las citas de las filas con botones, buscadas una vez por pedido. */
    private array $listedAppointments = [];

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Payment::class) ?? false;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $closing = $this->closing();
        $wholeDay = $closing->receivedBy === null;

        return [
            'wholeDay' => $wholeDay,
            'collected' => $closing->total(),
            'expected' => $closing->expected(),
            'pending' => $wholeDay ? $closing->pending() : collect(),
            'pendingTotal' => $wholeDay ? $closing->pendingTotal() : 0.0,
            'unclosed' => $wholeDay ? $closing->unclosedAppointments()->map(fn (Appointment $appointment) => self::row(
                $appointment,
                $appointment->treatment->name.' · '.mb_strtolower($appointment->status->getLabel()).': '.self::unclosedHint($appointment->status),
            )) : collect(),
            'inCourse' => $wholeDay ? $closing->inCourseAppointments()->map(fn (Appointment $appointment) => self::row(
                $appointment,
                $appointment->treatment->name.' · '.$appointment->doctor->name.' · '.mb_strtolower($appointment->status->getLabel()),
            )) : collect(),
            'earlierUnclosed' => $wholeDay && $closing->date->isToday() ? $closing->earlierUnclosedCount() : 0,
            'unclosedUrl' => AppointmentResource::getUrl('index', ['tab' => 'unclosed']),
            'record' => $closing->record(),
        ];
    }

    /**
     * "Atendido", el mismo de la agenda: la cita queda atendida y se carga lo programado (si no lo paga, aparece en
     * "Se fueron debiendo"), con sus materiales y sus cuidados.
     */
    public function completeAction(): Action
    {
        return $this->forListedAppointment(AppointmentActions::complete())->color('primary');
    }

    /** "No asistió", el mismo de la agenda: cuenta como falta y entra en "Faltaron o cancelaron" para darle otra hora. */
    public function missedAction(): Action
    {
        return $this->forListedAppointment(AppointmentActions::missed());
    }

    /** Cobrar a quien se fue debiendo, desde el mismo cierre. */
    public function collectAction(): Action
    {
        return RegisterPaymentAction::make(fn (array $arguments) => Patient::query()->find($arguments['patient'] ?? null))
            ->name('collect')
            ->label('Cobrar')
            ->color('success')
            ->size('sm')
            ->after(fn () => $this->closing = null);
    }

    /** El arqueo: el fondo con que se abrió, el efectivo que debe haber y el que se contó. */
    public function closeDayAction(): Action
    {
        return Action::make('closeDay')
            ->label('Cerrar el día')
            ->icon(Heroicon::OutlinedLockClosed)
            ->visible(fn () => $this->closing()->receivedBy === null && $this->closing()->record() === null)
            ->modalHeading(fn () => 'Cerrar la caja del '.$this->closing()->date->translatedFormat('l j \d\e F'))
            ->modalDescription('Cuenta el efectivo de la caja. Después del cierre, anular un pago de este día o registrar uno con esta fecha lo hace solo quien administra.')
            ->modalSubmitActionLabel('Cerrar el día')
            ->modalWidth('lg')
            ->fillForm(fn () => ['opening_cash' => $this->closing()->suggestedOpeningCash()])
            ->schema([
                TextInput::make('opening_cash')
                    ->label('Fondo con que se abrió la caja')
                    ->prefix('S/')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->live(onBlur: true),
                Text::make(fn (Get $get) => 'Debe haber '.Money::format((float) $get('opening_cash') + $this->closing()->cashInDrawer()).': el fondo más '.Money::format($this->closing()->cash()).' cobrados en efectivo'
                    .($this->closing()->cashExpenses() > 0 ? ', menos '.Money::format($this->closing()->cashExpenses()).' de gastos pagados en efectivo.' : '.')),
                TextInput::make('counted_cash')
                    ->label('Efectivo contado')
                    ->prefix('S/')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->live(onBlur: true),
                Text::make(fn (Get $get) => self::differenceText($this->difference($get)))
                    ->color(fn (Get $get) => abs($this->difference($get) ?? 0) < 0.005 ? 'success' : 'warning')
                    ->visible(fn (Get $get) => filled($get('counted_cash'))),
                TextInput::make('notes')
                    ->label('Observaciones')
                    ->placeholder('Por ejemplo: se dio vuelto de más')
                    ->maxLength(255)
                    ->required(fn (Get $get) => abs($this->difference($get) ?? 0) >= 0.005)
                    ->validationMessages(['required' => 'Si no cuadra, anota por qué.']),
                Text::make(fn () => $this->leftOpenText())
                    ->color('warning')
                    ->visible(fn () => $this->leftOpenText() !== null),
            ])
            ->action(function (array $data) {
                $record = $this->closing()->close((float) $data['opening_cash'], (float) $data['counted_cash'], $data['notes'] ?? null, self::user());
                // El resumen es un extra: si algo falla al armarlo, el día queda cerrado igual (y el error, en el registro).
                $summaries = rescue(fn () => app(DaySummary::class)->send($this->closing(), $record), 0);

                Notification::make()
                    ->success()
                    ->title('Día cerrado')
                    ->body(collect([
                        self::differenceText($record->difference()),
                        $summaries > 0 ? 'El resumen del día para quien administra quedó en Avisos, listo para enviar por WhatsApp.' : null,
                    ])->filter()->implode('<br>'))
                    ->send();
            });
    }

    /** Solo quien administra reabre un día cerrado (para corregir un pago de ese día). */
    public function reopenDayAction(): Action
    {
        return Action::make('reopenDay')
            ->label('Reabrir el día')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('gray')
            ->size('sm')
            ->visible(fn () => self::user()->isAdmin() && $this->closing()->record() !== null)
            ->requiresConfirmation()
            ->modalHeading('¿Reabrir el día?')
            ->modalDescription('Se podrán anular y registrar pagos de este día. Después vuelve a cerrarlo con el efectivo contado. Queda registrado quién lo reabrió.')
            ->modalSubmitActionLabel('Reabrir')
            ->action(function () {
                $this->closing()->record()?->delete();

                Notification::make()->success()->title('Día reabierto')->send();
            });
    }

    /** "Cuadra", "Faltan S/ 20.00" o "Sobran S/ 5.00". */
    public static function differenceText(?float $difference): string
    {
        return match (true) {
            $difference === null => '',
            abs($difference) < 0.005 => 'Cuadra: el efectivo contado es el que debía haber.',
            $difference < 0 => 'Faltan '.Money::format(-$difference).' en la caja.',
            default => 'Sobran '.Money::format($difference).' en la caja.',
        };
    }

    /** Qué falta para cerrar la cita: si no se marcó la llegada, saber si vino; si vino, terminar la atención. */
    private static function unclosedHint(AppointmentStatus $status): string
    {
        return in_array($status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true)
            ? 'marca si vino o si no asistió'
            : 'falta terminar la atención y cobrar';
    }

    /**
     * Una cita en las listas del cuadre: "09:00 · Rosa Huamán", con su detalle debajo.
     *
     * @return array{id: int, time: string, name: string, detail: string, url: string}
     */
    private static function row(Appointment $appointment, string $detail): array
    {
        return [
            'id' => $appointment->getKey(),
            'time' => $appointment->starts_at->format('H:i'),
            'name' => $appointment->patient->full_name,
            'detail' => $detail,
            'url' => AppointmentResource::getUrl('view', ['record' => $appointment]),
        ];
    }

    /** La acción de la agenda como botón chico en la fila, sobre la cita que llega en los argumentos. */
    private function forListedAppointment(Action $action): Action
    {
        return $action
            ->record(fn (array $arguments): ?Appointment => $this->listedAppointment($arguments['appointment'] ?? null))
            ->size(Size::Small)
            ->after(function (): void {
                $this->closing = null;
                $this->listedAppointments = [];
            });
    }

    /**
     * La cita de una fila. Cada botón la pide varias veces al pintarse (si se ve, si se puede, qué pregunta): se busca
     * una sola vez, con lo que esos botones miran. Antes eran unas 60 consultas por fila.
     */
    private function listedAppointment(mixed $id): ?Appointment
    {
        if (! is_numeric($id)) {
            return null;
        }

        if (! array_key_exists((int) $id, $this->listedAppointments)) {
            $this->listedAppointments[(int) $id] = AppointmentResource::getEloquentQuery()->with(['consultation', 'planItem'])->find($id);
        }

        return $this->listedAppointments[(int) $id];
    }

    private function difference(Get $get): ?float
    {
        if (! is_numeric($get('counted_cash'))) {
            return null;
        }

        return round((float) $get('counted_cash') - ((float) $get('opening_cash') + $this->closing()->cashInDrawer()), 2);
    }

    /** Lo que sigue pendiente al cerrar: no impide cerrar, pero conviene verlo antes. */
    private function leftOpenText(): ?string
    {
        $closing = $this->closing();
        $pending = $closing->pending()->count();
        $unclosed = $closing->unclosedAppointments()->count();
        $inCourse = $closing->inCourseAppointments()->count();

        $parts = collect([
            $pending > 0 ? 'faltó cobrar '.Money::format($closing->pendingTotal()).' a '.$pending.($pending === 1 ? ' paciente' : ' pacientes') : null,
            $unclosed > 0 ? $unclosed.($unclosed === 1 ? ' cita quedó sin cerrar' : ' citas quedaron sin cerrar') : null,
            $inCourse > 0 ? $inCourse.($inCourse === 1 ? ' cita sigue en curso' : ' citas siguen en curso') : null,
        ])->filter();

        return $parts->isEmpty() ? null : 'Antes de cerrar: '.$parts->join(', ', ' y ').'.';
    }

    /** El cierre del día y la persona elegidos; se arma una vez por pedido (cada parte lo consulta varias veces). */
    private function closing(): CashClosing
    {
        $filters = $this->pageFilters ?? [];

        if ($this->closing === null || $this->closingFilters !== $filters) {
            $this->closing = CashClosing::fromFilters($filters);
            $this->closingFilters = $filters;
        }

        return $this->closing;
    }

    private static function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
