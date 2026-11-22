<?php

namespace App\Filament\Resources\Patients\Tables;

use App\Enums\AppointmentStatus;
use App\Enums\MessageTemplateKey;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Charge;
use App\Models\Patient;
use App\Support\Money;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Todos los pacientes, por apellido, con su celular, edad, próxima cita, última visita y saldo. En "Les toca control"
 * y "Faltaron o cancelaron", en vez de la próxima cita (no tienen) va qué le toca o qué pasó, y si ya se le escribió.
 */
class PatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withMin(
                    ['appointments as next_appointment_at' => fn (Builder $a) => $a->where('starts_at', '>=', now())->whereIn('status', AppointmentStatus::changeable())],
                    'starts_at',
                )
                ->when(self::seesBalances(), fn (Builder $q) => $q->withBalance()))
            ->columns(self::columns())
            ->defaultSort('last_name')
            // En el celular "Ordenar por" lo dice, en vez de un guion.
            ->defaultSortOptionLabel('Apellido (A a Z)')
            ->searchPlaceholder('Buscar paciente, DNI o celular')
            // La búsqueda del paciente separa las palabras ella misma; si la tabla las separa antes, "987 654 321"
            // (como se ve en la columna) llega en pedazos de 3 números y no encuentra el celular.
            ->splitSearchTerms(false)
            ->filters([
                Filter::make('new')
                    ->label('Aún no vienen')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereNull('first_visit_at')),
                TrashedFilter::make()
                    ->label('Pacientes borrados')
                    ->placeholder('No mostrar')
                    ->trueLabel('Mostrar también')
                    ->falseLabel('Solo los borrados')
                    ->visible(fn () => auth()->user()->isAdmin()),
            ])
            ->filtersTriggerAction(fn (Action $action) => $action->button()->label('Filtros'))
            ->recordActions(PatientRowActions::all())
            ->recordActionsColumnLabel('Acciones')
            ->emptyStateHeading(fn ($livewire) => self::emptyState($livewire)[0])
            ->emptyStateDescription(fn ($livewire) => self::emptyState($livewire)[1])
            ->emptyStateActions([
                Action::make('createFromSearch')
                    ->label('Nuevo paciente')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(PatientResource::getUrl('create'))
                    ->visible(fn ($livewire) => self::isSearching($livewire) && PatientResource::canCreate()),
            ]);
    }

    /**
     * En "Les toca control" y "Faltaron o cancelaron", en vez de la próxima cita (no tienen) va qué le toca o qué
     * pasó, y si ya se le escribió.
     *
     * @return list<TextColumn>
     */
    private static function columns(): array
    {
        return [
            TextColumn::make('last_name')
                ->label('Paciente')
                ->formatStateUsing(fn (Patient $record) => $record->full_name)
                ->description(fn (Patient $record) => $record->document_number ? $record->document_type?->getLabel().' '.$record->document_number : null)
                ->searchable(query: fn (Builder $query, string $search) => $query->search($search))
                ->sortable(['last_name', 'first_name']),
            TextColumn::make('account.phone')
                ->label('Celular')
                ->formatStateUsing(fn (string $state) => Phone::display($state))
                ->description(fn (Patient $record) => $record->relationship?->getLabel())
                ->placeholder('Sin celular'),
            TextColumn::make('birth_date')
                ->label('Edad')
                ->formatStateUsing(fn (Patient $record) => $record->age !== null ? $record->age.' años' : null)
                ->placeholder('—'),
            TextColumn::make('next_appointment_at')
                ->label('Próxima cita')
                ->dateTime('D d/m H:i')
                ->placeholder('—')
                ->sortable()
                ->hidden(fn ($livewire) => PatientRowActions::followUpTemplate($livewire) !== null),
            TextColumn::make('follow_up')
                ->label(fn ($livewire) => PatientRowActions::followUpTemplate($livewire) === MessageTemplateKey::MissedVisit ? 'Qué pasó' : 'Le toca')
                ->state(fn (Patient $record, $livewire) => ($template = PatientRowActions::followUpTemplate($livewire)) ? PatientRowActions::followUpDetail($record, $template) : null)
                ->description(fn (Patient $record, $livewire) => ($template = PatientRowActions::followUpTemplate($livewire)) ? PatientRowActions::contactedLabel($record, $template) : null)
                ->wrap()
                ->visible(fn ($livewire) => PatientRowActions::followUpTemplate($livewire) !== null),
            TextColumn::make('last_visit_at')
                ->label('Última visita')
                ->date('d/m/Y')
                ->placeholder('Aún no viene')
                ->sortable(),
            TextColumn::make('balance_due')
                ->label('Saldo')
                ->formatStateUsing(fn ($state) => (float) $state == 0 ? '—' : Money::balance($state))
                ->color(fn ($state) => (float) $state > 0 ? 'danger' : null)
                ->weight(fn ($state) => (float) $state > 0 ? 'bold' : null)
                ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('balance_due', $direction))
                // Tocar "Debe S/ …" abre Cobrar, como en la ficha.
                ->action(PatientRowActions::collect('collectBalance'))
                ->disabledClick(fn (Patient $record) => ! PatientRowActions::canCollect($record))
                ->tooltip(fn (Patient $record) => PatientRowActions::canCollect($record) ? 'Cobrar' : null)
                ->alignEnd()
                ->visible(fn () => self::seesBalances()),
        ];
    }

    /**
     * Sin resultados no siempre es "no hay pacientes": puede ser que la búsqueda no encontró a nadie o que nadie
     * está en esa pestaña.
     *
     * @return array{0: string, 1: string}
     */
    private static function emptyState(mixed $livewire): array
    {
        return match (true) {
            self::isSearching($livewire) => ['No encontramos a nadie con eso', 'Revisa cómo se escribió o prueba con el DNI o el celular. Si es nuevo, regístralo.'],
            PatientRowActions::followUpTemplate($livewire) === MessageTemplateKey::CheckupRecall => ['A nadie le toca control', 'Aquí aparece quien cumple el tiempo de su control y no tiene cita.'],
            PatientRowActions::followUpTemplate($livewire) === MessageTemplateKey::MissedVisit => ['Nadie por recuperar', 'Aquí aparece quien faltó o canceló en los últimos '.Patient::MISSED_VISIT_DAYS.' días y no volvió a agendar.'],
            default => ['Aún no hay pacientes', 'Se crean solos al reservar en la web, o desde "Nuevo paciente".'],
        };
    }

    /** Hay algo escrito en el buscador o un filtro puesto. */
    private static function isSearching(mixed $livewire): bool
    {
        if (! $livewire instanceof HasTable) {
            return false;
        }

        return filled(data_get($livewire, 'tableSearch')) || collect(data_get($livewire, 'tableFilters') ?? [])->flatten()->filter()->isNotEmpty();
    }

    private static function seesBalances(): bool
    {
        return auth()->user()->can('viewAny', Charge::class);
    }
}
