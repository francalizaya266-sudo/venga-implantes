<?php

namespace App\Filament\Resources\Consultations\Pages;

use App\Exceptions\ClinicalException;
use App\Filament\Actions\RegisterPaymentAction;
use App\Filament\Actions\SendToLabAction;
use App\Filament\Actions\SendWhatsAppAction;
use App\Filament\Actions\SignPlanAction;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Consultations\ConsultationResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Support\VisitCloseOut;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Services\Clinical\ConsultationService;
use App\Services\Clinical\PrescriptionService;
use App\Services\Clinical\TreatmentPlanService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewConsultation extends ViewRecord
{
    protected static string $resource = ConsultationResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Atención de '.$this->getConsultation()->patient->full_name;
    }

    public function getBreadcrumb(): string
    {
        return $this->getConsultation()->attended_at->format('d/m/Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('continue')
                ->label('Continuar')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->visible(fn () => auth()->user()->can('update', $this->getConsultation()))
                ->url(fn () => ConsultationResource::getUrl('edit', ['record' => $this->getConsultation()])),
            Action::make('reopen')
                ->label('Reabrir para corregir')
                ->icon(Heroicon::OutlinedLockOpen)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('¿Reabrir la atención para corregirla?')
                ->modalDescription('Podrás corregir la atención. Cada cambio queda registrado con fecha y usuario.')
                ->modalSubmitActionLabel('Reabrir')
                ->authorize('reopen')
                ->action(function () {
                    try {
                        app(ConsultationService::class)->reopen($this->getConsultation(), auth()->user());
                    } catch (ClinicalException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();

                        return;
                    }

                    $this->redirect(ConsultationResource::getUrl('edit', ['record' => $this->getConsultation()]));
                }),
            // Una atención sin cita también se cobra desde aquí.
            RegisterPaymentAction::make(fn () => $this->getConsultation()->patient)
                ->name('collect')
                ->label('Cobrar')
                ->hidden(fn () => $this->getConsultation()->closed_at === null),
            Action::make('printPrescription')
                ->label('Imprimir receta')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->visible(fn () => $this->getConsultation()->prescription !== null)
                ->url(fn () => ConsultationResource::prescriptionPrintUrl($this->getConsultation()->prescription))
                ->openUrlInNewTab(),
            SendWhatsAppAction::compose('sendPrescription', fn () => app(PrescriptionService::class)->whatsappDraft($this->getConsultation()->prescription))
                ->label('Enviar receta por WhatsApp')
                ->visible(fn () => $this->getConsultation()->prescription !== null && $this->getConsultation()->patient->account_id !== null),
            Action::make('book')
                ->label('Programar cita')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->visible(fn () => auth()->user()->can('create', Appointment::class))
                ->url(fn () => AppointmentResource::nextVisitUrl($this->getConsultation()->patient, $this->getConsultation()->doctor_id)),
            SendToLabAction::make(fn () => $this->getConsultation()->patient, fn () => $this->getConsultation()->doctor_id),
            Action::make('clinicalRecord')
                ->label('Historia clínica')
                ->icon(Heroicon::OutlinedFolderOpen)
                ->color('gray')
                ->url(fn () => PatientResource::getUrl('clinical', ['record' => $this->getConsultation()->patient])),
        ];
    }

    /** Le pasa el cobro a recepción: el doctor, que no maneja la caja, o la administradora que prefiere no cobrar ella. */
    public function notifyReceptionAction(): Action
    {
        return Action::make('notifyReception')
            ->label('Pasar a recepción')
            ->visible(fn () => $this->getConsultation()->closed_at !== null)
            ->action(function () {
                VisitCloseOut::handToReception($this->getConsultation(), auth()->user());

                Notification::make()->success()->title('Listo: recepción ya sabe que tiene que cobrarle')->send();
            });
    }

    /** El paciente acepta y firma el presupuesto (o lo agregado a su plan) ahí mismo, desde la tarjeta de cierre. */
    public function acceptPlanAction(): Action
    {
        return SignPlanAction::make()
            ->name('acceptPlan')
            ->record(fn () => VisitCloseOut::planToAccept($this->getConsultation()))
            ->hidden(fn () => VisitCloseOut::planToAccept($this->getConsultation()) === null);
    }

    /** Se lleva el presupuesto por WhatsApp: con eso queda como presentado. */
    public function sendPlanAction(): Action
    {
        return SendWhatsAppAction::compose('sendPlan', fn () => app(TreatmentPlanService::class)->whatsappDraft(VisitCloseOut::planToAccept($this->getConsultation())))
            ->hidden(fn () => ! ($plan = VisitCloseOut::planToAccept($this->getConsultation())) || $plan->patient->account === null || ! auth()->user()->can('update', $plan))
            ->after(fn () => ($plan = VisitCloseOut::planToAccept($this->getConsultation())) ? app(TreatmentPlanService::class)->markProposed($plan) : null);
    }

    private function getConsultation(): Consultation
    {
        /** @var Consultation */
        return $this->getRecord();
    }
}
