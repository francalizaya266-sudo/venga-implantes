<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\LabOrderStatus;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\LabOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;

/**
 * Trabajo que se mandó al laboratorio dental (corona, prótesis, férula) para un paciente: cuándo salió, cuándo
 * lo entregan y cuánto cuesta. Avisa si se atrasa, si el paciente tiene cita antes de que llegue y si ya llegó
 * y nadie lo citó para colocarlo. Al pagarlo queda anotado en los gastos de Caja.
 */
#[Fillable(['patient_id', 'doctor_id', 'description', 'laboratory', 'sent_on', 'due_on', 'received_on', 'cost', 'expense_id', 'notes', 'created_by_user_id'])]
class LabOrder extends Model
{
    /** @use HasFactory<LabOrderFactory> */
    use Auditable, HasFactory;

    /** Días que un trabajo ya recibido sigue pidiendo cita para colocarlo. */
    public const PLACE_WITHIN_DAYS = 60;

    /** Comienzos de palabra (sin tildes) de los tratamientos que se hacen en el laboratorio. */
    private const WORK_WORDS = ['corona', 'protesis', 'puente', 'incrustac', 'carilla', 'ferula'];

    /** Si el tratamiento, por su nombre, suele salir al laboratorio: "Corona de porcelana", "Prótesis total". */
    public static function isLabWork(?Treatment $treatment): bool
    {
        $words = ' '.Str::of((string) $treatment?->name)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->trim();

        return collect(self::WORK_WORDS)->contains(fn (string $word) => str_contains($words, ' '.$word));
    }

    /**
     * Si el paciente tiene un trabajo de laboratorio en curso: todavía no llega, o llegó hace poco y falta
     * colocarlo.
     */
    public static function inProgressFor(int $patientId): bool
    {
        return self::query()
            ->where('patient_id', $patientId)
            ->where(fn (Builder $query) => $query
                ->whereNull('received_on')
                ->orWhere('received_on', '>=', today()->subDays(self::PLACE_WITHIN_DAYS)))
            ->exists();
    }

