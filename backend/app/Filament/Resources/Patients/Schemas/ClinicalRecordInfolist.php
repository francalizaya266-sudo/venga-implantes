<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Enums\Sex;
use App\Filament\Actions\EditMedicalBackgroundAction;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Support\ClinicalAlerts;
use App\Filament\Support\PageLayout;
use App\Livewire\OdontogramChart;
use App\Models\MedicalBackground;
use App\Models\Patient;
use App\Models\StockMovement;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * La historia clínica en dos columnas: a la izquierda los antecedentes (y los implantes colocados), a la derecha
 * el odontograma, ancho. Arriba, solo si hay algo que cuidar, la alerta médica. Debajo, en pestañas, las
 * atenciones, los presupuestos, las radiografías y los consentimientos. En el celular, una debajo de la otra.
 */
class ClinicalRecordInfolist
{
    /** Antecedentes de más de un año: se piden de nuevo, algo pudo cambiar (un medicamento, un embarazo). */
    public const STALE_AFTER_MONTHS = 12;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(PageLayout::PROFILE_COLUMNS)
            ->components([
                // Un solo aviso: arriba va solo lo que hay que cuidar; que falten, lo dice su recuadro.
                Group::make(fn (Patient $record) => array_filter([ClinicalAlerts::callout($record, withLink: false)]))
                    ->visible(fn (Patient $record) => ClinicalAlerts::for($record) !== [])
                    ->columnSpanFull(),
                Group::make([self::background(), self::plan(), self::implants()])
                    ->columnSpan(PageLayout::NARROW),
                Section::make('Odontograma')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->description('El más reciente. Elige una fecha para ver cómo estaba antes.')
                    ->collapsible()
                    ->schema([
                        Livewire::make(OdontogramChart::class)->key('odontogram'),
                    ])
                    ->columnSpan(PageLayout::WIDE),
            ]);
    }

    /**
     * Alergias, enfermedades, medicamentos y embarazo. Si nunca se preguntó, no muestra respuestas que nadie dio
     * ("No", "Ninguna"): solo los dos botones, registrarlos o dejar dicho que no tiene nada relevante.
     */
    private static function background(): Section
    {
        return Section::make('Antecedentes médicos')
            ->key('background')
            ->icon(Heroicon::OutlinedHeart)
            ->iconColor(fn (Patient $record) => self::needsAsking($record) ? 'warning' : null)
            ->description(fn (Patient $record) => self::backgroundStatus($record))
            ->headerActions([
                EditMedicalBackgroundAction::make(fn (Patient $record) => $record)
                    ->link()
                    ->visible(fn (Patient $record) => $record->medicalBackground !== null),
            ])
            ->columns(2)
            ->schema([
                Actions::make([
                    EditMedicalBackgroundAction::make(fn (Patient $record) => $record, 'registerBackground'),
                    EditMedicalBackgroundAction::nothingRelevant(fn (Patient $record) => $record),
                ])
                    ->key('backgroundActions')
                    ->fullWidth()
                    ->extraAttributes(['class' => 'pt-background-actions'])
                    ->columnSpanFull()
                    ->visible(fn (Patient $record) => $record->medicalBackground === null),
                Group::make([
                    TextEntry::make('medicalBackground.allergies')->label('Alergias')->placeholder('—'),
                    TextEntry::make('medicalBackground.medications')->label('Medicamentos')->placeholder('—'),
                    TextEntry::make('medicalBackground.conditions')
                        ->label('Enfermedades')
                        ->state(fn (Patient $record) => collect($record->medicalBackground?->conditions ?? [])->map(fn (string $key) => MedicalBackground::CONDITIONS[$key] ?? $key)->all())
                        ->badge()
                        ->color('danger')
                        ->placeholder('Ninguna')
                        ->columnSpanFull(),
                    TextEntry::make('pregnancy')
                        ->label('Embarazo / lactancia')
                        ->state(fn (Patient $record) => collect([
                            $record->medicalBackground?->is_pregnant ? 'Embarazada'.($record->medicalBackground->pregnancy_weeks ? " ({$record->medicalBackground->pregnancy_weeks} sem.)" : '') : null,
                            $record->medicalBackground?->is_breastfeeding ? 'Dando de lactar' : null,
                        ])->filter()->implode(' · ') ?: null)
                        ->placeholder('No')
                        ->visible(fn (Patient $record) => $record->sex !== Sex::Male),
                    TextEntry::make('medicalBackground.smoker')
                        ->label('Fuma')
                        ->formatStateUsing(fn (?bool $state) => $state ? 'Sí' : 'No')
                        ->placeholder('No registrado'),
                    TextEntry::make('medicalBackground.surgeries')->label('Operaciones')->placeholder('—'),
                    TextEntry::make('medicalBackground.notes')->label('Otras notas')->placeholder('—'),
                ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (Patient $record) => $record->medicalBackground !== null),
            ]);
    }

    /**
     * Su plan vigente, a la vista sin bajar a la pestaña Presupuestos: cada procedimiento con su precio, en qué va
     * y cuántos lleva hechos. Sin plan vigente no aparece.
     */
    private static function plan(): Section
    {
        return Section::make('Plan de tratamiento')
            ->key('plan')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->description('Cómo va: lo hecho y lo que falta.')
            ->schema([
                View::make('filament.consultations.plans')
                    ->viewData(fn (Patient $record) => [
                        'plans' => self::openPlans($record),
                        'payments' => [],
                        'paymentOptions' => [],
                        'url' => PatientResource::getUrl('clinical', ['record' => $record, 'relation' => '1']),
                        'linkLabel' => 'Ver presupuestos',
                    ]),
            ])
            ->visible(fn (Patient $record) => self::openPlans($record)->isNotEmpty());
    }

    /** @return Collection<int, TreatmentPlan> Los planes vigentes del paciente, del más antiguo al más nuevo. */
    private static function openPlans(Patient $patient): Collection
    {
        return TreatmentPlan::query()->whereBelongsTo($patient)->open()->with('items')->orderBy('id')->get();
    }

    /** Los implantes que se le pusieron, con su serie y lote, por si el fabricante pide revisar alguno. */
    private static function implants(): Section
    {
        return Section::make('Implantes colocados')
            ->icon(Heroicon::OutlinedQrCode)
            ->description('Con su serie y lote, por si el fabricante pide revisar alguno.')
            ->collapsible()
            ->visible(fn (Patient $record) => app(InventoryService::class)->implantsOf($record)->isNotEmpty())
            ->schema([
                TextEntry::make('implants')
                    ->label('Implantes colocados')
                    ->hiddenLabel()
                    ->state(fn (Patient $record) => app(InventoryService::class)->implantsOf($record)
                        ->map(fn (StockMovement $use) => collect([
                            ($use->consultation?->attended_at ?? $use->appointment?->starts_at ?? $use->created_at)->format('d/m/Y'),
                            $use->supply->name,
                            $use->batch?->label() ?? 'serie sin registrar',
                            $use->consultation?->doctor?->name ?? $use->appointment?->doctor?->name,
                        ])->filter()->implode(' · '))
                        ->all())
                    ->listWithLineBreaks()
                    ->bulleted(),
            ]);
    }

    /**
     * "Actualizado hace 3 meses por Dra. Cornejo"; si pasó más de un año, que se pregunte de nuevo. Si los llenó
     * el paciente desde la web, que se confirmen.
     */
    private static function backgroundStatus(Patient $patient): string
    {
        /** @var MedicalBackground|null $background */
        $background = $patient->medicalBackground;

        if ($background === null) {
            return 'Sin registrar. Pregunta por alergias, enfermedades y medicamentos antes de empezar.';
        }

        /** @var User|null $updatedBy */
        $updatedBy = $background->updatedBy;
        $updated = 'Actualizado '.($background->updated_at->isToday() ? 'hoy' : $background->updated_at->diffForHumans()).($updatedBy ? ' por '.$updatedBy->name : '');

        return ClinicalAlerts::filledByPatient($patient)
            ?? ($background->updated_at->lt(now()->subMonths(self::STALE_AFTER_MONTHS)) ? $updated.'. Pregúntale si algo cambió.' : $updated.'.');
    }

    /** Sin registrar, de más de un año o llenados por el paciente sin confirmar: el ícono en ámbar. */
    private static function needsAsking(Patient $patient): bool
    {
        /** @var MedicalBackground|null $background */
        $background = $patient->medicalBackground;

        return $background === null
            || $background->filled_by_patient_at !== null
            || $background->updated_at->lt(now()->subMonths(self::STALE_AFTER_MONTHS));
    }
}
