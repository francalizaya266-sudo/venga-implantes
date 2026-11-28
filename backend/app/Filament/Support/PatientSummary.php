<?php

namespace App\Filament\Support;

use App\Enums\AppointmentStatus;
use App\Enums\LabOrderStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\PatientRelationship;
use App\Enums\PaymentMethod;
use App\Filament\Concerns\HasPatientSummaryActions;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Consultations\ConsultationResource;
use App\Filament\Resources\LabOrders\LabOrderResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\Clinical\ConsentService;
use App\Services\Dashboard\FollowUps;
use App\Services\Patients\PatientInsights;
use App\Support\Money;
use App\Support\Phone;

/**
 * Lo que hay que saber de un paciente de un vistazo, bajo su nombre: primero el siguiente paso (continuar la
 * atención, firmar, agendar lo que falta del plan, recordar el presupuesto o llamarlo a control) con su botón,
 * y debajo, en etiquetas, cuánto debe, cuándo vuelve, si suele faltar, cuándo suele venir, cómo paga, si cumple
 * años y cómo nos encontró. Cada cosa lleva a donde se resuelve. El color dice si hay algo que hacer.
 *
 * @phpstan-type Chip array{label: string, color: string, icon: string, url: string|null, action: array{name: string, arguments: array<string, mixed>}|null}
 * @phpstan-type StepAction array{name: string, arguments: array<string, mixed>}
 * @phpstan-type Step array{key: string, label: string, detail: string|null, button: string|null, icon: string, url: string|null, action: StepAction|null, remind: StepAction|null}
 */
final class PatientSummary
{
    /**
     * Lo que muestra la vista del resumen. Los botones que abren una ventana solo van en las páginas que la
     * tienen (la ficha y la cita); en las demás, el resumen lleva a donde se hace.
     *
     * @param  bool  $withWhatsapp  El enlace para escribirle. La página de la cita no lo lleva: su WhatsApp es el botón de la cita.
     * @param  bool  $withProfile  En la ficha, el celular y las preferencias van en su perfil, al costado: aquí queda solo el estado.
     * @return array{step: Step|null, chips: list<Chip>, contact: array{whatsapp: string|null, call: string}|null}
     */
    public static function viewData(Patient $patient, User $user, mixed $livewire = null, bool $withWhatsapp = true, bool $withProfile = false): array
    {
        $withActions = $livewire !== null && in_array(HasPatientSummaryActions::class, class_uses_recursive($livewire), true);
        $step = self::nextStep($patient, $user, $withActions);
        $contact = $withProfile ? null : self::contact($patient);

        return [
            'step' => $step,
            'chips' => self::chips($patient, $user, $withActions, $step['key'] ?? null, withPreferences: ! $withProfile),
            'contact' => $contact && ! $withWhatsapp ? [...$contact, 'whatsapp' => null] : $contact,
        ];
    }

    /**
     * Lo que toca hacer con el paciente, en este orden: terminar la atención que quedó abierta, firmar el
     * consentimiento de la cita de hoy, agendar lo que sigue de su plan, recordarle el presupuesto que no
     * respondió, darle otro horario si faltó o canceló, o llamarlo a su control. Cada uno solo para quien lo puede hacer.
     *
     * @return Step|null
     */
    public static function nextStep(Patient $patient, User $user, bool $withActions = false): ?array
    {
        $insights = app(PatientInsights::class);

        $consultation = $insights->openConsultation($patient);

        if ($consultation && $user->can('update', $consultation)) {
            return self::step('consultation', 'Continuar la atención', collect([
                $consultation->appointment?->treatment?->name,
                'empezó '.($consultation->attended_at ?? $consultation->created_at)->diffForHumans(),
            ])->filter()->implode(' · '), 'Continuar', 'heroicon-m-play', url: ConsultationResource::urlFor($consultation));
        }

        $visit = $user->can('updateClinical', $patient) ? $insights->visitMissingConsent($patient) : null;

        if ($visit) {
            return self::step('consent', 'Falta firmar el consentimiento', $visit->treatment->name.' · cita de hoy a las '.$visit->starts_at->format('H:i'), 'Firmar', 'heroicon-m-pencil-square',
                url: $withActions ? null : PatientResource::getUrl('clinical', ['record' => $patient]),
                action: $withActions ? ['name' => 'consentForVisit', 'arguments' => ['appointment' => $visit->getKey()]] : null);
        }

        $canBook = $user->can('create', Appointment::class);
        $item = $canBook ? $insights->itemToSchedule($patient) : null;

        if ($item) {
            $sessions = $item->sessions_planned > 1 ? 'sesión '.min($item->sessions_done + 1, $item->sessions_planned).' de '.$item->sessions_planned : null;

            return self::step('schedule', 'Agendar: '.$item->label(withTooth: $user->canSeeClinical()), collect([
                $sessions ? ucfirst($sessions) : null,
                'de su plan'.($item->plan->doctor ? ' con '.$item->plan->doctor->name : ''),
            ])->filter()->implode(' · '), 'Agendar', 'heroicon-m-calendar-days', url: AppointmentResource::nextVisitUrl($patient, null));
        }

        $plan = $insights->planAwaitingAnswer($patient);

        if ($plan) {
            return self::step('plan_answer', 'Presupuesto sin respuesta', Money::format($plan->total()).' · se le presentó '.$plan->proposed_at->diffForHumans(), 'Recordar por WhatsApp', 'heroicon-m-clipboard-document-list',
                action: $withActions && $patient->account ? ['name' => 'followUp', 'arguments' => ['template' => MessageTemplateKey::PlanFollowUp->value]] : null);
        }

        return self::recallStep($patient, $insights, $canBook, $withActions);
    }

