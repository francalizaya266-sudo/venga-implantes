<?php

namespace App\Filament\Support;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\LabOrders\LabOrderResource;
use App\Models\Appointment;
use App\Models\LabOrder;
use App\Models\User;
use App\Services\Clinical\ConsentService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lo que tiene que estar listo para atender cada cita que viene, para las tarjetas de la agenda: el consentimiento
 * (solo si el tratamiento tiene uno, y solo para quien ve lo clínico) y lo que el paciente espera del laboratorio
 * (de lo que este usuario puede ver). Con unas pocas consultas para toda la agenda, no una por cita. Las citas ya
 * cerradas no llevan nada: ya no hay qué preparar.
 */
final class VisitReadiness
{
    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return array<int, array{consent: bool|null, lab: array{label: string, tone: string, title: string}|null}> Por id de la cita.
     */
    public static function for(Collection $appointments, User $user): array
    {
        $open = $appointments->filter(fn (Appointment $appointment) => self::isOpen($appointment))->values();
        $consents = $user->canSeeClinical() && $open->isNotEmpty() ? app(ConsentService::class)->signedForVisits($open) : [];
        $labs = LabOrderResource::canViewAny() && $open->isNotEmpty() ? self::labWork($open) : [];

        return $appointments->mapWithKeys(fn (Appointment $appointment) => [$appointment->getKey() => [
            'consent' => $consents[$appointment->getKey()] ?? null,
            'lab' => $open->contains($appointment) ? ($labs[$appointment->patient_id] ?? null) : null,
        ]])->all();
    }

    private static function isOpen(Appointment $appointment): bool
    {
        /** @var AppointmentStatus $status */
        $status = $appointment->status;

        return in_array($status, AppointmentStatus::open(), true);
    }

    /**
     * Por paciente, una etiqueta: "Lab atrasado" si algo debía llegar y no llegó, "Lab pendiente" si sigue allá,
     * "Lab listo" si llegó y todavía no se lo colocaron. Al pasar el mouse, cada trabajo con lo suyo.
     *
     * @param  Collection<int, Appointment>  $appointments
     * @return array<int, array{label: string, tone: string, title: string}> Por id del paciente.
     */
    private static function labWork(Collection $appointments): array
    {
        $patients = $appointments->pluck('patient_id')->unique()->values()->all();

        /** @var Builder<LabOrder> $query */
        $query = LabOrderResource::getEloquentQuery();
        $orders = $query
            ->setEagerLoads([])
            ->whereIn('patient_id', $patients)
            ->where(fn (Builder $recent) => $recent
                ->whereNull('received_on')
                ->orWhereDate('received_on', '>=', today()->subDays(LabOrder::PLACE_WITHIN_DAYS)))
            ->orderBy('due_on')
            ->get();

        if ($orders->isEmpty()) {
            return [];
        }

        // La última vez que se atendió a cada paciente: lo que llegó antes ya se le colocó.
        $lastAttended = Appointment::query()
            ->whereIn('patient_id', $orders->pluck('patient_id')->unique()->all())
            ->where('status', AppointmentStatus::Completed)
            ->where('starts_at', '>=', today()->subDays(LabOrder::PLACE_WITHIN_DAYS))
            ->toBase()
            ->selectRaw('patient_id, max(starts_at) as last_at')
            ->groupBy('patient_id')
            ->pluck('last_at', 'patient_id');

        return $orders
            ->groupBy('patient_id')
            ->map(fn (Collection $patientOrders, int|string $patientId) => self::summary($patientOrders, $lastAttended[$patientId] ?? null))
            ->filter()
            ->all();
    }

    /**
     * @param  Collection<int, LabOrder>  $orders
     * @return array{label: string, tone: string, title: string}|null
     */
    private static function summary(Collection $orders, ?string $lastAttendedAt): ?array
    {
        $parts = [];
        $tones = [];

        foreach ($orders as $order) {
            /** @var CarbonInterface|null $received */
            $received = $order->received_on;
            /** @var CarbonInterface $due */
            $due = $order->due_on;

            if ($received !== null) {
                if ($lastAttendedAt !== null && $received->lte($lastAttendedAt)) {
                    continue;
                }

                $parts[] = $order->description.': ya llegó, el '.$received->translatedFormat('j \d\e F');
                $tones[] = 'success';
            } elseif ($due->isBefore(today())) {
                $parts[] = $order->description.': no llegó, debía llegar el '.$due->translatedFormat('j \d\e F');
                $tones[] = 'danger';
            } else {
                $parts[] = $order->description.': sigue en el laboratorio, llega '.($due->isToday() ? 'hoy' : 'el '.$due->translatedFormat('j \d\e F'));
                $tones[] = 'warning';
            }
        }

        if ($parts === []) {
            return null;
        }

        return match (true) {
            in_array('danger', $tones, true) => ['label' => 'Lab atrasado', 'tone' => 'danger', 'title' => implode(' · ', $parts)],
            in_array('warning', $tones, true) => ['label' => 'Lab pendiente', 'tone' => 'warning', 'title' => implode(' · ', $parts)],
            default => ['label' => 'Lab listo', 'tone' => 'success', 'title' => implode(' · ', $parts)],
        };
    }
}
