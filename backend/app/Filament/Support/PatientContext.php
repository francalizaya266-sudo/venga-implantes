<?php

namespace App\Filament\Support;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\Clinical\TreatmentPlanService;
use App\Services\Patients\PatientInsights;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo que conviene saber del paciente al agendarle ("Nueva cita"), cuando llega sin cita o al anotarlo en la lista de
 * espera, con el botón que deja la ventana lista: que lo que se eligió es una sesión de su plan, que ya tuvo su evaluación, lo que le toca de su plan,
 * el presupuesto que no respondió o la cita a la que faltó. Uno a la vez, el más útil para lo que se está haciendo,
 * con lo mismo que calcula su ficha: así todas las pantallas dicen lo mismo.
 *
 * @phpstan-type Notice array{key: string, heading: string, detail: string, warn: bool, button: string|null, fill: array<string, int|null>, url: string|null}
 */
final class PatientContext
{
    /**
     * @param  bool  $forToday  "Llegó sin cita": el paciente está en el mostrador (se le pregunta si viene por eso), y el
     *                          presupuesto ya lo dice "Antes de pasar".
     * @return Notice|null
     */
    public static function notice(Patient $patient, User $user, bool $forToday, ?int $treatmentId, ?int $planItemId): ?array
    {
        // Ya se eligió la sesión de su plan: no hay nada que proponerle.
        if ($planItemId !== null) {
            return null;
        }

        $treatment = $treatmentId !== null ? Treatment::find($treatmentId) : null;

        return self::sameAsPlan($patient, $user, $treatment)
            ?? self::evaluationAgain($patient, $user, $treatment)
            ?? self::planItemToBook($patient, $user, $forToday)
            ?? ($forToday ? null : self::planAwaitingAnswer($patient, $user))
            ?? self::missedVisit($patient, $forToday, $treatment);
    }

    /**
     * Para "Anotar en lista de espera": lo que le toca de su plan o lo que perdió, para anotarlo para eso. Solo llena el
     * tratamiento y el doctor (la anotación no guarda la sesión). Null si ya se eligió eso mismo.
     *
     * @return Notice|null
     */
    public static function toWaitFor(Patient $patient, User $user, ?int $treatmentId): ?array
    {
        $treatment = $treatmentId !== null ? Treatment::find($treatmentId) : null;
        $planned = self::planItemToBook($patient, $user, forToday: false);
        $notice = $planned !== null && ($planned['fill']['treatment_id'] ?? null) !== $treatmentId
            ? $planned
            : self::missedVisit($patient, forToday: false, treatment: $treatment);

        if ($notice === null) {
            return null;
        }

        return [
            'button' => $notice['key'] === 'planItemToBook' ? 'Esperar esa sesión' : 'Esperar eso',
            'fill' => array_intersect_key($notice['fill'], array_flip(['treatment_id', 'doctor_id'])),
        ] + $notice;
    }

    /**
     * "Debe S/ 500 · cuota vencida desde el 1 de noviembre. Faltó a 2 de sus últimas 5 citas: confírmale por llamada."
     * Para "Nueva cita": recepción lo recuerda al agendar. Null si no hay nada que decir.
     */
    public static function reminders(Patient $patient, User $user): ?string
    {
        $insights = app(PatientInsights::class);
        $balance = $user->can('viewAny', Charge::class) ? $patient->balance() : 0.0;
        $late = $balance > 0 ? $insights->lateInstallments($patient) : null;
        $noShows = PatientInsights::noShowsLabel($insights->noShows($patient));

        return collect([
            $balance > 0 ? 'Debe '.Money::format($balance).($late ? ' · cuota vencida desde el '.self::day($late['since']) : '') : null,
            $noShows ? $noShows.': confírmale por llamada' : null,
        ])->filter()->map(fn (string $line) => $line.'.')->implode(' ') ?: null;
    }

