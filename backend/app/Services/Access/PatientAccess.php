<?php

namespace App\Services\Access;

use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\MagicLink;
use App\Models\RememberedDevice;
use App\Models\WhatsappMessage;
use App\Services\StaffAlerts;
use App\Services\WhatsApp\WhatsAppOutbox;
use App\Support\Phone;
use Illuminate\Http\Request;

/**
 * Quién puede ver o cambiar una cita desde la web: quien tiene el enlace de esa cita
 * o el dispositivo recordado del celular del paciente. Sin contraseñas ni códigos.
 */
class PatientAccess
{
    public function __construct(
        private readonly MagicLinkService $links,
        private readonly DeviceService $devices,
        private readonly WhatsAppOutbox $outbox,
        private readonly StaffAlerts $alerts,
    ) {}

    public function device(Request $request): ?RememberedDevice
    {
        return $this->devices->current($request);
    }

    /** ¿Esta petición puede actuar sobre la cita? Con su enlace vigente o desde el celular recordado. */
    public function canAccess(Request $request, Appointment $appointment, ?string $token = null): bool
    {
        if ($token !== null) {
            return $this->links->find($token)?->appointment_id === $appointment->getKey();
        }

        $appointment->loadMissing('patient');

        return $this->device($request)?->account_id === $appointment->patient->account_id;
    }

    /**
     * El paciente tocó un botón del enlace: este celular queda recordado y verificado, así que ve todo lo de su
     * familia en Mi cuenta hasta que salga.
     */
    public function actWithLink(Request $request, MagicLink $link): void
    {
        $this->links->consume($link);
        $device = $this->device($request);

        if ($device?->account_id === $link->account_id) {
            $this->devices->verify($device);
        } else {
            // El enlace llega al WhatsApp del número: quien lo toca es su dueño. Si alguien había reservado con ese
            // número desde otro celular, ese celular deja de estar recordado.
            $this->devices->forgetUnverified($link->account);
            $this->devices->remember($link->account, $request);
        }
    }

    /**
     * Primera reserva con un número que el consultorio no tenía: la cuenta nace con lo que escribió este celular, así
     * que queda recordado al instante y entra a Mi cuenta sin esperar el enlace. Queda sin verificar hasta que toque
     * uno: sus reservas no cuentan como hechas desde el celular de la familia.
     */
    public function rememberNewcomer(Account $account, Request $request): void
    {
        $this->devices->remember($account, $request, verified: false);
    }

    /**
     * "Pedir acceso": deja listo en la bandeja el enlace de Mi cuenta para que recepción
     * lo envíe. No dice si el número existe, y no repite el aviso si ya hay uno pendiente.
     */
    public function requestAccess(string $phone): void
    {
        $account = Phone::isValid($phone) ? Account::findByPhone($phone) : null;

        if ($account === null || $account->isBlocked()) {
            return;
        }

        $pending = WhatsappMessage::query()
            ->whereBelongsTo($account)
            ->where('template_key', MessageTemplateKey::AccessLink)
            ->where('status', MessageStatus::Pending)
            ->exists();

        if ($pending) {
            return;
        }

        $this->outbox->queueForAccount($account, MessageTemplateKey::AccessLink, patientId: $account->holder?->getKey());

        $name = $account->holder?->full_name ?? Phone::display($account->phone);
        $this->alerts->toReception(
            'Pidió su enlace de Mi cuenta',
            "{$name} quiere entrar a Mi cuenta. El enlace está listo en Avisos.",
            link: ['label' => 'Enviar el enlace', 'url' => WhatsappMessageResource::getUrl()],
        );
    }
}
