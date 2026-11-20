<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Livewire\PanelPulse;
use App\Models\Appointment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;

/**
 * Alertas en la campana del panel para recepción (y administración), y para el doctor. Se distinguen de un vistazo:
 * lo que pide hacer algo lleva su botón y el ícono en el color de la marca; lo que solo informa va en gris y sin
 * botón, para que no compita; y "Llegó" va aparte, con "Atender".
 */
class StaffAlerts
{
    /**
     * @param  array{label: string, url: string}|null  $link  Botón para ir directo a resolverlo.
     */
    public function toReception(string $title, ?string $body = null, ?User $except = null, ?array $link = null): void
    {
        $recipients = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Reception, UserRole::Admin])
            ->when($except, fn ($q) => $q->whereKeyNot($except->getKey()))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $this->deliver($recipients, $link ? $this->toAct($title, $body, $link) : $this->toKnow($title, $body));
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
