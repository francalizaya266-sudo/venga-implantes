<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\DayPeriod;
use App\Enums\MessageTemplateKey;
use App\Enums\PatientRelationship;
use App\Enums\WaitlistStatus;
use App\Exceptions\BookingException;
use App\Filament\Resources\WaitlistEntries\WaitlistEntryResource;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\RememberedDevice;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WhatsappMessage;
use App\Services\Agenda\AvailabilityService;
use App\Services\Agenda\Slot;
use App\Services\StaffAlerts;
use App\Services\WhatsApp\WhatsAppOutbox;
use App\Support\FamilyNames;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lista de espera: a quién ofrecerle un hueco, el aviso por WhatsApp con el horario (que queda apartado
 * mientras responde) y la salida de la lista cuando se le agenda. Los horarios salen de la misma agenda
 * inteligente que la web.
 */
final class WaitlistService
{
    /** Días hacia adelante en que se busca el primer horario que le sirva. */
    public const SEARCH_DAYS = 14;

    /** Horas que el horario ofrecido queda apartado para quien lo recibió (o hasta la cita, si es antes). */
    public const HOLD_HOURS = 2;

    /** Minutos que, como mínimo, tiene para responder cuando el horario ofrecido es muy pronto. */
    public const HOLD_MIN_MINUTES = 15;

