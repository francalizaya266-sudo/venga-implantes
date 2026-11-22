{{--
    En la ficha, sus presupuestos y planes (qué incluye cada uno, en qué quedó y cuánto suma) o sus trabajos de
    laboratorio (qué es, en qué quedó y lo que hay que resolver). Cada uno abre su página.
--}}
@isset($heading)
    <h4 class="pt-list-heading">{{ $heading }}</h4>
@endisset
<ul class="pt-plans">
    @foreach ($rows as $row)
        <li>
            <a href="{{ $row['url'] }}" @class(['pt-plan', 'is-closed' => ! $row['open']])>
                <span class="pt-plan-top">
                    <b>{{ $row['summary'] }}</b>
                    @if ($row['total'])
                        <span class="pt-plan-total">{{ $row['total'] }}</span>
                    @endif
                </span>
                <span class="pt-plan-state">
                    <x-filament::badge :color="$row['status']->getColor()" size="sm">{{ $row['status']->getLabel() }}</x-filament::badge>
                    @if ($row['since'])
                        <small>{{ $row['since'] }}</small>
                    @endif
                </span>
                @if ($row['payment'])
                    <span class="pt-plan-pay">{{ $row['payment'] }}</span>
                @endif
            </a>
        </li>
    @endforeach
</ul>
