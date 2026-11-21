<?php

namespace App\Filament\Support;

use App\Enums\PatientRelationship;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Doctor;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Services\Clinical\ConsentService;
use App\Services\Patients\PatientInsights;
use App\Support\Money;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * Lo que conviene resolver con el paciente cuando llega, mientras espera: los datos que le faltan, sus
 * antecedentes sin llenar, lo que debe de antes (y si una cuota se le pasó), el presupuesto que no respondió y si su
 * trabajo todavía no llegó del laboratorio. Sale en "Antes de pasar" y en el aviso de "Paciente en sala", con el
 * botón para completar su ficha.
 */
final class ArrivalChecklist
{
    /**
     * @param  bool  $originAskedAlready  La ventana ya pregunta "¿Cómo nos encontró?": no se repite en la lista.
     * @return list<string> Una frase por cosa pendiente; vacío si no falta nada.
     */
    public static function for(Patient $patient, User $user, bool $originAskedAlready = false): array
    {
        $missing = array_keys(array_filter([
            'DNI' => blank($patient->document_number),
            'fecha de nacimiento' => $patient->birth_date === null,
            'celular' => $patient->account === null,
            'apoderado' => $patient->isMinor() && app(ConsentService::class)->defaultSigner($patient)['signer_relationship'] === PatientRelationship::Self,
            'cómo nos encontró' => $patient->origin === null && ! $originAskedAlready,
        ]));

        $insights = app(PatientInsights::class);
        $balance = $user->can('viewAny', Charge::class) ? $patient->balance() : 0.0;
        $late = $balance > 0 ? $insights->lateInstallments($patient) : null;
        $inLab = LabOrder::query()->where('patient_id', $patient->getKey())->pending()->orderBy('due_on')->first();
        /** @var CarbonInterface|null $presentedAt */
        $presentedAt = $insights->planAwaitingAnswer($patient)?->proposed_at;

        return array_values(array_filter([
            $missing !== [] ? 'Le falta: '.Arr::join($missing, ', ', ' y ').'.' : null,
            $patient->medicalBackground === null ? 'No ha llenado sus antecedentes médicos: puede hacerlo en su celular, desde Mi cuenta.' : null,
            $balance > 0 ? 'Debe '.Money::format($balance).' de antes'.($late ? ' (cuota vencida desde el '.$late['since']->translatedFormat('j \d\e F').')' : '').'.' : null,
            // Ahora que está en el mostrador es cuando se le pregunta.
            $presentedAt ? 'Tiene un presupuesto sin respuesta desde el '.$presentedAt->translatedFormat('j \d\e F').': pregúntale si lo acepta.' : null,
            $inLab ? '"'.$inLab->description.'" todavía no llega del laboratorio ('.($inLab->due_on->isPast() && ! $inLab->due_on->isToday() ? 'debía llegar' : 'llega').' el '.$inLab->due_on->translatedFormat('D j M').').' : null,
        ]));
    }

    /** "Rosa Quispe llegó antes: su cita pasó de las 11:00 a las 10:15 con Dra. Ana Torres." */
    public static function advancedHeadline(Appointment $appointment, CarbonInterface $bookedAt): string
    {
        /** @var Patient $patient */
        $patient = $appointment->patient;
        /** @var Doctor $doctor */
        $doctor = $appointment->doctor;
        /** @var CarbonInterface $startsAt */
        $startsAt = $appointment->starts_at;

        return $patient->full_name.' llegó antes: su cita pasó de las '.$bookedAt->format('H:i').' a las '.$startsAt->format('H:i').' con '.$doctor->name.'.';
    }

    /**
     * Agrega al aviso de llegada lo pendiente del paciente; si hay algo, el aviso se queda hasta que lo cierren.
     *
     * @param  string|null  $headline  Primera línea del aviso; si no se da, el nombre del paciente.
     */
    public static function addTo(Notification $notification, Appointment $appointment, User $user, ?string $headline = null): Notification
    {
        $patient = Patient::query()->with(['account', 'medicalBackground'])->find($appointment->patient_id);
        $pending = $patient ? self::for($patient, $user) : [];

        if ($pending === []) {
            return $headline ? $notification->body($headline) : $notification;
        }

        return $notification
            ->body(implode('<br>', [$headline ?? $patient->full_name, ...$pending]))
            ->actions(array_values(array_filter([
                Action::make('patient')
                    ->label($user->can('update', $patient) ? 'Completar su ficha' : 'Ver su ficha')
                    ->icon(Heroicon::OutlinedUser)
                    ->url(PatientResource::getUrl($user->can('update', $patient) ? 'edit' : 'view', ['record' => $patient]))
                    ->button(),
                // Si debe de antes, se le cobra mientras espera: abre su ficha con "Cobrar" y lo que debe ya puesto.
                $user->can('create', Payment::class) && $user->can('viewAny', Charge::class) && $patient->balance() > 0
                    ? Action::make('collect')
                        ->label('Cobrar')
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->color('success')
                        ->url(PatientResource::getUrl('view', ['record' => $patient, 'action' => 'registerPayment']))
                        ->button()
                    : null,
            ])))
            ->persistent();
    }
}