    /** Minutos que necesita alguien de la lista de espera para llegar si se le avisa ahora. */
    public const ARRIVAL_MINUTES = 30;

    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly WhatsAppOutbox $outbox,
        private readonly StaffAlerts $alerts,
    ) {}

    /**
     * Quienes pueden tomar un horario que se acaba de liberar: les cae en su plazo y en la parte
     * del día que pidieron, su tratamiento entra a esa hora sin cruzarse con otra cita y es antes de la cita que ya
     * tienen para lo mismo (si la tienen, esperan adelantarla).
     *
     * @return Collection<int, WaitlistEntry>
     */
    public function candidatesFor(Doctor $doctor, CarbonInterface $startsAt, ?int $exceptPatientId = null): Collection
    {
        return WaitlistEntry::query()
            ->availableOn($startsAt, $doctor->getKey())
            ->whereHas('patient')
            ->when($exceptPatientId, fn ($query, int $id) => $query->where('patient_id', '!=', $id))
            // Quien ya tiene otro horario apartado está por responder; a quien se le ofreció este y no respondió, no se le insiste.
            ->whereNotIn('id', WaitlistEntry::query()->holding()->select('id'))
            ->where(fn ($query) => $query
                ->whereNull('offered_starts_at')
                ->orWhere('offered_starts_at', '!=', $startsAt)
                ->orWhere('offered_doctor_id', '!=', $doctor->getKey()))
            ->with(['patient.account', 'treatment'])
            ->oldest()
            ->get()
            ->filter(fn (WaitlistEntry $entry) => $entry->accepts($startsAt)
                && $this->fitsAt($entry, $doctor, $startsAt)
                && $this->isBeforeUpcoming($entry, $startsAt))
            ->values();
    }

    /**
     * La próxima cita que ya tiene para lo que espera (para cualquier cosa, si espera sin tratamiento): la misma de
     * la que la lista dice "adelántasela". Null si no tiene.
     */
    public function upcomingAppointmentAt(WaitlistEntry $entry): ?CarbonInterface
    {
        $upcoming = Appointment::query()
            ->where('patient_id', $entry->patient_id)
            ->when($entry->treatment_id, fn (Builder $query, int $treatmentId) => $query->where('treatment_id', $treatmentId))
            ->where('starts_at', '>=', now())
            ->whereIn('status', AppointmentStatus::occupying())
            ->orderBy('starts_at')
            ->first();

        /** @var CarbonInterface|null $startsAt */
        $startsAt = $upcoming?->starts_at;

        return $startsAt;
    }

    /**
     * Doctores que pueden atenderlo: el que pidió o cualquiera activo que haga el tratamiento.
     *
     * @return Collection<int, Doctor>
     */
    public function doctorsFor(WaitlistEntry $entry, ?Treatment $treatment = null): Collection
    {
        $treatment ??= $entry->treatment;

        return Doctor::query()
            ->active()
            ->when($entry->doctor_id, fn ($query, int $id) => $query->whereKey($id))
            ->when($treatment, fn ($query) => $query->whereHas('treatments', fn ($treatments) => $treatments->whereKey($treatment->getKey())))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Horarios libres de ese día que le sirven (en la parte del día que pidió, dentro de su plazo y antes de la cita
     * que ya tiene para lo mismo, si la tiene).
     *
     * @return Collection<int, Slot>
     */
    public function slotsFor(WaitlistEntry $entry, Doctor $doctor, Treatment $treatment, CarbonInterface $date): Collection
    {
        $day = CarbonImmutable::instance($date)->startOfDay();
        $upcoming = $this->upcomingAppointmentAt($entry);

        return $this->availability
            ->forPatient($entry->patient_id)
            ->slotsFor($doctor, $treatment, $day, earliest: $day->isToday() ? now() : null)
            ->filter(fn (Slot $slot) => $entry->accepts($slot->startsAt) && ($upcoming === null || $slot->startsAt->lt($upcoming)))
            ->values();
    }

    /**
     * El primer horario libre que le sirve en los próximos días, con su doctor o con cualquiera que
     * haga el tratamiento. Null si no hay ninguno.
     *
     * @return array{doctor: Doctor, slot: Slot}|null
     */
    public function firstSlot(WaitlistEntry $entry, ?Treatment $treatment = null): ?array
    {
        $treatment ??= $entry->treatment;

        if (! $treatment) {
            return null;
        }

        $doctors = $this->doctorsFor($entry, $treatment);
        // Desde el día que puede: si es en un mes, se buscan las dos semanas desde ahí, no las próximas.
        $first = CarbonImmutable::instance($entry->firstDate())->startOfDay();
        $last = $first->addDays(self::SEARCH_DAYS - 1);

        if ($entry->latest_date && $entry->latest_date->lt($last)) {
            $last = CarbonImmutable::instance($entry->latest_date);
        }

        for ($date = $first; $date->lte($last); $date = $date->addDay()) {
            foreach ($doctors as $doctor) {
                $slot = $this->slotsFor($entry, $doctor, $treatment, $date)->first();

                if ($slot) {
                    return ['doctor' => $doctor, 'slot' => $slot];
                }
            }
        }

        return null;
    }

    /** El aviso con el horario que se le ofrece, sin guardar. Null si el paciente no tiene celular. */
    public function offerDraft(WaitlistEntry $entry, Doctor $doctor, Treatment $treatment, CarbonInterface $startsAt): ?WhatsappMessage
    {
        $patient = $entry->patient;

        if (! $patient?->account) {
            return null;
        }

        return $this->outbox->draftForAccount($patient->account, MessageTemplateKey::WaitlistOffer, [
            'nombre' => $patient->first_name,
            'paciente' => $patient->full_name,
            'tratamiento' => mb_strtolower($treatment->name),
            'fecha' => $startsAt->translatedFormat('l j \d\e F'),
            'hora' => $startsAt->format('g:i a'),
            'doctor' => $doctor->name,
            'hasta' => $this->holdEndsAt($startsAt)->format('g:i a'),
        ], $patient->getKey());
    }

    /**
     * Hasta cuándo queda apartado un horario que se ofrece ahora: unas horas, pero no tan encima de la cita que
     * ya no dé tiempo a llegar (media hora antes). Si la cita es muy pronto, igual se le da un plazo mínimo para
     * responder (un "te lo guardo 3 minutos" no sirve), sin pasar de la hora en que empieza.
     */
    public function holdEndsAt(CarbonInterface $startsAt): CarbonImmutable
    {
        $startsAt = CarbonImmutable::instance($startsAt);
        $now = now()->toImmutable();
        $usual = $now->addHours(self::HOLD_HOURS)->min($startsAt->subMinutes(30));
        $atLeast = $now->addMinutes(self::HOLD_MIN_MINUTES)->min($startsAt);

        return $usual->max($atLeast);
    }

    /**
     * Queda como avisado, con el horario que se le ofreció, para agendarlo si dice que sí. El horario queda
     * apartado un rato (ver holdEndsAt): mientras tanto no se le ofrece a otro ni lo toma la web.
     */
    public function markOffered(WaitlistEntry $entry, Doctor $doctor, Treatment $treatment, CarbonInterface $startsAt): void
    {
        $startsAt = CarbonImmutable::instance($startsAt);

        $entry->update([
            'status' => WaitlistStatus::Offered,
            'offered_at' => now(),
            'offered_starts_at' => $startsAt,
            'offered_until' => $startsAt->addMinutes($doctor->durationFor($treatment) + $treatment->bufferMinutes()),
            'offered_doctor_id' => $doctor->getKey(),
            'held_until' => $this->holdEndsAt($startsAt),
        ]);
    }

    /** Deja libre el horario apartado (no lo quiere, o no respondió a tiempo); sigue en la lista. */
    public function release(WaitlistEntry $entry): void
    {
        $entry->update(['held_until' => null]);
    }

    /**
     * Recepción dejó libre a mano el horario que tenía apartado (lo liberó o lo retiró de la lista): ese horario y a
     * quién de la lista ofrecérselo ahora, como cuando el apartado vence solo. Null si ya pasó.
     *
     * @return array{when: string, next: array{text: string, link: array{label: string, url: string}}|null}|null
     */
    public function releasedHoldNotice(WaitlistEntry $entry): ?array
    {
        /** @var Doctor|null $doctor */
        $doctor = $entry->offeredDoctor;
        /** @var CarbonInterface|null $startsAt */
        $startsAt = $entry->offered_starts_at;

        if ($doctor === null || $startsAt === null || ! $startsAt->isFuture()) {
            return null;
        }

        return [
            'when' => ucfirst($startsAt->translatedFormat('D j M, g:i a'))." con {$doctor->name}",
            'next' => $this->freedSlotNotice($doctor, $startsAt, $entry->patient_id),
        ];
    }

    /**
     * Para la alerta de un horario que se liberó: quién de la lista de espera puede tomarlo (el que espera
     * hace más tiempo) y el botón que abre la agenda con el mensaje para él ya escrito.
     *
     * @return array{text: string, link: array{label: string, url: string}}|null
     */
    public function freedSlotNotice(Doctor $doctor, CarbonInterface $startsAt, ?int $exceptPatientId = null): ?array
    {
        if (! $startsAt->isFuture()) {
            return null;
        }

        $candidates = $this->candidatesFor($doctor, $startsAt, $exceptPatientId);
        $first = $candidates->first(fn (WaitlistEntry $entry) => $entry->patient->account !== null);

        if (! $first) {
            return null;
        }

        $others = $candidates->count() - 1;

        return [
            'text' => "{$first->patient->full_name}, de la lista de espera, puede venir a esa hora"
                .match (true) {
                    $others === 1 => ' (y 1 persona más).',
                    $others > 1 => " (y {$others} personas más).",
                    default => '.',
                },
            'link' => ['label' => 'Ofrecérselo a '.$first->patient->first_name, 'url' => WaitlistEntryResource::urlForOffer($first, $doctor, $startsAt)],
        ];
    }

    /**
     * El paciente no vino y todavía queda tiempo de su cita: desde cuándo puede llegar alguien de la lista de espera
     * si se le avisa ahora (media hora, en cuartos de hora), y quién. Null si ya no queda tiempo o nadie le sirve.
     *
     * @return array{startsAt: CarbonImmutable, text: string, link: array{label: string, url: string}}|null
     */
    public function noShowNotice(Appointment $appointment): ?array
    {
        /** @var CarbonInterface $startsAt */
        $startsAt = $appointment->starts_at;
        /** @var CarbonInterface $endsAt */
        $endsAt = $appointment->ends_at;
        /** @var Doctor $doctor */
        $doctor = $appointment->doctor;

        $arrival = now()->toImmutable()->addMinutes(self::ARRIVAL_MINUTES)->setSecond(0)->setMicrosecond(0);
        $arrival = $arrival->addMinutes((15 - $arrival->minute % 15) % 15);
        $from = $arrival->max(CarbonImmutable::instance($startsAt));

        if ($from->gte($endsAt)) {
            return null;
        }

        $notice = $this->freedSlotNotice($doctor, $from, $appointment->patient_id);

        return $notice ? ['startsAt' => $from] + $notice : null;
    }

    /**
     * Al cancelar una cita, el paciente pasa a la lista de espera para ese tratamiento con su doctor, desde hoy y a
     * cualquier hora: no se le pierde. Si se canceló porque su doctor no podrá atender, con cualquier doctor que lo
     * haga: atado a ese doctor, esperaría justo por quien no está. Null si ya estaba esperando por lo mismo.
     */
    public function addAfterCancellation(Appointment $appointment, User $by, bool $anyDoctor = false): ?WaitlistEntry
    {
        $alreadyWaiting = WaitlistEntry::query()
            ->active()
            ->where('patient_id', $appointment->patient_id)
            ->where(fn ($query) => $query->whereNull('treatment_id')->orWhere('treatment_id', $appointment->treatment_id))
            ->exists();

        if ($alreadyWaiting) {
            return null;
        }

        return WaitlistEntry::create([
            'patient_id' => $appointment->patient_id,
            'treatment_id' => $appointment->treatment_id,
            'doctor_id' => $anyDoctor ? null : $appointment->doctor_id,
            'earliest_date' => today(),
            'preferred_period' => DayPeriod::Any,
            'status' => WaitlistStatus::Waiting,
            'notes' => 'Se canceló su cita del '.$appointment->starts_at->translatedFormat('D j M, H:i').'.',
            'created_by_user_id' => $by->getKey(),
        ]);
    }

    /**
     * "Avísame si se libera un horario" desde la web: quien no encontró un horario que le sirva queda en la lista de
     * espera, marcado como anotado desde la web, y recepción recibe el aviso en la campana. Si ya esperaba lo mismo, se
     * actualiza lo que pidió en vez de anotarlo dos veces (y no se vuelve a avisar). Como en la reserva, quien se anota
     * con el número de otra familia sin su celular no cuenta como familia de ese número (Account::guestIds()).
     *
     * @throws BookingException|AuthorizationException
     */
    public function addFromWeb(WebWaitlistData $data, Treatment $treatment, ?Doctor $doctor = null, ?RememberedDevice $device = null): WaitlistEntry
    {
        $phone = Phone::normalize($data->phone);
        /** @var Account|null $deviceAccount La familia del celular recordado, si lo hay. */
        $deviceAccount = $device?->account;
        $recognized = $device !== null && $deviceAccount?->phone === $phone;
        $fromVerifiedDevice = $recognized && $device->isVerified();

        return DB::transaction(function () use ($data, $treatment, $doctor, $phone, $recognized, $fromVerifiedDevice) {
            $account = Account::withTrashed()->firstOrCreate(['phone' => $phone]);

            if ($account->trashed()) {
                $account->restore();
            }

            if ($account->isBlocked()) {
                throw BookingException::accountBlocked();
            }

            $patient = $this->webPatient($account, $data, $recognized);

            $account->forceFill([
                'privacy_accepted_at' => $data->acceptsPrivacy ? ($account->privacy_accepted_at ?? now()) : $account->privacy_accepted_at,
                'last_seen_at' => now(),
            ])->save();

            $entry = WaitlistEntry::query()
                ->active()
                ->whereBelongsTo($patient)
                ->where('treatment_id', $treatment->getKey())
                ->latest('id')
                ->first() ?? new WaitlistEntry([
                    'patient_id' => $patient->getKey(),
                    'treatment_id' => $treatment->getKey(),
                    'earliest_date' => today(),
                    'status' => WaitlistStatus::Waiting,
                    'from_web' => true,
                ]);

            $entry->fill([
                'doctor_id' => $doctor?->getKey(),
                'preferred_period' => $data->period,
                'notes' => self::notesWithDays($entry->notes, $data->days),
                'from_verified_device' => $entry->from_verified_device || $fromVerifiedDevice,
            ])->save();

            if ($entry->wasRecentlyCreated) {
                $this->alerts->toReception(
                    'Lista de espera desde la web',
                    "{$patient->full_name} · {$treatment->name}".($doctor ? " con {$doctor->name}" : '').' · '.mb_strtolower($data->period->getLabel())
                        .(filled($data->days) ? ' · '.trim($data->days) : '')
                        .'. No encontró un horario que le sirva: ofrécele uno cuando se libere.',
                    link: ['label' => 'Ver la lista de espera', 'url' => WaitlistEntryResource::getUrl()],
                );
            }

            return $entry;
        });
    }

    /**
     * Quién espera: el familiar que eligió desde el celular recordado, o la persona que escribió (si el número ya tenía
     * a alguien con ese nombre, es la misma persona).
     */
    private function webPatient(Account $account, WebWaitlistData $data, bool $recognized): Patient
    {
        if ($data->patientId !== null) {
            if (! $recognized) {
                throw new AuthorizationException('Para elegir a un familiar registrado, abre la web desde tu enlace de WhatsApp.');
            }

            // Como al reservar: quien está "por confirmar" en este número no es de la familia y no se puede elegir.
            /** @var Patient|null $relative */
            $relative = $account->patients()->whereKeyNot($account->guestIds())->find($data->patientId);

            return $relative ?? throw new BookingException('No encontramos a esa persona en este celular. Elige de nuevo para quién es.');
        }

        /** @var Collection<int, Patient> $patients */
        $patients = $account->patients()->get();
        $existing = $patients->first(
            fn (Patient $patient) => FamilyNames::comparable((string) $patient->first_name) === FamilyNames::comparable((string) $data->firstName)
                && FamilyNames::comparable((string) $patient->last_name) === FamilyNames::comparable((string) $data->lastName)
        );

        return $existing ?? Patient::create([
            'account_id' => $account->getKey(),
            'first_name' => trim((string) $data->firstName),
            'last_name' => trim((string) $data->lastName),
            'relationship' => $account->patients()->where('relationship', PatientRelationship::Self)->exists()
                ? PatientRelationship::Other
                : PatientRelationship::Self,
        ]);
    }

    /**
     * La nota con los días que le sirven: los que escribe ahora reemplazan los que dejó la web la vez anterior (siempre
     * al final), y lo que anotó recepción se conserva.
     */
    private static function notesWithDays(?string $notes, ?string $days): ?string
    {
        if (blank($days)) {
            return $notes;
        }

        $kept = rtrim((string) preg_replace('/(?:\s*·\s*)?Días que le sirven: .*$/u', '', (string) $notes));

        return ($kept === '' ? '' : $kept.' · ').'Días que le sirven: '.trim($days);
    }

    /** Ya no quiere esperar (consiguió otro horario, se atendió en otro lado...). */
    public function remove(WaitlistEntry $entry, ?string $reason = null): void
    {
        $entry->update([
            'status' => WaitlistStatus::Removed,
            'notes' => trim(collect([$entry->notes, $reason ? 'Retirado: '.$reason : null])->filter()->implode("\n")) ?: null,
        ]);
    }

    /**
     * Al agendarle una cita (o adelantarle la que tenía), sale de la lista lo que esa cita resuelve: lo que esperaba
     * para ese tratamiento (o para cualquiera), aunque sea con otro doctor del que esperaba. Si de todos modos quiere
     * esperar a su doctor, se le anota de nuevo; si no, seguiría recibiendo horarios que ya no necesita.
     */
    public function settle(Appointment $appointment): void
    {
        WaitlistEntry::query()
            ->where('patient_id', $appointment->patient_id)
            ->whereIn('status', WaitlistEntry::ACTIVE_STATUSES)
            ->where(fn ($query) => $query->whereNull('treatment_id')->orWhere('treatment_id', $appointment->treatment_id))
            ->get()
            ->each(fn (WaitlistEntry $entry) => $entry->update([
                'status' => WaitlistStatus::Booked,
                'appointment_id' => $appointment->getKey(),
            ]));
    }

    /** Si ya tiene cita para lo que espera, solo le sirve un horario antes de esa: está en la lista para adelantarla. */
    private function isBeforeUpcoming(WaitlistEntry $entry, CarbonInterface $startsAt): bool
    {
        $upcoming = $this->upcomingAppointmentAt($entry);

        return $upcoming === null || $startsAt->lt($upcoming);
    }

    /** ¿Su tratamiento entra a esa hora sin cruzarse con nada? Sin tratamiento elegido, cualquier hueco sirve. */
    private function fitsAt(WaitlistEntry $entry, Doctor $doctor, CarbonInterface $startsAt): bool
    {
        if (! $entry->treatment) {
            return true;
        }

        return $this->availability->forPatient($entry->patient_id)->isFree($doctor, $startsAt, $doctor->durationFor($entry->treatment), $entry->treatment->bufferMinutes());
    }
}