    /**
     * Faltó o canceló y sigue sin otra cita, o le toca su control: agendar, o recordárselo por WhatsApp si todavía
     * no se le escribió.
     *
     * @return Step|null
     */
    private static function recallStep(Patient $patient, PatientInsights $insights, bool $canBook, bool $withActions): ?array
    {
        $missed = $insights->missedVisit($patient);

        if ($missed) {
            $contacted = self::contactedLabel($patient, MessageTemplateKey::MissedVisit);

            return self::step('missed', ($missed->status === AppointmentStatus::NoShow ? 'Faltó a su cita del ' : 'Se canceló su cita del ').$missed->starts_at->translatedFormat('j \d\e F'), collect([$missed->treatment->name, 'sigue sin otra cita', $contacted])->filter()->implode(' · '), $canBook ? 'Agendar' : null, 'heroicon-m-arrow-path',
                url: $canBook ? AppointmentResource::newUrl(['patient' => $patient->getKey(), 'treatment' => $missed->treatment_id, 'doctor' => $missed->doctor_id]) : null,
                remind: self::remind($patient, MessageTemplateKey::MissedVisit, $withActions && $contacted === null));
        }

        $checkup = $insights->checkup($patient);

        if ($checkup && $checkup['due'] && ! $insights->hasUpcomingVisit($patient)) {
            $contacted = self::contactedLabel($patient, MessageTemplateKey::CheckupRecall);

            return self::step('checkup', 'Le toca su '.$checkup['kind']->checkupLabel(), collect(['Última visita '.$patient->last_visit_at->diffForHumans(), $contacted])->filter()->implode(' · '), $canBook ? 'Agendar' : null, 'heroicon-m-clock',
                url: $canBook ? AppointmentResource::newUrl(['patient' => $patient->getKey()]) : null,
                remind: self::remind($patient, MessageTemplateKey::CheckupRecall, $withActions && $contacted === null));
        }

        return null;
    }

    /** El encabezado: "38 años · DNI 60075025 · paciente desde oct. 2026". El distrito va en su dirección, en el perfil. */
    public static function subheading(Patient $patient): ?string
    {
        $since = $patient->first_visit_at;

        return collect([
            $patient->age !== null ? $patient->age.($patient->age === 1 ? ' año' : ' años') : null,
            $patient->document_number ? $patient->document_type?->getLabel().' '.$patient->document_number : null,
            $since ? 'paciente desde '.$since->translatedFormat('M Y') : null,
        ])->filter()->implode(' · ') ?: null;
    }

    /**
     * Las etiquetas, sin repetir lo que ya dice el siguiente paso. Sin las preferencias (cuándo suele venir, cómo
     * paga, cómo nos encontró), quedan solo las del estado: la ficha las muestra en su perfil.
     *
     * @return list<Chip>
     */
    public static function chips(Patient $patient, User $user, bool $withActions = false, ?string $step = null, bool $withPreferences = true): array
    {
        $insights = app(PatientInsights::class);
        $seesMoney = $user->can('viewAny', Charge::class);
        $next = AppointmentResource::getEloquentQuery()
            ->whereBelongsTo($patient)
            ->where('ends_at', '>', now())
            ->whereIn('status', AppointmentStatus::changeable())
            ->orderBy('starts_at')
            ->first();

        return array_map(fn (array $chip) => $chip + ['action' => null], array_values(array_filter([
            $seesMoney ? self::balance($patient, $insights, $withActions && $user->can('create', Payment::class)) : null,
            self::nextVisit($patient, $next),
            $next === null ? self::lastVisit($patient, $insights, $step) : null,
            self::plan($patient, $user, $step),
            self::labWork($patient, $user),
            self::noShows($patient, $insights),
            self::birthday($patient, $withActions),
            self::guardian($patient, $user),
            ...($withPreferences ? self::preferences($patient, $user) : []),
        ])));
    }