    /**
     * Se eligió el mismo tratamiento que una sesión de su plan sin marcarla: se pregunta, no se marca sola, porque
     * puede venir por lo mismo en otra pieza y entonces el plan daría por hecha una sesión que no se hizo.
     *
     * @return Notice|null
     */
    private static function sameAsPlan(Patient $patient, User $user, ?Treatment $treatment): ?array
    {
        if ($treatment === null) {
            return null;
        }

        $plans = app(TreatmentPlanService::class);
        $items = $plans->workableItems($patient)->where('treatment_id', $treatment->getKey());
        $booked = $plans->bookedSessions($items->map(fn (TreatmentPlanItem $item) => $item->getKey())->all());
        $item = $items->first(fn (TreatmentPlanItem $item) => TreatmentPlanService::nextSessionToBook($item, $booked[$item->getKey()] ?? 0) !== null);

        if ($item === null) {
            return null;
        }

        /** @var TreatmentPlan $plan */
        $plan = $item->plan;

        return self::make(
            'sameAsPlan',
            '¿Es la sesión de su plan?',
            self::itemLabel($item, $user, $booked[$item->getKey()] ?? 0).' está en su '.($plan->accepted_at === null ? 'presupuesto, que todavía no acepta' : 'plan')
                .'. Si es eso, márcala: se cobra lo acordado y queda hecha al atenderla.',
            warn: false,
            button: 'Sí, es esa sesión',
            fill: ['treatment_plan_item_id' => $item->getKey()],
        );
    }

    /**
     * Se eligió una evaluación para quien ya la tuvo y tiene un plan: casi siempre viene por su plan.
     *
     * @return Notice|null
     */
    private static function evaluationAgain(Patient $patient, User $user, ?Treatment $treatment): ?array
    {
        if ($treatment === null || ! $treatment->is_evaluation || ! TreatmentPlan::query()->where('patient_id', $patient->getKey())->open()->exists()) {
            return null;
        }

        $last = Appointment::query()
            ->where('patient_id', $patient->getKey())
            ->where('status', AppointmentStatus::Completed)
            ->whereHas('treatment', fn (Builder $treatments) => $treatments->where('is_evaluation', true))
            ->latest('starts_at')
            ->first();

        if ($last === null) {
            return null;
        }

        $item = app(TreatmentPlanService::class)->nextToSchedule($patient, evenIfNotAccepted: true);

        return self::make(
            'evaluationAgain',
            'Ya tuvo su evaluación el '.self::day(self::startsAt($last)),
            'Y ya tiene un plan. ¿Es otra evaluación? Si viene por su plan, elige la sesión.',
            warn: true,
            button: $item ? 'Es por su plan: '.$item->label(withTooth: $user->canSeeClinical()) : null,
            fill: $item ? self::itemFill($item) : [],
        );
    }

    /**
     * Lo siguiente de su plan aceptado que todavía no tiene cita.
     *
     * @return Notice|null
     */
    private static function planItemToBook(Patient $patient, User $user, bool $forToday): ?array
    {
        $item = app(TreatmentPlanService::class)->nextToSchedule($patient);

        if ($item === null) {
            return null;
        }

        /** @var TreatmentPlan $plan */
        $plan = $item->plan;
        /** @var Doctor|null $doctor */
        $doctor = $plan->doctor;

        return self::make(
            'planItemToBook',
            'Le toca de su plan: '.self::itemLabel($item, $user, 0),
            collect([
                $doctor && SlotPicker::onlyActiveDoctorId() === null ? 'Su plan es con '.$doctor->name.'.' : null,
                $forToday ? '¿Viene por eso?' : 'Todavía no está agendada.',
            ])->filter()->implode(' '),
            warn: false,
            button: $forToday ? 'Sí, viene por eso' : 'Agendar esa sesión',
            fill: self::itemFill($item),
        );
    }

