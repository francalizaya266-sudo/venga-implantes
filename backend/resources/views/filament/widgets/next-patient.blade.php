<x-filament-widgets::widget>
    <div class="dash-doctor" wire:poll.30s>
        <x-filament::section heading="Tu próximo paciente" icon="heroicon-o-user">
            @if ($next)
                <div class="dash-next">
                    <div class="dash-next-main">
                        <p class="dash-next-name"><time>{{ $next['time'] }}</time> {{ $next['name'] }}@if ($next['age'])<span> · {{ $next['age'] }}</span>@endif</p>
                        <p class="dash-next-reason">{{ $next['reason'] }}</p>
                        <x-filament::badge :color="$next['stateColor']" icon="heroicon-m-clock">{{ $next['state'] }}</x-filament::badge>
                    </div>
                    <div class="dash-next-actions">
                        {{ ($this->startAction)(['appointment' => $next['id']]) }}
                        <x-filament::button :href="$next['historyUrl']" tag="a" color="gray" icon="heroicon-o-document-text">Ver historia</x-filament::button>
                    </div>
                </div>

                @php
                    $severeAlerts = collect($next['alerts'])->where('severe', true);
                    $otherAlerts = collect($next['alerts'])->where('severe', false);
                    // Sin antecedentes tampoco hay alertas: "Faltan los antecedentes" va en lugar de "Sin alergias".
                    $hasTags = $next['consentMissing'] || $next['alerts'] === [] || $otherAlerts->isNotEmpty() || $next['lab'] !== [];
                @endphp

                {{-- Lo que puede hacer daño en el sillón va entero y en rojo; lo demás, en etiquetas cortas en una línea. --}}
                @if ($severeAlerts->isNotEmpty())
                    <ul class="dash-alerts-severe" aria-label="Alertas médicas">
                        @foreach ($severeAlerts as $alert)
                            <li><x-filament::icon icon="heroicon-m-exclamation-triangle" /> {{ $alert['text'] }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($hasTags)
                    <ul class="dash-alerts" aria-label="Antes de atender">
                        @if ($next['consentMissing'])
                            <li class="is-warning" title="Se firma al empezar la atención."><x-filament::icon icon="heroicon-m-pencil-square" /> Falta firmar el consentimiento</li>
                        @endif
                        @if (! $next['hasBackground'])
                            <li class="is-warning" title="Pregunta por alergias, enfermedades y medicamentos."><x-filament::icon icon="heroicon-m-exclamation-circle" /> Faltan los antecedentes</li>
                        @elseif ($next['alerts'] === [])
                            <li class="is-success" title="Sin alergias ni enfermedades registradas."><x-filament::icon icon="heroicon-m-shield-check" /> Sin alergias ni enfermedades</li>
                        @endif
                        @foreach ($otherAlerts as $alert)
                            <li class="is-warning"><x-filament::icon icon="heroicon-m-information-circle" /> {{ $alert['text'] }}</li>
                        @endforeach
                        @foreach ($next['lab'] as $lab)
                            <li class="is-{{ $lab['tone'] }}" title="{{ $lab['text'] }}"><x-filament::icon icon="heroicon-m-beaker" /> {{ $lab['tag'] }}</li>
                        @endforeach
                    </ul>
                @endif

                <dl class="dash-next-facts">
                    <div><dt>Última atención</dt><dd>{{ $next['last'] ?? 'Es su primera visita' }}</dd></div>
                    @if (filled($next['notes']))
                        <div><dt>Dejó una nota</dt><dd>{{ $next['notes'] }}</dd></div>
                    @endif
                    @if (filled($next['internalNotes']))
                        <div><dt>Nota interna</dt><dd>{{ $next['internalNotes'] }}</dd></div>
                    @endif
                    @if ($othersWaiting > 0)
                        <div><dt>En sala</dt><dd>{{ $othersWaiting === 1 ? '1 paciente más espera' : $othersWaiting.' pacientes más esperan' }}</dd></div>
                    @endif
                </dl>

                @if ($after)
                    <p class="dash-next-after"><span>Después</span> <time>{{ $after['time'] }}</time> {{ $after['text'] }}</p>
                @endif
            @else
                <div class="dash-empty">
                    <x-filament::icon icon="heroicon-o-check-circle" class="dash-empty-icon" />
                    <p>No tienes más pacientes por atender hoy.</p>
                </div>
            @endif

        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