    /**
     * Lo que conviene saber al agendarle y al cobrarle: cuándo suele venir, con qué suele pagar y cómo nos encontró
     * (si falta, se pide).
     *
     * @return list<Chip>
     */
    public static function preferences(Patient $patient, User $user): array
    {
        return array_map(fn (array $chip) => $chip + ['action' => null], array_values(array_filter([
            self::usualVisit($patient, app(PatientInsights::class)),
            $user->can('viewAny', Charge::class) ? self::usualPayment($patient) : null,
            self::origin($patient, $user),
        ])));
    }

    /**
     * "3 visitas · S/ 350.00 pagados · sin faltas": cuánto viene, cuánto lleva pagado (solo quien ve la caja) y si falta.
     */
    public static function profileLine(Patient $patient, User $user): string
    {
        $visits = $patient->appointments()->where('status', AppointmentStatus::Completed)->count();
        $missed = $patient->appointments()->where('status', AppointmentStatus::NoShow)->count();
        $paid = $user->can('viewAny', Charge::class) ? round((float) $patient->payments()->whereNull('voided_at')->sum('amount'), 2) : null;

        return collect([
            match ($visits) {
                0 => 'Sin visitas aún',
                1 => '1 visita',
                default => $visits.' visitas',
            },
            match (true) {
                $paid === null => null,
                $paid > 0 => Money::format($paid).' pagados',
                default => 'sin pagos',
            },
            match ($missed) {
                0 => 'sin faltas',
                1 => '1 falta',
                default => $missed.' faltas',
            },
        ])->filter()->implode(' · ');
    }

    /** "Le escribiste hace 3 días": si se le mandó este aviso hace poco (los mismos días que cuenta Hoy). */
    public static function contactedLabel(Patient $patient, MessageTemplateKey $template): ?string
    {
        $sentAt = app(FollowUps::class)->recentlyContacted([$patient->getKey()], $template)[$patient->getKey()] ?? null;

        return $sentAt ? 'le escribiste '.($sentAt->isToday() ? 'hoy' : $sentAt->diffForHumans()) : null;
    }

    /**
     * Para escribirle o llamarlo desde la ficha.
     *
     * @return array{whatsapp: string, call: string}|null
     */
    public static function contact(Patient $patient): ?array
    {
        $phone = $patient->account?->phone;

        return $phone ? ['whatsapp' => 'https://wa.me/'.Phone::forWhatsApp($phone), 'call' => 'tel:'.Phone::normalize($phone)] : null;
    }

    /**
     * Cuánto debe, y si una cuota se le pasó. Es el único lugar de la ficha con el saldo: lo que debe se cobra
     * ahí mismo y lo que tiene a favor, ahí mismo se le devuelve.
     *
     * @return Chip
     */
    private static function balance(Patient $patient, PatientInsights $insights, bool $canCollect): array
    {
        $balance = $patient->balance();
        $late = $balance > 0 ? $insights->lateInstallments($patient) : null;

        return [
            'label' => Money::balance($balance).($late ? ' · cuota vencida el '.$late['since']->translatedFormat('j M') : ''),
            'color' => match (true) {
                $balance > 0 => 'danger',
                $balance < 0 => 'info',
                default => 'success',
            },
            'icon' => 'heroicon-m-banknotes',
            'url' => null,
            'action' => match (true) {
                ! $canCollect || $balance == 0 => null,
                $balance > 0 => ['name' => 'collectPayment', 'arguments' => []],
                default => ['name' => 'refund', 'arguments' => []],
            },
        ];
    }

    /** @return Chip */
    private static function nextVisit(Patient $patient, ?Appointment $next): array
    {
        if ($next === null) {
            return [
                'label' => 'Sin cita agendada',
                'color' => 'gray',
                'icon' => 'heroicon-m-calendar',
                'url' => AppointmentResource::newUrl(['patient' => $patient->getKey()]),
            ];
        }

        $when = match (true) {
            $next->starts_at->isToday() => 'hoy',
            $next->starts_at->isTomorrow() => 'mañana',
            default => $next->starts_at->translatedFormat('D j M'),
        };

        return [
            'label' => 'Próxima cita: '.$when.' · '.$next->starts_at->format('H:i'),
            'color' => 'info',
            'icon' => 'heroicon-m-calendar-days',
            'url' => AppointmentResource::getUrl('view', ['record' => $next]),
        ];
    }