    /**
     * El presupuesto que se le presentó hace una semana o más y no respondió: al agendarle, se le pregunta.
     *
     * @return Notice|null
     */
    private static function planAwaitingAnswer(Patient $patient, User $user): ?array
    {
        $plan = app(PatientInsights::class)->planAwaitingAnswer($patient);

        if ($plan === null) {
            return null;
        }

        /** @var CarbonInterface $proposedAt */
        $proposedAt = $plan->proposed_at;
        /** @var TreatmentPlanItem|null $first */
        $first = $plan->items->first();
        $what = filled($plan->title) ? $plan->title : ($first?->label(withTooth: $user->canSeeClinical()) ?? 'Su presupuesto');

        return self::make(
            'planAwaitingAnswer',
            'Presupuesto sin respuesta',
            $what.' · '.Money::format($plan->total()).' · se le presentó el '.self::day($proposedAt).'. Pregúntale si lo acepta.',
            warn: true,
            button: $user->can('view', $plan) ? 'Ver presupuesto' : null,
            url: $user->can('view', $plan) ? TreatmentPlanResource::getUrl('view', ['record' => $plan]) : null,
        );
    }

    /**
     * La cita a la que faltó (o que se canceló) hace poco, si después no vino ni tiene otra.
     *
     * @return Notice|null
     */
    private static function missedVisit(Patient $patient, bool $forToday, ?Treatment $treatment): ?array
    {
        $missed = app(PatientInsights::class)->missedVisit($patient);

        // Si ya se le está agendando eso mismo, no hay nada que agregar.
        if ($missed === null || $treatment?->getKey() === $missed->treatment_id) {
            return null;
        }

        /** @var AppointmentStatus $status */
        $status = $missed->status;
        /** @var Treatment $missedTreatment */
        $missedTreatment = $missed->treatment;
        $doctorIsActive = Doctor::query()->active()->whereKey($missed->doctor_id)->exists();

        return self::make(
            'missedVisit',
            ($status === AppointmentStatus::NoShow ? 'Faltó a su cita del ' : 'Se canceló su cita del ').self::day(self::startsAt($missed)),
            $missedTreatment->name.' · sigue sin otra cita.'.($forToday ? ' ¿Viene por eso?' : ''),
            warn: true,
            button: $forToday ? 'Sí, viene por eso' : 'Reagendar eso',
            fill: ['treatment_id' => $missed->treatment_id] + ($doctorIsActive ? ['doctor_id' => $missed->doctor_id] : []),
        );
    }

    /**
     * Lo que se llena al tocar el botón de una sesión del plan: la sesión, su tratamiento y el doctor del plan.
     *
     * @return array<string, int|null>
     */
    private static function itemFill(TreatmentPlanItem $item): array
    {
        /** @var TreatmentPlan $plan */
        $plan = $item->plan;
        $doctorIsActive = Doctor::query()->active()->whereKey($plan->doctor_id)->exists();

        return array_filter([
            'treatment_plan_item_id' => $item->getKey(),
            'treatment_id' => $item->treatment_id,
            'doctor_id' => $doctorIsActive ? $plan->doctor_id : null,
        ]);
    }

    /** "Endodoncia · sesión 2 de 3" (la pieza, solo para quien ve lo clínico). */
    private static function itemLabel(TreatmentPlanItem $item, User $user, int $booked): string
    {
        $session = TreatmentPlanService::nextSessionToBook($item, $booked);

        return $item->label(withTooth: $user->canSeeClinical())
            .($item->sessions_planned > 1 && $session !== null ? ' · sesión '.$session.' de '.$item->sessions_planned : '');
    }

    /** "13 de noviembre". */
    private static function day(CarbonInterface $date): string
    {
        return $date->translatedFormat('j \d\e F');
    }

    private static function startsAt(Appointment $appointment): CarbonInterface
    {
        /** @var CarbonInterface $startsAt */
        $startsAt = $appointment->starts_at;

        return $startsAt;
    }

    /**
     * @param  array<string, int|null>  $fill
     * @return Notice
     */
    private static function make(string $key, string $heading, string $detail, bool $warn, ?string $button = null, array $fill = [], ?string $url = null): array
    {
        return ['key' => $key, 'heading' => $heading, 'detail' => $detail, 'warn' => $warn, 'button' => $button, 'fill' => $fill, 'url' => $url];
    }
}
