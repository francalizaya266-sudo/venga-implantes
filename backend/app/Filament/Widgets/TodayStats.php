<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\RescheduleRequestStatus;
use App\Filament\Concerns\RefreshesWithToday;
use App\Filament\Pages\Agenda;
use App\Filament\Pages\CashClosing as CashClosingPage;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\RescheduleRequests\RescheduleRequestResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\CashClosing;
use App\Support\Dates;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Los números del día: cuatro, en una fila y a la vista. Solo se marca el que espera una acción (alguien sin
 * confirmar, alguien que se fue debiendo) o una espera larga; el resto queda neutro para que lo urgente resalte.
 * Para quien ve todas las agendas (recepción y administración, también el administrador que atiende): las citas,
 * quién está en sala ahora (con scroll la sala puede quedar abajo, y una espera larga no debe pasar desapercibida),
 * por confirmar y lo cobrado; las solicitudes y los avisos van en "Por hacer hoy". Para el doctor: sus citas, sus
 * por confirmar, las solicitudes de cambio y los avisos por enviar; quién lo espera ya lo dice "Tu próximo paciente".
 * Cada número lleva a su lista.
 */
class TodayStats extends Widget
{
    use RefreshesWithToday;

    /** Los números del día se ven apenas abre "Hoy", sin un recuadro vacío mientras carga. */
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.today-stats';

    protected int|string|array $columnSpan = 'full';

