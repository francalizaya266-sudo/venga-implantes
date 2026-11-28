<?php

namespace App\Filament\Resources\Treatments\Pages;

use App\Filament\Resources\Treatments\TreatmentResource;
use App\Models\Treatment;
use App\Services\Catalog\TreatmentCatalog;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageTreatments extends ManageRecords
{
    protected static string $resource = TreatmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->suggestedAction(),
            CreateAction::make()
                ->label('Nuevo tratamiento')
                ->mutateDataUsing(fn (array $data) => TreatmentResource::withPresence($data))
                ->successNotificationTitle('Tratamiento guardado')
                // "Guardar" y "Cancelar", como en las demás ventanas de Configuración: aquí además salía "Crear y crear otro".
                ->createAnother(false),
        ];
    }

    /**
     * Para no escribir uno por uno los tratamientos que casi todo consultorio ofrece: se marcan en una lista y
     * llegan con su duración, sus indicaciones y sus cuidados. Solo ofrece los que el consultorio todavía no tiene.
     */
    private function suggestedAction(): Action
    {
        return Action::make('suggested')
            ->label('Agregar tratamientos sugeridos')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->visible(fn (TreatmentCatalog $catalog) => auth()->user()->can('create', Treatment::class) && $catalog->missing() !== [])
            ->modalHeading('Agregar tratamientos sugeridos')
            ->modalDescription('Marca los que ofreces. Llegan con su duración, lo que el paciente debe saber antes de venir y sus cuidados. El precio lo pones tú después.')
            ->modalSubmitActionLabel('Agregar los marcados')
            ->schema(fn (TreatmentCatalog $catalog) => [
                CheckboxList::make('treatments')
                    ->hiddenLabel()
                    ->options(collect($catalog->missing())->map(fn (array $definition) => $definition['name'])->all())
                    ->descriptions(collect($catalog->missing())->map(fn (array $definition) => "{$definition['minutes']} min. {$definition['summary']}")->all())
                    ->columns(2)
                    ->required()
                    ->validationMessages(['required' => 'Marca al menos un tratamiento.']),
            ])
            ->action(function (array $data, TreatmentCatalog $catalog): void {
                $added = $catalog->add($data['treatments'])->count();

                Notification::make()
                    ->success()
                    ->title($added === 1 ? 'Se agregó 1 tratamiento.' : "Se agregaron {$added} tratamientos.")
                    ->body('Por ahora solo se ven en el consultorio y no tienen precio. Abre cada uno para ponerle su precio y elegir si aparece en la web.')
                    ->persistent()
                    ->send();
            });
    }
}
