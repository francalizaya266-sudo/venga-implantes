<?php

namespace App\Livewire;

use App\Enums\AppointmentStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Consultations\ConsultationResource;
use App\Filament\Support\Notify;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Clinical\ConsultationService;
use App\Services\Dashboard\TodayPulse;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * El pulso del panel: va en todas las pantallas y no dibuja nada. Cada pocos segundos mira si alguien guardó
 * algo del día (TodayPulse) y, si es así, avisa a la pantalla para que se vuelva a pintar: "Hoy", la agenda,
 * la lista de citas y la campana. Lo nuevo de la campana sale además como aviso en pantalla, así el doctor se
 * entera de que su paciente llegó aunque esté escribiendo una atención. Ese "Llegó" se queda en pantalla con
 * "Atender" hasta que lo atienda o lo cierre, y se cierra solo si el paciente pasa a consulta desde otro lado.
 *
 * No es tiempo real por conexión abierta (websockets): eso pide un servidor aparte siempre encendido. Una
 * consulta corta cada 3 segundos da casi lo mismo y funciona en cualquier hosting.
 */
class PanelPulse extends Component
{
    /** Cada cuánto pregunta el navegador. */
    public const EVERY = '3s';

    /** "Atender" de un aviso de que llegó el paciente, en pantalla o en la campana. */
    public const ATTEND = 'attend-arrival';

    /** El botón de un aviso en pantalla: lo deja como leído en la campana y lleva a resolverlo. */
    public const OPEN_ALERT = 'open-alert';

    /** Avisos que se muestran de una vez; si llegaron más, están en la campana. */
    private const ALERTS_AT_ONCE = 3;

    /** Lo que dura en pantalla un aviso que no es "Llegó": lo justo para leerlo y tocar su botón. */
    private const TOAST_SECONDS = 8;

    /**
     * El aviso en pantalla no lleva el id del de la campana: al cerrarse, la campana borraría el suyo.
     */
    private const TOAST_ID_PREFIX = 'alert-';

    /** La última marca que esta pantalla ya pintó. */
    #[Locked]
    public string $stamp = '';

    /** Los avisos de la campana creados desde este momento son nuevos para esta pantalla. */
    #[Locked]
    public string $alertsSince = '';

    /** @var list<string> Los de ese mismo segundo que ya se mostraron. */
    #[Locked]
    public array $alertsShown = [];

    /** @var list<string> Los "Llegó" que siguen en pantalla, para cerrarlos cuando el paciente ya pasó. */
    #[Locked]
    public array $arrivalsOnScreen = [];

    public function mount(): void
    {
        $this->stamp = TodayPulse::stamp();
        $this->alertsSince = now()->toDateTimeString();
    }

    /** Lo llama el navegador cada pocos segundos. */
    public function check(): void
    {
        if ($this->catchUp()) {
            $this->dispatch(Dashboard::CHANGED);
        }
    }

    /**
     * El cambio se hizo en esta misma pantalla y sus tarjetas ya se están pintando: solo se toma nota, para no
     * pintarlas otra vez en la siguiente consulta. Lo llama el navegador al oír el aviso de la propia página; no
     * es un oyente de Livewire porque, al responderse a sí mismo, el pulso cancelaría su consulta y con ella
     * los avisos en pantalla que traía.
     */
    public function acknowledge(): void
    {
        $this->catchUp();
    }

    /**
     * "Atender" del aviso de que llegó: abre su atención, como el botón de la cita, y lleva a ella. Solo el doctor
     * de esa cita y solo si el paciente sigue en sala (o ya pasó y la atención está abierta).
     */
    #[On(self::ATTEND)]
    public function attend(int $appointment): void
    {
        $this->skipRender();

        /** @var User $user */
        $user = auth()->user();
        $record = $user->canSeeClinical() ? Appointment::query()
            ->whereKey($appointment)
            ->where('doctor_id', $user->doctorId())
            ->whereDate('starts_at', today())
            ->whereIn('status', [AppointmentStatus::CheckedIn, AppointmentStatus::InProgress])
            ->first() : null;

        if ($record === null) {
            Notification::make()->warning()->title('Ese paciente ya no está en sala')->body('Búscalo en tu agenda de hoy.')->send();

            return;
        }

        $consultations = app(ConsultationService::class);
        $consultation = null;
        Notify::run(function () use ($consultations, $record, $user, &$consultation) {
            $consultation = $consultations->startFromAppointment($record, $user);
        }, 'Atención iniciada');

        if ($consultation) {
            Notify::heldMedications($consultations->heldMedications());
            $this->redirect(ConsultationResource::getUrl('edit', ['record' => $consultation]));
        }
    }