    /** @return array{stats: list<array{label: string, value: string|int, hint: string, color: string, icon: string, url: string}>} */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->managesAllAgendas()) {
            // "Cobrado hoy" falta para quien no ve la caja; como va al final, la lista queda en orden.
            return ['stats' => array_filter([
                $this->appointments(),
                $this->waitingRoom(),
                $this->unconfirmed(),
                $this->collectedToday($user),
            ])];
        }

        // Al doctor la lista ya le trae solo lo suyo.
        return ['stats' => [
            $this->appointments('Mis citas de hoy'),
            $this->unconfirmed(),
            $this->pendingRequests(),
            $this->dueMessages(),
        ]];
    }

    /** @return array{label: string, value: int, hint: string, color: string, icon: string, url: string} */
    private function appointments(string $label = 'Citas de hoy'): array
    {
        // Las mismas que lista "Agenda de hoy", justo debajo: todas menos las canceladas.
        /** @var Builder<Appointment> $query */
        $query = AppointmentResource::getEloquentQuery();
        /** @var Collection<int, Appointment> $today */
        $today = $query
            ->startsOn(today())
            ->where('status', '!=', AppointmentStatus::Cancelled)
            ->withoutEagerLoads()
            ->get(['id', 'status', 'starts_at', 'ends_at']);
        $parts = $today->countBy(fn (Appointment $appointment): string => self::dayPart($appointment));
        $done = (int) $parts->get('done', 0);
        $here = (int) $parts->get('here', 0);
        $toCome = (int) $parts->get('toCome', 0);
        $unmarked = (int) $parts->get('unmarked', 0);
        $missed = (int) $parts->get('missed', 0);

        return [
            'label' => $label,
            'value' => $today->count(),
            // Cuánto va del día; "atendidas" siempre, para que el cero se lea como cero y no como olvido.
            'hint' => collect([
                $done === 1 ? '1 atendida' : "{$done} atendidas",
                $here > 0 ? "{$here} en la clínica" : null,
                $toCome > 0 ? "{$toCome} por llegar" : null,
                $unmarked > 0 ? "{$unmarked} sin cerrar" : null,
                match ($missed) {
                    0 => null,
                    1 => '1 no asistió',
                    default => "{$missed} no asistieron",
                },
            ])->filter()->implode(' · '),
            'color' => 'gray',
            'icon' => 'heroicon-o-calendar-days',
            // Las citas de hoy se ven en la Agenda: Citas ya no repite ese día en una pestaña.
            'url' => Agenda::getUrl(),
        ];
    }

    /**
     * En qué parte del día va cada cita de hoy, para que las partes de "Citas de hoy" sumen el total: atendida, en
     * la clínica (en sala o con el doctor), por llegar, sin cerrar o no asistió. "Sin cerrar" sale de la misma regla
     * que "Citas sin cerrar" y el cierre del día (Appointment::isUnclosed): la que ya pasó su hora sin que nadie
     * marcara si vino, y también la que sigue "en sala" o "en atención" horas después de su hora.
     *
     * @return 'done'|'here'|'toCome'|'unmarked'|'missed'
     */
    private static function dayPart(Appointment $appointment): string
    {
        /** @var AppointmentStatus $status */
        $status = $appointment->status;

        return match (true) {
            $status === AppointmentStatus::Completed => 'done',
            $appointment->isUnclosed() => 'unmarked',
            in_array($status, AppointmentStatus::inClinic(), true) => 'here',
            $status->isChangeable() => 'toCome',
            default => 'missed',
        };
    }

    /** @return array{label: string, value: int, hint: string, color: string, icon: string, url: string} */
    private function unconfirmed(): array
    {
        $count = AppointmentResource::getEloquentQuery()
            ->awaitingConfirmation()
            ->todayOrTomorrow()
            ->count();

        return [
            'label' => 'Por confirmar',
            'value' => $count,
            'hint' => $count > 0 ? 'Hoy y mañana · reenvía el aviso o llama' : 'Hoy y mañana, todo confirmado',
            'color' => $count > 0 ? 'warning' : 'gray',
            'icon' => 'heroicon-o-phone',
            // La pestaña trae todas las que faltan confirmar; con las fechas en el filtro quedan las de hoy y mañana, que son las que se cuentan aquí.
            'url' => AppointmentResource::toConfirmUrl(today(), today()->addDay()),
        ];
    }

    /**
     * Quién está en sala ahora y cuánto lleva el que más espera: las mismas personas que la sala de espera, a la
     * que lleva. Se marca en rojo cuando alguien ya está demorado, como en la sala (Appointment::waitsTooLong).
     *
     * @return array{label: string, value: int, hint: string, color: string, icon: string, url: string}
     */
    private function waitingRoom(): array
    {
        $waiting = WaitingRoom::waiting();
        $longest = (int) $waiting->map(fn (Appointment $appointment) => (int) $appointment->waitingMinutes())->max();

        return [
            'label' => 'En sala ahora',
            'value' => $waiting->count(),
            'hint' => $waiting->isEmpty() ? 'Nadie esperando' : 'El que más espera: '.Dates::duration($longest),
            'color' => $waiting->contains(fn (Appointment $appointment) => $appointment->waitsTooLong()) ? 'danger' : 'gray',
            'icon' => 'heroicon-o-user-group',
            'url' => '#'.WaitingRoom::ANCHOR,
        ];
    }

    /**
     * Lo cobrado hoy y lo que falta cobrar de lo de hoy, con las mismas cuentas que el cierre del día: lleva
     * ahí, donde está cada persona que debe con su "Cobrar", y el número y la lista coinciden.
     *
     * @return array{label: string, value: string, hint: string, color: string, icon: string, url: string}|null
     */
    private function collectedToday(User $user): ?array
    {
        if (! $user->can('viewAny', Payment::class)) {
            return null;
        }

        $closing = new CashClosing(CarbonImmutable::today());
        $owing = $closing->pending()->count();
        $finished = Appointment::query()->startsOn(today())->where('status', AppointmentStatus::Completed)->exists();

        return [
            'label' => 'Cobrado hoy',
            'value' => Money::format($closing->total()),
            'hint' => match (true) {
                $owing === 0 && ! $finished => 'Aún no se terminó ninguna atención',
                $owing === 0 => 'Nadie se fue debiendo',
                $owing === 1 => 'Falta cobrar '.Money::format($closing->pendingTotal()).' a 1 paciente',
                default => 'Falta cobrar '.Money::format($closing->pendingTotal())." a {$owing} pacientes",
            },
            'color' => $owing > 0 ? 'warning' : 'gray',
            'icon' => 'heroicon-o-banknotes',
            'url' => CashClosingPage::getUrl(),
        ];
    }

    /** @return array{label: string, value: int, hint: string, color: string, icon: string, url: string} */
    private function pendingRequests(): array
    {
        $count = RescheduleRequestResource::getEloquentQuery()
            ->where('status', RescheduleRequestStatus::Pending)
            ->count();

        return [
            'label' => 'Cambios pedidos',
            'value' => $count,
            'hint' => $count > 0 ? 'Aprueba un horario con un clic' : 'Nada pendiente',
            'color' => $count > 0 ? 'warning' : 'gray',
            'icon' => 'heroicon-o-arrows-right-left',
            'url' => RescheduleRequestResource::getUrl('index'),
        ];
    }

    /** @return array{label: string, value: int, hint: string, color: string, icon: string, url: string} */
    private function dueMessages(): array
    {
        $count = WhatsappMessageResource::getEloquentQuery()->due()->count();

        return [
            'label' => 'Avisos por enviar',
            'value' => $count,
            'hint' => $count > 0 ? 'Listos para WhatsApp' : 'Nada pendiente',
            'color' => $count > 0 ? 'warning' : 'gray',
            'icon' => 'heroicon-o-chat-bubble-left-right',
            'url' => WhatsappMessageResource::getUrl('index'),
        ];
    }
}