    /**
     * Sin cita por delante: cuándo vino por última vez y, si ya pasó el tiempo de su control (según lo último
     * que se le hizo), que le toca. Si el siguiente paso ya lo dice, no se repite.
     *
     * @return Chip|null
     */
    private static function lastVisit(Patient $patient, PatientInsights $insights, ?string $step): ?array
    {
        $last = $patient->last_visit_at;

        // Con su primera atención en curso ya vino, aunque todavía no se cierre.
        if ($last === null) {
            return $step === 'consultation' ? null : ['label' => 'Aún no viene', 'color' => 'gray', 'icon' => 'heroicon-m-sparkles', 'url' => null];
        }

        $checkup = $step === 'checkup' ? null : $insights->checkup($patient);
        $due = $checkup !== null && $checkup['due'];

        return [
            'label' => ($due ? 'Le toca su '.$checkup['kind']->checkupLabel().' · ' : '').'Última visita '.$last->diffForHumans(),
            'color' => $due ? 'warning' : 'gray',
            'icon' => 'heroicon-m-clock',
            'url' => $due ? AppointmentResource::newUrl(['patient' => $patient->getKey()]) : null,
        ];
    }

    /**
     * Su plan: cómo va el que aceptó ("Plan: 1 de 3 hechos · 1 en curso"), con el aviso si le quedan procedimientos
     * y ninguna cita; o el presupuesto que no respondió. Lo que ya dice el siguiente paso no se repite.
     *
     * @return Chip|null
     */
    private static function plan(Patient $patient, User $user, ?string $step): ?array
    {
        $patients = Patient::query()->whereKey($patient->getKey());
        /** @var TreatmentPlan|null $plan */
        $plan = $patient->treatmentPlans()->whereIn('status', TreatmentPlan::ACCEPTED_STATUSES)->with('items')->oldest('id')->first();

        if ($plan !== null) {
            $unscheduled = $step !== 'schedule' && (clone $patients)->withUnscheduledPlan()->exists();

            return [
                'label' => 'Plan: '.$plan->progressText().($unscheduled ? ' · falta agendar' : ''),
                'color' => $unscheduled ? 'warning' : 'gray',
                'icon' => 'heroicon-m-clipboard-document-list',
                'url' => match (true) {
                    $unscheduled => AppointmentResource::newUrl(['patient' => $patient->getKey()]),
                    $user->can('viewBudget', $plan) => TreatmentPlanResource::getUrl('view', ['record' => $plan]),
                    default => null,
                },
                'action' => null,
            ];
        }

        return $step !== 'plan_answer' && (clone $patients)->withPlanAwaitingAnswer()->exists()
            ? ['label' => 'Presupuesto sin respuesta', 'color' => 'warning', 'icon' => 'heroicon-m-clipboard-document-list', 'url' => null, 'action' => null]
            : null;
    }

    /**
     * Lo que tiene en el laboratorio dental: cuándo llega, o lo que hay que resolver (se atrasó, su cita es antes
     * de la entrega, ya llegó y falta citarlo).
     *
     * @return Chip|null
     */
    private static function labWork(Patient $patient, User $user): ?array
    {
        if (! $user->can('viewAny', LabOrder::class)) {
            return null;
        }

        /** @var LabOrder|null $order */
        $order = LabOrderResource::getEloquentQuery()
            ->where('patient_id', $patient->getKey())
            ->where(fn ($orders) => $orders->pending()->orWhere(fn ($arrived) => $arrived->needingAttention()))
            ->orderBy('due_on')
            ->first();

        if ($order === null) {
            return null;
        }

        $alert = $order->alert();

        return [
            'label' => 'Laboratorio: '.$order->description.' · '.($alert ? mb_strtolower(mb_substr($alert, 0, 1)).mb_substr($alert, 1) : 'llega el '.$order->due_on->translatedFormat('D j M')),
            'color' => match (true) {
                $alert === null => 'info',
                $order->status() === LabOrderStatus::Late => 'danger',
                default => 'warning',
            },
            'icon' => 'heroicon-m-beaker',
            'url' => LabOrderResource::getUrl('index', ['tab' => $alert ? 'attention' : 'pending', 'search' => $patient->full_name]),
        ];
    }

