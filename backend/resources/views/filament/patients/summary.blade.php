{{-- Bajo el nombre: lo que toca hacer con el paciente, lo que hay que saber de él y cómo escribirle, sin abrir nada. --}}
<div class="pt-summary">
    @if ($step)
        <div class="pt-step" data-step="{{ $step['key'] }}">
            <x-filament::icon :icon="$step['icon']" class="pt-step-icon" />
            <div class="pt-step-text">
                <span class="pt-step-kicker">Siguiente paso</span>
                <strong class="pt-step-label">{{ $step['label'] }}</strong>
                @if ($step['detail'])
                    <span class="pt-step-detail">{{ $step['detail'] }}</span>
                @endif
            </div>
            @if ($step['button'] || $step['remind'])
                <div class="pt-step-buttons">
                    {{-- Al que faltó o le toca control: recordarle por WhatsApp (el mismo aviso de Hoy) o agendarle de una vez. --}}
                    @if ($step['remind'])
                        <x-filament::button size="sm" color="gray" icon="heroicon-m-chat-bubble-left-right" class="pt-step-button" wire:click="mountAction('{{ $step['remind']['name'] }}', {{ \Illuminate\Support\Js::from($step['remind']['arguments']) }})">Recordar por WhatsApp</x-filament::button>
                    @endif
                    @if ($step['button'] && $step['action'])
                        <x-filament::button size="sm" class="pt-step-button" wire:click="mountAction('{{ $step['action']['name'] }}', {{ \Illuminate\Support\Js::from($step['action']['arguments']) }})">{{ $step['button'] }}</x-filament::button>
                    @elseif ($step['button'])
                        <x-filament::button size="sm" class="pt-step-button" tag="a" :href="$step['url']">{{ $step['button'] }}</x-filament::button>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @include('filament.patients.chips', ['chips' => $chips, 'label' => 'Resumen del paciente'])

    @if ($contact)
        <div class="pt-contact">
            {{-- En la página de la cita no va: allí WhatsApp es el botón de arriba, con el aviso de esa cita ya escrito. --}}
            @if ($contact['whatsapp'])
                <x-filament::link :href="$contact['whatsapp']" target="_blank" icon="heroicon-m-chat-bubble-left-right" color="success" size="sm">WhatsApp</x-filament::link>
            @endif
            <x-filament::link :href="$contact['call']" icon="heroicon-m-phone" color="gray" size="sm">Llamar</x-filament::link>
        </div>
    @endif
</div>
