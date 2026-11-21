<?php

namespace App\Filament\Resources\WaitlistEntries;

use App\Enums\AppointmentStatus;
use App\Filament\Concerns\HasSpanishLabels;
use App\Filament\Pages\Agenda;
use App\Filament\Resources\WaitlistEntries\Pages\ManageWaitlistEntries;
use App\Filament\Resources\WaitlistEntries\Schemas\WaitlistEntryForm;
use App\Filament\Resources\WaitlistEntries\Tables\WaitlistEntriesTable;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\WaitlistEntry;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Quienes quieren un horario antes o un día que hoy está lleno. Cuando se libera un hueco, se le
 * ofrece por WhatsApp a quien le sirve; al agendarle la cita sale sola de la lista.
 */
class WaitlistEntryResource extends Resource
{
    use HasSpanishLabels;

    protected static ?string $slug = 'lista-de-espera';

    protected static ?string $model = WaitlistEntry::class;

    protected static ?string $modelLabel = 'paciente en espera';

    protected static ?string $pluralModelLabel = 'lista de espera';

    protected static ?string $navigationLabel = 'Lista de espera';

    protected static ?string $navigationParentItem = 'Agenda';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public static function form(Schema $schema): Schema
    {
        return WaitlistEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WaitlistEntriesTable::configure($table);
    }

    /** Con la próxima cita que ya tiene para lo mismo: entonces se le adelanta en vez de darle otra. */
    public static function getEloquentQuery(): Builder
    {
        $upcoming = fn (string $column) => Appointment::query()
            ->select($column)
            ->whereColumn('patient_id', 'waitlist_entries.patient_id')
            ->where(fn (Builder $treatment) => $treatment->whereNull('waitlist_entries.treatment_id')->orWhereColumn('treatment_id', 'waitlist_entries.treatment_id'))
            ->where('starts_at', '>=', now())
            ->whereIn('status', AppointmentStatus::occupying())
            ->orderBy('starts_at')
            ->limit(1);

        return parent::getEloquentQuery()
            ->with(['patient.account', 'treatment', 'doctor', 'offeredDoctor', 'appointment'])
            ->whereHas('patient')
            ->addSelect([
                'upcoming_appointment_id' => $upcoming('id'),
                'upcoming_appointment_at' => $upcoming('starts_at'),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->active()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'info';
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWaitlistEntries::route('/'),
        ];
    }

    /**
     * La agenda de ese día con la ventana para ofrecerle el horario ya abierta y el mensaje escrito: a dónde lleva
     * la alerta de un horario que se liberó.
     */
    public static function urlForOffer(WaitlistEntry $entry, Doctor $doctor, CarbonInterface $startsAt): string
    {
        return Agenda::getUrl([
            'date' => $startsAt->toDateString(),
            'mode' => 'day',
            'doctor' => $doctor->getKey(),
            'action' => 'offer',
            'actionArguments' => ['entry' => $entry->getKey(), 'doctor' => $doctor->getKey(), 'date' => $startsAt->toDateString(), 'time' => $startsAt->format('H:i')],
        ], panel: 'admin');
    }
}
