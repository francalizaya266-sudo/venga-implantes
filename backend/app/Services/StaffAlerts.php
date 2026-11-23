<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Filament\Pages\CashClosing;
use App\Livewire\PanelPulse;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\PriceAdjustments;
use App\Support\Dates;
use App\Support\Money;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
     * A quien administra: lo delicado de la caja, para que se entere en el momento y no recién en el cierre. A quien lo
     * hizo no hace falta decírselo.
     *
     * @param  array{label: string, url: string}|null  $link  Botón para ir a revisarlo.
     */
    public function toAdmins(string $title, ?string $body = null, ?User $except = null, ?array $link = null): void
    {
        $recipients = User::query()
            ->where('is_active', true)
            ->where('role', UserRole::Admin)
            ->when($except, fn ($q) => $q->whereKeyNot($except->getKey()))
            ->get();

        if ($recipients->isNotEmpty()) {
            $this->deliver($recipients, $link ? $this->toAct($title, $body, $link) : $this->toKnow($title, $body));
        }
    }

    /**
     * Al cobrar se cambió un precio, se puso un descuento o se quitó un concepto: quien administra lo sabe al momento,
     * con el motivo y quién fue. Un concepto agregado a mano sin descuento no se avisa: suma, no rebaja.
     *
     * @param  Collection<int, Charge>  $charges  Lo que se cambió al cobrar.
     */
    public function checkoutAdjusted(Patient $patient, Collection $charges, User $by): void
    {
        $worth = $charges->reject(fn (Charge $charge) => PriceAdjustments::kind($charge) === PriceAdjustments::ADDED && (float) $charge->discount <= 0);

        if ($worth->isEmpty()) {
            return;
        }

        $reason = $worth->pluck('adjustment_reason')->filter()->first();

        $this->toAdmins(
            'Cambio al cobrar a '.$patient->full_name,
            // "Limpieza dental: precio de S/ 120.00 a S/ 70.00." y abajo el motivo y quién.
            $worth->map(fn (Charge $charge) => e($charge->description.': '.Str::lcfirst(PriceAdjustments::change($charge)).'.'))
                ->push(e(($reason ? 'Motivo: '.$reason.'. ' : 'Sin motivo. ').'Lo hizo '.$by->name.'.'))
                ->implode('<br>'),
            $by,
            ['label' => 'Ver en el cierre del día', 'url' => self::closingUrl(today())],
        );
    }

    /** Se anuló un cargo desde la cuenta del paciente: ya no se le cobra, y quien administra lo sabe al momento. */
    public function chargeVoided(Charge $charge, User $by): void
    {
        /** @var Patient $patient */
        $patient = $charge->patient;

        $this->toAdmins(
            'Se anuló un cargo de '.$patient->full_name,
            e($charge->description.' de '.Money::format($charge->net()).'. Motivo: '.$charge->void_reason.'. Lo anuló '.$by->name.'.'),
            $by,
            ['label' => 'Ver en el cierre del día', 'url' => self::closingUrl(today())],
        );
    }

    /** Se anuló un pago: deja de contar en la caja de su día, y quien administra lo sabe al momento. */
    public function paymentVoided(Payment $payment, User $by): void
    {
        /** @var Patient $patient */
        $patient = $payment->patient;
        /** @var PaymentMethod $method */
        $method = $payment->method;
        /** @var CarbonInterface $paidAt */
        $paidAt = $payment->paid_at;

        $this->toAdmins(
            'Se anuló un pago de '.$patient->full_name,
            e(Money::format(abs((float) $payment->amount)).' con '.$method->getLabel().' ('.$paidAt->translatedFormat('l j \d\e F').', '.Dates::time($paidAt).'). Motivo: '.$payment->void_reason.'. Lo anuló '.$by->name.'.'),
            $by,
            ['label' => 'Ver en el cierre del día', 'url' => self::closingUrl($paidAt)],
        );
    }

    /** Se devolvió dinero que el paciente tenía a favor: sale de la caja de hoy. */
    public function refunded(Payment $refund, User $by): void
    {
        /** @var Patient $patient */
        $patient = $refund->patient;
        /** @var PaymentMethod $method */
        $method = $refund->method;
        /** @var CarbonInterface $paidAt */
        $paidAt = $refund->paid_at;

        $this->toAdmins(
            'Se devolvió dinero a '.$patient->full_name,
            e(Money::format(abs((float) $refund->amount)).' con '.$method->getLabel().'. Motivo: '.$refund->notes.'. Lo devolvió '.$by->name.'.'),
            $by,
            ['label' => 'Ver en el cierre del día', 'url' => self::closingUrl($paidAt)],
        );
    }

    /** El cierre de ese día. */
    private static function closingUrl(CarbonInterface $day): string
    {
        return CashClosing::getUrl(['filters' => ['date' => $day->toDateString()]], panel: 'admin');
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
