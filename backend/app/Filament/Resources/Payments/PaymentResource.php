<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Concerns\HasSpanishLabels;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Filament\Support\MenuPending;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Pagos recibidos en el consultorio. Solo informativo: no se cobra en línea. */
class PaymentResource extends Resource
{
    use HasSpanishLabels;

    protected static ?string $slug = 'pagos';

    protected static ?string $model = Payment::class;

    protected static ?string $modelLabel = 'pago';

    protected static ?string $pluralModelLabel = 'pagos';

    protected static ?string $navigationLabel = 'Caja';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    /** Lo que espera en caja: a quiénes falta cobrarles lo de hoy. */
    public static function getNavigationBadge(): ?string
    {
        return MenuPending::badge(MenuPending::cash());
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return MenuPending::tooltip(MenuPending::cash());
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['patient', 'treatment', 'doctor', 'receivedBy']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
        ];
    }
}