    /** El botón de un aviso en pantalla: en la campana queda como leído y se va adonde se resuelve. */
    #[On(self::OPEN_ALERT)]
    public function openAlert(string $alert): void
    {
        $this->skipRender();

        /** @var User $user */
        $user = auth()->user();
        $notification = $user->notifications()->whereKey($alert)->first();

        if ($notification === null) {
            return;
        }

        $notification->markAsRead();
        $url = $notification->data['actions'][0]['url'] ?? null;

        if (is_string($url) && $url !== '') {
            $this->redirect($url);
        }
    }

    public function render(): View
    {
        return view('livewire.panel-pulse');
    }

    /**
     * Con cada cambio la campana también se pone al día: lo nuevo aparece y lo que ya se atendió deja de contar.
     *
     * @return bool Si alguien guardó algo desde la última vez.
     */
    private function catchUp(): bool
    {
        $this->skipRender();

        $stamp = TodayPulse::stamp();

        if ($stamp === $this->stamp) {
            return false;
        }

        $this->stamp = $stamp;
        $this->announceNewAlerts();
        $this->closeAttendedArrivals();
        $this->dispatch('databaseNotificationsSent');

        return true;
    }

    /** Lo que llegó a la campana desde la última vez se muestra también en pantalla. */
    private function announceNewAlerts(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $fresh = $user->unreadNotifications()
            ->where('data->format', 'filament')
            ->where('created_at', '>=', $this->alertsSince)
            ->whereNotIn('id', $this->alertsShown)
            ->oldest()
            ->get();

        if ($fresh->isEmpty()) {
            return;
        }

        $shown = $fresh->take(self::ALERTS_AT_ONCE)->each(fn (DatabaseNotification $alert) => $this->toast($alert)->send());
        $arrivals = $shown->filter(fn (DatabaseNotification $alert) => isset($alert->data['viewData']['arrival']))->modelKeys();
        $this->arrivalsOnScreen = [...$this->arrivalsOnScreen, ...$arrivals];

        $newest = $fresh->last()->created_at->toDateTimeString();
        $ofThatSecond = $fresh->filter(fn (DatabaseNotification $alert) => $alert->created_at->toDateTimeString() === $newest)->modelKeys();

        $this->alertsShown = $newest === $this->alertsSince ? [...$this->alertsShown, ...$ofThatSecond] : $ofThatSecond;
        $this->alertsSince = $newest;
    }

    /**
     * El aviso en pantalla se ve como en la campana: lo que pide hacer algo, con su botón; lo que solo informa, en
     * gris. "Llegó" se queda hasta que el doctor lo atienda o lo cierre; los demás se van solos.
     */
    private function toast(DatabaseNotification $alert): Notification
    {
        $toast = Notification::fromDatabase($alert)->id(self::TOAST_ID_PREFIX.$alert->getKey());

        if (blank($toast->getTitle())) {
            $toast->title('Tienes un aviso nuevo');
        }

        // Los avisos de antes no guardaban su ícono.
        if (blank($toast->getIcon())) {
            $toast->icon(Heroicon::OutlinedBell)->iconColor('info');
        }

        $arrival = $alert->data['viewData']['arrival'] ?? null;

        if ($arrival !== null) {
            return $toast->persistent()->actions([
                Action::make('attend')->label('Atender')->button()->size(Size::Small)
                    ->dispatch(self::ATTEND, ['appointment' => $arrival])
                    ->close(),
                Action::make('later')->label('Cerrar')->link()->color('gray')->size(Size::Small)->close(),
            ]);
        }

        $label = $alert->data['actions'][0]['label'] ?? null;

        // En la campana se guardan sin plazo; en pantalla se van solos.
        return $toast->seconds(self::TOAST_SECONDS)->actions(blank($label) ? [] : [
            Action::make('open')->label($label)->button()->size(Size::Small)
                ->dispatch(self::OPEN_ALERT, ['alert' => $alert->getKey()])
                ->close(),
        ]);
    }

    /** Los "Llegó" en pantalla de pacientes que ya pasaron a consulta (desde aquí o desde otra pantalla) se cierran. */
    private function closeAttendedArrivals(): void
    {
        if ($this->arrivalsOnScreen === []) {
            return;
        }

        /** @var User $user */
        $user = auth()->user();
        $waiting = array_values($user->unreadNotifications()->whereIn('id', $this->arrivalsOnScreen)->pluck('id')->map(fn ($id): string => (string) $id)->all());

        foreach (array_diff($this->arrivalsOnScreen, $waiting) as $attended) {
            $this->dispatch('close-notification', id: self::TOAST_ID_PREFIX.$attended);
        }

        $this->arrivalsOnScreen = $waiting;
    }
}
