@use('App\Support\Money')
{{-- Lo que se cambió al cobrar: otro precio, un descuento, un concepto agregado o quitado, con su motivo y quién. --}}
<x-filament-widgets::widget>
    <x-filament::section :heading="$heading" icon="heroicon-o-receipt-percent">
        @if ($rows->isEmpty())
            <p class="cc-ok">Nadie cambió precios, puso descuentos ni anuló cargos.</p>
        @else
            <p class="cc-adjust-total">
                <strong>{{ $totals['count'] }} {{ $totals['count'] === 1 ? 'cambio' : 'cambios' }}</strong>
                @if ($totals['lowered'] > 0)
                    · se cobró <strong class="is-lowered">{{ Money::format($totals['lowered']) }}</strong> menos de su precio
                @endif
                @if ($totals['raised'] > 0)
                    · {{ Money::format($totals['raised']) }} más por precios subidos
                @endif
            </p>
            <ul class="cc-rows">
                @foreach ($rows as $row)
                    <li>
                        <span class="cc-row-text">
                            <strong>{{ $row['patient'] }} · {{ $row['concept'] }}</strong>
                            <small>{{ $row['change'] }} · {{ filled($row['reason']) ? 'Motivo: '.$row['reason'] : 'Sin motivo' }} · {{ $row['by'] }}, {{ $row['at']->format('H:i') }}</small>
                        </span>
                        @if (abs($row['lowered']) >= 0.005)
                            <span @class(['cc-amount', 'is-lowered' => $row['lowered'] > 0])>{{ $row['lowered'] > 0 ? '−' : '+' }} {{ Money::format(abs($row['lowered'])) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
