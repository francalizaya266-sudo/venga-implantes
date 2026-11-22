{{-- Etiquetas del paciente: cada una lleva a donde se resuelve, o abre ahí mismo su ventana (cobrar, saludar). --}}
@if ($chips !== [])
    <ul class="pt-chips" aria-label="{{ $label }}">
        @foreach ($chips as $chip)
            <li>
                @if ($chip['action'])
                    <button type="button" class="pt-chip is-{{ $chip['color'] }}" wire:click="mountAction('{{ $chip['action']['name'] }}', {{ \Illuminate\Support\Js::from($chip['action']['arguments']) }})">
                        <x-filament::icon :icon="$chip['icon']" class="pt-chip-icon" />
                        {{ $chip['label'] }}
                    </button>
                @elseif ($chip['url'])
                    <a href="{{ $chip['url'] }}" class="pt-chip is-{{ $chip['color'] }}">
                        <x-filament::icon :icon="$chip['icon']" class="pt-chip-icon" />
                        {{ $chip['label'] }}
                    </a>
                @else
                    <span class="pt-chip is-{{ $chip['color'] }}">
                        <x-filament::icon :icon="$chip['icon']" class="pt-chip-icon" />
                        {{ $chip['label'] }}
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
