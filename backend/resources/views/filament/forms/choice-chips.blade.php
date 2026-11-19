{{-- Botones de radio con forma de botón: en grupos con título y con una línea chica debajo de cada uno. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field" label-tag="div">
    <div role="radiogroup" aria-labelledby="{{ $getId() }}-label" {{ $getExtraAttributeBag()->class(['choice-chips']) }}>
        @foreach ($getGroupedOptions() as $group => $options)
            <div class="choice-chips-group" @if (filled($group)) role="group" aria-label="{{ $group }}" @endif>
                @if (filled($group))
                    <p class="choice-chips-group-title">{{ $group }}</p>
                @endif
                <div class="choice-chips-row">
                    @foreach ($options as $value => $label)
                        @php($description = $getDescription($value))
                        {{-- La clave mantiene cada botón con su valor cuando la lista cambia (por ejemplo, al elegir otro doctor). --}}
                        <label wire:key="{{ $getId() }}-{{ $value }}" @class(['choice-chip', 'is-highlighted' => $isHighlighted($value)])>
                            <input
                                type="radio"
                                class="choice-chip-input"
                                name="{{ $getId() }}"
                                value="{{ $value }}"
                                {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}"
                                @disabled($isDisabled())
                            />
                            <span class="choice-chip-label">{{ $label }}</span>
                            @if (filled($description))
                                <span class="choice-chip-description">{{ $description }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-dynamic-component>
