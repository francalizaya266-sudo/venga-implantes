{{-- Las tablas de Ingresos. Una tabla angosta que quedaría sola en su fila va a todo el ancho (no deja media fila vacía), y la
     zona que se desliza (de lado en el celular, o la de los días) se alcanza con el teclado. --}}
<x-filament-widgets::widget>
    <div class="earnings-grid">
        @foreach ($tables as $table)
            <x-filament::section :heading="$table['title']" :description="$table['key'] === 'period' ? $best : null" @class(['is-wide' => in_array($table['key'], $fullWidth, true)])>
                <div @class(['earnings-scroll', 'earnings-period' => $table['key'] === 'period']) tabindex="0" role="region" aria-label="{{ $table['title'] }}">
                    @include('reports.partials.table', ['table' => $table])
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-widgets::widget>
