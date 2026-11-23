<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasSettingsMenuGroup;
use App\Models\Setting;
use App\Models\User;
use App\Services\ClinicSetupChecklist;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;

/**
 * La puesta a punto del consultorio, como la de la página web: lo que hay que dejar listo antes de atender con el
 * sistema, cada cosa con el enlace a donde se hace y "x de y listos". Va primera en Configuración, con el número de lo
 * que falta en el menú; Configuración igual sigue abriendo Ajustes.
 */
class ClinicSetup extends Page
{
    use HasSettingsMenuGroup;

    protected string $view = 'filament.pages.clinic-setup';

    protected static ?string $title = 'Puesta a punto del consultorio';

    protected static ?string $navigationLabel = 'Puesta a punto';

    protected static ?string $slug = 'puesta-a-punto';

    protected static ?string $navigationParentItem = 'Configuración';

    protected static ?int $navigationSort = -1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    public static function settingsMenuGroup(): string
    {
        return 'Consultorio';
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->isAdmin();
    }

    /** Cuántas cosas faltan para empezar a atender: se ve en el menú sin tener que entrar. */
    public static function getNavigationBadge(): ?string
    {
        $missing = count(app(ClinicSetupChecklist::class)->missing());

        return $missing > 0 ? (string) $missing : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Lo que falta para empezar a atender';
    }

    public function getSubheading(): string
    {
        return 'Lo que hay que dejar listo antes de atender con el sistema. Cada cosa se hace una sola vez.';
    }

    /** Donde quien atiende también recibe a los pacientes, no hace falta un usuario de recepción. */
    public function skipReceptionAction(): Action
    {
        return Action::make('skipReception')
            ->label('Nadie trabaja en recepción')
            ->link()
            ->color('gray')
            ->size(Size::Small)
            ->action(function (): void {
                Setting::set(ClinicSetupChecklist::NO_RECEPTION, true);

                Notification::make()->title('Listo: no hace falta un usuario de recepción')->success()->send();
            });
    }

    /** @return array{items: list<array<string, mixed>>, allDone: bool} */
    protected function getViewData(): array
    {
        $items = app(ClinicSetupChecklist::class)->items();

        return [
            'items' => $items,
            'allDone' => collect($items)->every(fn (array $item) => $item['done']),
        ];
    }
}
