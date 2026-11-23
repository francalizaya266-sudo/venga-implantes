{{-- Cómo va el mes en una línea; toda la línea lleva a Reportes, donde está el detalle. --}}
<x-filament-widgets::widget>
    <a href="{{ $url }}" class="dash-month">
        <x-filament::icon icon="heroicon-o-banknotes" class="dash-month-icon" />
        <span class="dash-month-text">
            {{ $label }}: <strong>{{ $collected }}</strong> cobrados
            @if ($goal !== null)
                · {{ $goal }} % de la meta
            @endif
        </span>
        <span class="dash-month-more">
            Ver reportes
            <x-filament::icon icon="heroicon-m-arrow-right" class="dash-month-more-icon" />
        </span>
    </a>
</x-filament-widgets::widget>
