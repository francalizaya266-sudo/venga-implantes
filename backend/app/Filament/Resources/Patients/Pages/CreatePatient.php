<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Services\Patients\PatientRegistrar;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class CreatePatient extends CreateRecord
{
    protected static string $resource = PatientResource::class;

    protected static ?string $title = 'Nuevo paciente';

    protected static ?string $breadcrumb = 'Nuevo';

    protected static bool $canCreateAnother = false;

    /** Si al crearlo se pasa a agendarle la cita ("Crear y agendar cita"). Solo dura esa petición. */
    private bool $bookAfterCreating = false;

    /** El celular agrupa a la familia: si ya existe, el paciente se suma a esa cuenta. */
    protected function handleRecordCreation(array $data): Model
    {
        return app(PatientRegistrar::class)->register(array_filter($data, fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * Casi siempre se registra a alguien que está en el mostrador o al teléfono esperando su horario: "Crear y agendar
     * cita" va primero, en azul, y deja abierta la cita nueva con el paciente puesto. "Volver", como en el resto del
     * panel. Sin permiso para agendar, "Crear" es el principal.
     */
    protected function getFormActions(): array
    {
        $canBook = AppointmentResource::canCreate();

        return [
            Action::make('createAndBook')
                ->label('Crear y agendar cita')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->visible($canBook)
                ->action(fn () => $this->createAndBook()),
            $this->getCreateFormAction()
                ->label('Crear')
                ->color($canBook ? 'gray' : 'primary'),
            $this->getCancelFormAction()
                ->label('Volver'),
        ];
    }

    public function createAndBook(): void
    {
        $this->bookAfterCreating = true;

        try {
            $this->create();
        } finally {
            $this->bookAfterCreating = false;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->bookAfterCreating
            ? AppointmentResource::newUrl(['patient' => $this->getRecord()->getKey()])
            : $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