    protected function casts(): array
    {
        return [
            'sent_on' => 'date',
            'due_on' => 'date',
            'received_on' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** El gasto con que se le pagó al laboratorio. */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function status(): LabOrderStatus
    {
        return match (true) {
            $this->received_on !== null => LabOrderStatus::Received,
            $this->due_on->isBefore(today()) => LabOrderStatus::Late,
            default => LabOrderStatus::Pending,
        };
    }

    /** Se pagó y ese gasto sigue en pie (no se anuló). */
    public function isPaid(): bool
    {
        return $this->expense_id !== null && $this->expense?->voided_at === null;
    }

    /**
     * La próxima cita del paciente que todavía no pasó. La lista la trae ya calculada (withNextAppointment).
     */
    public function nextAppointmentAt(): ?CarbonImmutable
    {
        $at = array_key_exists('next_appointment_at', $this->attributes)
            ? $this->attributes['next_appointment_at']
            : self::nextAppointmentQuery($this->patient_id)->value('starts_at');

        return $at ? CarbonImmutable::parse($at) : null;
    }

    /**
     * Lo que hay que resolver con este trabajo, si hay algo: "Su cita del jue. 12 nov. es antes de la entrega",
     * "Debía llegar hace 3 días" (y si ya tiene cita, cuándo) o "Ya llegó y no tiene cita para colocarlo".
     */
    public function alert(): ?string
    {
        $next = $this->nextAppointmentAt();

        return match (true) {
            $this->received_on !== null => $next === null && $this->received_on->gte(today()->subDays(self::PLACE_WITHIN_DAYS)) && ! $this->visitedSinceArrival()
                ? 'Ya llegó y no tiene cita para colocarlo'
                : null,
            $next !== null && $next->startOfDay()->lt($this->due_on) => 'Su cita del '.$next->translatedFormat('D j M').' es antes de la entrega',
            // Atrasado y con el paciente citado: es lo primero que hay que reclamar.
            $this->due_on->isBefore(today()) => 'Debía llegar '.$this->due_on->diffForHumans(today(), ['syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW, 'parts' => 1])
                .($next !== null ? ' y tiene cita el '.$next->translatedFormat('D j M') : ''),
            $this->due_on->isToday() => 'Llega hoy: confirma que lo trajeron',
            default => null,
        };
    }

    /**
     * Lo que el doctor tiene que saber de este trabajo antes de atender al paciente ("Tu próximo paciente"): si ya
     * llegó, o si sigue en el laboratorio y si se atrasó. Null si ya se atendió al paciente desde que llegó: se
     * colocó, o esa visita ya pasó. "tag" es lo corto que va en la etiqueta ("Corona: llegó hoy") y "text" la frase
     * entera, al pasar el mouse.
     *
     * @return array{tag: string, text: string, tone: 'success'|'warning'|'danger'}|null
     */
    public function beforeVisit(): ?array
    {
        /** @var CarbonInterface|null $received */
        $received = $this->received_on;
        /** @var CarbonInterface $due */
        $due = $this->due_on;

        if ($received !== null) {
            return $this->attendedSinceArrival() ? null : [
                'tag' => $this->description.': llegó '.($received->isToday() ? 'hoy' : 'el '.$received->translatedFormat('j M')),
                'text' => $this->description.': ya llegó del laboratorio'.($received->isToday() ? ' hoy' : ' el '.$received->translatedFormat('j \d\e F')).'.',
                'tone' => 'success',
            ];
        }

        return match (true) {
            $due->isBefore(today()) => [
                'tag' => $this->description.': no llegó, debía el '.$due->translatedFormat('j M'),
                'text' => $this->description.': no ha llegado del laboratorio; debía llegar '.$due->diffForHumans(today(), ['syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW, 'parts' => 1]).'.',
                'tone' => 'danger',
            ],
            $due->isToday() => [
                'tag' => $this->description.': llega hoy',
                'text' => $this->description.': llega hoy del laboratorio; confirma que lo trajeron.',
                'tone' => 'warning',
            ],
            default => [
                'tag' => $this->description.': llega el '.$due->translatedFormat('j M'),
                'text' => $this->description.': sigue en el laboratorio, llega el '.$due->translatedFormat('j \d\e F').'.',
                'tone' => 'warning',
            ],
        };
    }

    /** Lo que sigue en el laboratorio para ese paciente y lo que llegó hace poco: lo que se mira antes de atenderlo. */
    #[Scope]
    protected function forVisitOf(Builder $query, int $patientId): Builder
    {
        return $query
            ->where('patient_id', $patientId)
            ->where(fn (Builder $recent) => $recent
                ->whereNull('received_on')
                ->orWhereDate('received_on', '>=', today()->subDays(self::PLACE_WITHIN_DAYS)))
            ->orderBy('due_on');
    }

    /** Todavía en el laboratorio. */
    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->whereNull('received_on');
    }

    /**
     * Los que piden hacer algo hoy: llegan hoy o están atrasados, el paciente tiene cita antes de la entrega, o ya
     * llegaron y nadie lo citó para colocarlos.
     */
    #[Scope]
    protected function needingAttention(Builder $query): Builder
    {
        $appointments = fn (array $statuses): QueryBuilder => Appointment::query()
            ->toBase()
            ->whereColumn('appointments.patient_id', 'lab_orders.patient_id')
            ->whereIn('appointments.status', $statuses);

        return $query->where(fn (Builder $any) => $any
            ->where(fn (Builder $pending) => $pending
                ->whereNull('received_on')
                ->where(fn (Builder $problem) => $problem
                    ->whereDate('due_on', '<=', today())
                    ->orWhereExists($appointments(AppointmentStatus::open())->where('appointments.ends_at', '>', now())->whereColumn('appointments.starts_at', '<', 'lab_orders.due_on'))))
            ->orWhere(fn (Builder $arrived) => $arrived
                ->whereDate('received_on', '>=', today()->subDays(self::PLACE_WITHIN_DAYS))
                ->whereNotExists($appointments(AppointmentStatus::occupying())->whereColumn('appointments.starts_at', '>=', 'lab_orders.received_on'))));
    }

    /** Con la próxima cita del paciente en next_appointment_at, para no consultarla fila por fila. */
    #[Scope]
    protected function withNextAppointment(Builder $query): Builder
    {
        return $query->addSelect(['next_appointment_at' => self::nextAppointmentQuery()->select('starts_at')]);
    }

    /** Lo que sigue en el laboratorio para ese paciente y llega después de ese día: una cita ese día sería antes de la entrega. */
    #[Scope]
    protected function arrivingAfter(Builder $query, int $patientId, CarbonInterface $day): Builder
    {
        return $query->where('patient_id', $patientId)->whereNull('received_on')->whereDate('due_on', '>', $day);
    }

    private static function nextAppointmentQuery(?int $patientId = null): Builder
    {
        return Appointment::query()
            ->when(
                $patientId,
                fn (Builder $appointments) => $appointments->where('patient_id', $patientId),
                fn (Builder $appointments) => $appointments->whereColumn('appointments.patient_id', 'lab_orders.patient_id'),
            )
            ->whereIn('status', AppointmentStatus::open())
            ->where('ends_at', '>', now())
            ->orderBy('starts_at')
            ->limit(1);
    }

    /** Se le atendió desde que llegó el trabajo: ya se le colocó (o se vio que no hacía falta). */
    private function attendedSinceArrival(): bool
    {
        return Appointment::query()
            ->where('patient_id', $this->patient_id)
            ->where('status', AppointmentStatus::Completed)
            ->where('starts_at', '>=', $this->received_on)
            ->exists();
    }

    /** Vino (o tiene cita) desde que llegó el trabajo: ya se le colocó o ya está citado. */
    private function visitedSinceArrival(): bool
    {
        return Appointment::query()
            ->where('patient_id', $this->patient_id)
            ->whereIn('status', AppointmentStatus::occupying())
            ->where('starts_at', '>=', $this->received_on)
            ->exists();
    }
}
