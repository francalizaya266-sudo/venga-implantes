<?php

namespace App\Http\Resources\V1;

use App\Enums\PatientRelationship;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Lo primero para quien vuelve desde su celular recordado: lo suyo, con el botón de lo que sigue.
 * Sin montos ni pagos: eso sigue en Mi cuenta, después de verificar por WhatsApp.
 *
 * @property-read array{name: string|null, names: array<int, string>, guests: list<int>, appointments: Collection<int, Appointment>, more: int, sessions: Collection<int, array{patient: Patient, done: int, total: int, doctor: string|null, requested: bool}>, checkups: Collection<int, array{patient: Patient, months: int, url: string}>} $resource
 */
class WelcomeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $welcome = $this->resource;
        $names = $welcome['names'];
        $isSelf = fn (Patient $patient): bool => $patient->relationship === PatientRelationship::Self;
        $askedHealth = [];

        return [
            'name' => $welcome['name'],
            'appointments' => $welcome['appointments']->map(function (Appointment $appointment) use ($welcome, $names, $isSelf, &$askedHealth) {
                $guest = in_array((int) $appointment->patient_id, $welcome['guests'], true);
                // "Antes de la cita" sale una vez por persona, en su primera cita.
                $askHealth = $appointment->patient->medicalBackground === null && ! in_array($appointment->patient_id, $askedHealth, true);
                $askedHealth[] = $appointment->patient_id;

                return AppointmentCardResource::make($appointment)->shownAs(
                    name: $isSelf($appointment->patient) && ! $guest ? null : $names[$appointment->patient_id],
                    guest: $guest,
                    askHealth: $askHealth,
                );
            })->values(),
            'more' => $welcome['more'],
            'sessions' => $welcome['sessions']->map(function (array $row) use ($names, $isSelf) {
                $patient = $row['patient'];
                $self = $isSelf($patient);
                $left = $row['total'] - $row['done'];
                $missing = $left === 1 ? 'falta' : 'faltan';

                return [
                    'patient_id' => $patient->id,
                    'kicker' => $self ? 'Tu tratamiento' : 'Tratamiento de '.$names[$patient->id],
                    'title' => ($self ? 'Te '.$missing : 'A '.$names[$patient->id].' le '.$missing).' '.$left.' de '.$row['total'].' '.($row['total'] === 1 ? 'sesión' : 'sesiones'),
                    'done' => $row['done'],
                    'total' => $row['total'],
                    'note' => ($row['doctor'] ? 'Con '.$row['doctor'].'. ' : '').'Cada sesión la programa '.($self ? 'tu' : 'su').' doctor: pídela y te escribimos con el horario.',
                    'requested' => $row['requested'],
                    'button' => $self ? 'Pedir mi próxima sesión' : 'Pedir su próxima sesión',
                ];
            })->values(),
            'checkups' => $welcome['checkups']->map(function (array $row) use ($names, $isSelf) {
                $patient = $row['patient'];
                $self = $isSelf($patient);

                return [
                    'patient_id' => $patient->id,
                    'kicker' => $self ? 'Tu control' : 'Control de '.$names[$patient->id],
                    'title' => $row['months'].' '.($row['months'] === 1 ? 'mes' : 'meses').' desde '.($self ? 'tu' : 'su').' última visita',
                    'note' => 'Ya '.($self ? 'te' : 'le').' toca una revisión: así lo pequeño se trata antes de que duela.',
                    'url' => $row['url'],
                    'button' => $self ? 'Reservar mi control' : 'Reservar su control',
                ];
            })->values(),
        ];
    }
}
