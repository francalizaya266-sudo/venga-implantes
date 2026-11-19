<?php

namespace App\Filament\Forms;

use Closure;
use Filament\Forms\Components\ToggleButtons;

/**
 * Botones para elegir una opción, como los de ToggleButtons, pero que pueden ir en grupos con título ("Mañana",
 * "Tarde") y llevar una línea chica debajo ("12 libres", "Recomendada"). Son botones de radio: un segundo toque
 * en el elegido no lo quita.
 */
class ChoiceChips extends ToggleButtons
{
    protected string $view = 'filament.forms.choice-chips';

    /** @var array<string, string|null>|Closure|null Valor => línea chica debajo. */
    protected array|Closure|null $descriptions = null;

    /** @var array<string, string>|Closure|null Valor => título del grupo en que va. */
    protected array|Closure|null $groups = null;

    /** @var list<string>|Closure|null Los valores que van resaltados. */
    protected array|Closure|null $highlighted = null;

    /** @param  array<string, string|null>|Closure|null  $descriptions */
    public function descriptions(array|Closure|null $descriptions): static
    {
        $this->descriptions = $descriptions;

        return $this;
    }

    /** @param  array<string, string>|Closure|null  $groups */
    public function groups(array|Closure|null $groups): static
    {
        $this->groups = $groups;

        return $this;
    }

    /** @param  list<string>|Closure|null  $highlighted */
    public function highlighted(array|Closure|null $highlighted): static
    {
        $this->highlighted = $highlighted;

        return $this;
    }

    public function getDescription(string $value): ?string
    {
        return $this->evaluate($this->descriptions)[$value] ?? null;
    }

    public function isHighlighted(string $value): bool
    {
        return in_array($value, $this->evaluate($this->highlighted) ?? [], true);
    }

    /**
     * Las opciones por grupo, en el orden en que aparece cada grupo. Sin grupos, uno solo sin título.
     *
     * @return array<string, array<string, string>>
     */
    public function getGroupedOptions(): array
    {
        $groups = $this->evaluate($this->groups) ?? [];
        $grouped = [];

        foreach ($this->getOptions() as $value => $label) {
            $grouped[$groups[(string) $value] ?? ''][(string) $value] = (string) $label;
        }

        return $grouped;
    }
}
