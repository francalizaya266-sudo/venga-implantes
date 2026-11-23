{{-- Las tablas del cierre. En el celular, los pagos, los gastos y lo anulado van como tarjetas (.cc-cards); una tabla
     angosta que quedaría sola en su fila va a todo el ancho. Si una tabla se desliza de lado, se alcanza con el teclado. --}}
<x-filament-widgets::widget>
    <div class="earnings-grid">
        @foreach ($tables as $table)
            <x-filament::section :heading="$table['title']" @class(['is-wide' => in_array($table['key'], $fullWidth, true)])>
                <div class="earnings-scroll" tabindex="0" role="region" aria-label="{{ $table['title'] }}">
                    @include('reports.partials.table', [
                        'table' => $table,
                        'empty' => 'Sin pagos este día.',
                        'tableClass' => in_array($table['key'], ['payments', 'expenses', 'voided'], true) ? 'cc-cards' : null,
                    ])
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-widgets::widget>