    /**
     * Que faltó a varias de sus últimas citas: mejor confirmarle por llamada.
     *
     * @return Chip|null
     */
    private static function noShows(Patient $patient, PatientInsights $insights): ?array
    {
        $label = PatientInsights::noShowsLabel($insights->noShows($patient));
        $phone = $patient->account?->phone;

        return $label ? ['label' => $label, 'color' => 'warning', 'icon' => 'heroicon-m-phone', 'url' => $phone ? 'tel:'.Phone::normalize($phone) : null] : null;
    }

    /**
     * Cumple hoy: el saludo por WhatsApp, ya escrito.
     *
     * @return Chip|null
     */
    private static function birthday(Patient $patient, bool $withActions): ?array
    {
        $birth = $patient->birth_date;

        if ($birth === null || ! Patient::query()->whereKey($patient->getKey())->birthdayOn(today())->exists()) {
            return null;
        }

        $canGreet = $withActions && $patient->account !== null;

        return [
            'label' => 'Hoy cumple '.$patient->age.' años'.($canGreet ? ' · saludar' : ''),
            'color' => 'success',
            'icon' => 'heroicon-m-cake',
            'url' => null,
            'action' => $canGreet ? ['name' => 'followUp', 'arguments' => ['template' => MessageTemplateKey::Birthday->value]] : null,
        ];
    }

    /**
     * "Suele venir los martes en la mañana": al agendar, esos horarios salen primero.
     *
     * @return Chip|null
     */
    private static function usualVisit(Patient $patient, PatientInsights $insights): ?array
    {
        $usual = $insights->usualVisit($patient);

        return $usual ? ['label' => $usual['label'], 'color' => 'gray', 'icon' => 'heroicon-m-calendar', 'url' => null] : null;
    }

    /**
     * Con qué suele pagar: al cobrarle, ese medio ya viene marcado.
     *
     * @return Chip|null
     */
    private static function usualPayment(Patient $patient): ?array
    {
        if (! $patient->payments()->whereNull('voided_at')->exists()) {
            return null;
        }

        $label = match ($method = $patient->usualPaymentMethod()) {
            PaymentMethod::Cash => 'Paga en efectivo',
            PaymentMethod::Transfer => 'Paga por transferencia',
            PaymentMethod::Other => null,
            default => 'Paga con '.($method === PaymentMethod::Card ? 'tarjeta' : $method->getLabel()),
        };

        return $label ? ['label' => $label, 'color' => 'gray', 'icon' => 'heroicon-m-credit-card', 'url' => null] : null;
    }

    /**
     * Un menor sin apoderado ni titular que firme por él: se pide antes de su primer consentimiento.
     *
     * @return Chip|null
     */
    private static function guardian(Patient $patient, User $user): ?array
    {
        if (! $patient->isMinor() || app(ConsentService::class)->defaultSigner($patient)['signer_relationship'] !== PatientRelationship::Self) {
            return null;
        }

        return [
            'label' => 'Falta: apoderado',
            'color' => 'warning',
            'icon' => 'heroicon-m-user-group',
            'url' => $user->can('update', $patient) ? PatientResource::getUrl('edit', ['record' => $patient]) : null,
        ];
    }

    /**
     * Cómo nos encontró. Si falta, se pide: sin ese dato, Reportes no sabe qué publicidad funciona.
     *
     * @return Chip|null
     */
    private static function origin(Patient $patient, User $user): ?array
    {
        if ($patient->origin !== null) {
            return ['label' => 'Origen: '.$patient->origin->getLabel(), 'color' => 'gray', 'icon' => 'heroicon-m-megaphone', 'url' => null];
        }

        return $user->can('update', $patient)
            ? ['label' => 'Falta: ¿cómo nos encontró?', 'color' => 'warning', 'icon' => 'heroicon-m-megaphone', 'url' => PatientResource::getUrl('edit', ['record' => $patient])]
            : null;
    }

    /**
     * "Recordar por WhatsApp" con el mismo aviso de Hoy, al lado de "Agendar": solo si tiene celular y no se le
     * escribió hace poco.
     *
     * @return StepAction|null
     */
    private static function remind(Patient $patient, MessageTemplateKey $template, bool $canRemind): ?array
    {
        return $canRemind && $patient->account !== null ? ['name' => 'followUp', 'arguments' => ['template' => $template->value]] : null;
    }

    /**
     * @param  StepAction|null  $action
     * @param  StepAction|null  $remind
     * @return Step
     */
    private static function step(string $key, string $label, ?string $detail, ?string $button, string $icon, ?string $url = null, ?array $action = null, ?array $remind = null): array
    {
        return ['key' => $key, 'label' => $label, 'detail' => $detail ?: null, 'button' => $url || $action ? $button : null, 'icon' => $icon, 'url' => $url, 'action' => $action, 'remind' => $remind];
    }
}
