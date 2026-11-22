<?php

namespace App\Filament\Resources\Patients\Tables;

use App\Enums\AppointmentStatus;
use App\Enums\MessageTemplateKey;
use App\Filament\Actions\RegisterPaymentAction;
use App\Filament\Actions\SendWhatsAppAction;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Support\PatientSummary;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Treatment;
use App\Services\Dashboard\FollowUps;
use App\Services\Patients\PatientInsights;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;

/**
 * Lo que se hace con un paciente desde su fila: verlo, editarlo y, en el menú ⋮, agendarle, escribirle o cobrarle
 * sin abrir su ficha. En "Les toca control" y "Faltaron o cancelaron" va primero el paso que toca: recordarle por
 * WhatsApp con el mismo aviso de Hoy o, si ya se le escribió, agendarle.
 */
final class PatientRowActions
{
    /** Las pestañas para recuperar pacientes y el aviso con el que se les escribe (el mismo de Hoy). */
    public const FOLLOW_UP_TEMPLATES = [
        'checkup' => MessageTemplateKey::CheckupRecall,
        'missed' => MessageTemplateKey::MissedVisit,
    ];

    /** @return list<Action|ActionGroup> */
    public static function all(): array
    {
        return [
            self::remind(),
            self::schedule(),
            ViewAction::make(),
            EditAction::make(),
            ActionGroup::make([
                Action::make('newAppointment')
                    ->label('Nueva cita')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->url(fn (Patient $record) => AppointmentResource::newUrl(['patient' => $record->getKey()])),
                Action::make('whatsapp')
                    ->label('Escribir por WhatsApp')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->color('success')
                    ->visible(fn (Patient $record) => PatientSummary::contact($record) !== null)
                    ->url(fn (Patient $record) => PatientSummary::contact($record)['whatsapp'] ?? null, shouldOpenInNewTab: true),
                self::collect('registerPayment'),
            ])->tooltip('Más'),
        ];
    }

    /** Cobrar lo que debe, desde el menú o tocando su saldo en rojo. Solo si debe y quien mira puede cobrar. */
    public static function collect(string $name): Action
    {
        return RegisterPaymentAction::make(fn (Patient $record) => $record)
            ->name($name)
            ->visible(fn (?Patient $record) => $record !== null && self::canCollect($record));
    }

    public static function canCollect(Patient $patient): bool
    {
        // "balance_due" lo trae la consulta de la lista (Patient::withBalance).
        return (float) $patient->getAttribute('balance_due') > 0 && auth()->user()->can('create', Payment::class);
    }

    /**
     * El aviso con el que se le escribe desde la pestaña en la que se está, o null fuera de las pestañas para recuperar.
     */
    public static function followUpTemplate(mixed $livewire): ?MessageTemplateKey
    {
        $tab = is_object($livewire) && property_exists($livewire, 'activeTab') ? $livewire->activeTab : null;

        return self::FOLLOW_UP_TEMPLATES[$tab] ?? null;
    }

    /** Qué le pasó o qué le toca: "Faltó el 5 nov · Limpieza dental", "Control y limpieza · vino hace 6 meses". */
    public static function followUpDetail(Patient $patient, MessageTemplateKey $template): ?string
    {
        $insights = app(PatientInsights::class);

        if ($template === MessageTemplateKey::MissedVisit) {
            $missed = $insights->missedVisit($patient);

            if ($missed === null) {
                return null;
            }

            /** @var AppointmentStatus $status */
            $status = $missed->status;
            /** @var CarbonInterface $startsAt */
            $startsAt = $missed->starts_at;
            /** @var Treatment $treatment */
            $treatment = $missed->treatment;

            return ($status === AppointmentStatus::NoShow ? 'Faltó el ' : 'Se canceló su cita del ').$startsAt->translatedFormat('j M').' · '.$treatment->name;
        }

        $checkup = $insights->checkup($patient);
        /** @var CarbonInterface|null $lastVisit */
        $lastVisit = $patient->last_visit_at;

        return $checkup && $lastVisit
            ? ucfirst($checkup['kind']->checkupLabel()).' · vino '.$lastVisit->diffForHumans()
            : null;
    }

    /** "Le escribiste hace 3 días": cuándo se le mandó este aviso, si fue hace poco (los mismos días que cuenta Hoy). */
    public static function contactedLabel(Patient $patient, MessageTemplateKey $template): ?string
    {
        $sentAt = self::contactedAt($patient, $template);

        return $sentAt ? 'Le escribiste '.($sentAt->isToday() ? 'hoy' : $sentAt->diffForHumans()) : null;
    }

    private static function contactedAt(Patient $patient, MessageTemplateKey $template): ?CarbonImmutable
    {
        return once(fn () => app(FollowUps::class)->recentlyContacted([$patient->getKey()], $template)[$patient->getKey()] ?? null);
    }

    /** "Recordar": el aviso de control o de la cita perdida, ya escrito. Va primero mientras no se le haya escrito. */
    private static function remind(): Action
    {
        return SendWhatsAppAction::compose('remind', function (Patient $record, mixed $livewire) {
            $template = self::followUpTemplate($livewire);

            return $template ? app(FollowUps::class)->draft($template, $record) : null;
        })
            ->label('Recordar')
            ->link()
            ->extraAttributes(['class' => 'appt-main-step'], merge: true)
            ->visible(function (?Patient $record, mixed $livewire) {
                $template = self::followUpTemplate($livewire);

                return $record !== null && $template !== null && $record->account !== null && self::contactedAt($record, $template) === null;
            });
    }

    /** "Agendar" lo mismo que perdió, o su control: el paso cuando ya se le escribió o no tiene celular. */
    private static function schedule(): Action
    {
        return Action::make('schedule')
            ->label('Agendar')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->extraAttributes(['class' => 'appt-main-step'], merge: true)
            ->url(function (Patient $record, mixed $livewire) {
                $missed = self::followUpTemplate($livewire) === MessageTemplateKey::MissedVisit ? app(PatientInsights::class)->missedVisit($record) : null;

                return AppointmentResource::newUrl(array_filter([
                    'patient' => $record->getKey(),
                    'treatment' => $missed?->treatment_id,
                    'doctor' => $missed?->doctor_id,
                ]));
            })
            ->visible(function (?Patient $record, mixed $livewire) {
                $template = self::followUpTemplate($livewire);

                return $record !== null && $template !== null && ($record->account === null || self::contactedAt($record, $template) !== null);
            });
    }
}
