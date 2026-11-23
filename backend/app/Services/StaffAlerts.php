<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Livewire\PanelPulse;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Alertas en la campana del panel para recepción (y administración), y para el doctor. Se distinguen de un vistazo:
 * lo que pide hacer algo lleva su botón y el ícono en el color de la marca; lo que solo informa va en gris y sin
 * botón, para que no compita; y "Llegó" va aparte, con "Atender".
 */
class StaffAlerts
{
    /**
     * @param  array{label: string, url: string}|null  $link  Botón para ir directo a resolverlo.
     * @param  array<string, int>  $about  De qué es el aviso (por ejemplo ['checkout' => id del paciente]), para darlo por leído cuando ya no haga falta.
     */
    public function toReception(string $title, ?string $body = null, ?User $except = null, ?array $link = null, array $about = []): void
    {
        $recipients = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Reception, UserRole::Admin])
            ->when($except, fn ($q) => $q->whereKeyNot($except->getKey()))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $notification = $link ? $this->toAct($title, $body, $link) : $this->toKnow($title, $body);

        $this->deliver($recipients, $about === [] ? $notification : $notification->viewData($about));
    }

    /**
     * Ya se le cobró: "terminó su atención: cobrarle" queda como leído en la campana de todos en recepción. Antes
     * seguía ahí después de que pagó, y parecía que faltaba cobrarle.
     */
    public function checkoutSettled(Patient $patient): void
    {
        DatabaseNotification::query()
            ->whereNull('read_at')
            ->where('data->viewData->checkout', $patient->getKey())
            ->update(['read_at' => now()]);
    }

    /**
     * Los avisos de días anteriores quedan leídos: la campana muestra lo de hoy. No se pierde nada, porque lo que
     * pedían sigue en otro lado (los avisos por enviar, los cambios pedidos, los materiales, la lista de espera, lo
     * que se fueron debiendo) y el aviso queda en la campana, ya leído. Antes se juntaban "Llegó…" de hace dos días
     * y citas web de la semana pasada, y lo nuevo se perdía entre ellos.
     *
     * @return int Cuántos quedaron leídos.
     */
    public function readEarlierDays(): int
    {
        return DatabaseNotification::query()
            ->whereNull('read_at')
            ->where('created_at', '<', today())
            // El reporte del mes espera una semana a que lo abran: el día 1 puede caer domingo.
            ->where(fn ($query) => $query->whereNull('data->viewData->monthlyReport')->orWhere('created_at', '<', today()->subWeek()))
            ->update(['read_at' => now()]);
    }

    /** A una persona, para que esté al tanto. */
    public function toUser(?User $user, string $title, ?string $body = null): void
    {
        if ($user === null || ! $user->is_active) {
            return;
        }

        $this->deliver([$user], $this->toKnow($title, $body));
    }

    /**
     * Al doctor: su paciente ya está en sala. Si lo marcó él mismo, no hace falta decírselo. El aviso guarda de
     * qué cita es, para darlo por leído cuando el paciente pasa a consulta.
     */
    public function patientArrived(Appointment $appointment, User $by): void
    {
        $doctorUser = $appointment->doctor->user;

        if ($doctorUser === null || ! $doctorUser->is_active || $doctorUser->is($by)) {
            return;
        }

        $this->deliver([$doctorUser], Notification::make()
            ->title("Llegó {$appointment->patient->full_name}")
            ->body("Te espera en sala. Cita de las {$appointment->starts_at->format('H:i')} · {$appointment->treatment->name}.")
            ->icon(Heroicon::OutlinedUserCircle)
            ->iconColor('success')
            ->viewData(['arrival' => $appointment->getKey()])
            ->actions([
                Action::make('attend')
                    ->label('Atender')
                    ->button()
                    ->size(Size::Small)
                    ->dispatch(PanelPulse::ATTEND, ['appointment' => $appointment->getKey()]),
            ]));
    }

    /** El paciente ya no espera (pasó a consulta o la cita se cerró): el aviso de que llegó queda como leído. */
    public function arrivalAttended(Appointment $appointment): void
    {
        $appointment->doctor->user?->unreadNotifications()
            ->where('data->viewData->arrival', $appointment->getKey())
            ->update(['read_at' => now()]);
    }

    /**
     * Pide que alguien haga algo: el botón lleva a resolverlo y deja el aviso como leído.
     *
     * @param  array{label: string, url: string}  $link
     */
    private function toAct(string $title, ?string $body, array $link): Notification
    {
        return Notification::make()
            ->title($title)
            ->body($body)
            ->icon(Heroicon::OutlinedBellAlert)
            ->iconColor('primary')
            ->actions([Action::make('open')->label($link['label'])->url($link['url'])->button()->size(Size::Small)->markAsRead()]);
    }

    /** Solo informa: en gris y sin botón. */
    private function toKnow(string $title, ?string $body): Notification
    {
        return Notification::make()
            ->title($title)
            ->body($body)
            ->icon(Heroicon::OutlinedInformationCircle)
            ->iconColor('gray');
    }

    /**
     * Queda en la campana al instante. Antes se encolaba y solo aparecía cuando la tarea programada del hosting
     * procesaba la cola: si esa tarea no corría, la alerta no llegaba nunca.
     *
     * @param  iterable<User>  $recipients
     */
    private function deliver(iterable $recipients, Notification $notification): void
    {
        foreach ($recipients as $recipient) {
            $recipient->notifyNow($notification->toDatabase());
        }
    }
}
