<?php

namespace App\Filament\Resources\Doctors\Pages;

use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Resources\Doctors\Schemas\DoctorForm;
use App\Models\Doctor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDoctor extends CreateRecord
{
    protected static string $resource = DoctorResource::class;

    protected static ?string $title = 'Nuevo doctor';

    protected static ?string $breadcrumb = 'Nuevo';

    protected static bool $canCreateAnother = false;

    public static bool $formActionsAreSticky = true;

    /** "Guardar", como en todas las ventanas de alta: aquí decía "Crear". */
    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Guardar');
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return DoctorForm::withAccess($data, $this->form->getRawState());
    }

    /** Recién creado casi siempre le falta algo para atender: se dice qué, en vez de un "Creado" a secas. */
    protected function getCreatedNotification(): ?Notification
    {
        /** @var Doctor $doctor */
        $doctor = $this->getRecord();

        return Notification::make()
            ->success()
            ->title('Doctor guardado')
            ->body(DoctorForm::notBookableYet($doctor)
                ?? 'Cuando su ficha esté completa, elige en "Dónde aparece" si sale en la página web.');
    }

    /** Al crear, se sigue en la ficha para agregar sus tratamientos. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
