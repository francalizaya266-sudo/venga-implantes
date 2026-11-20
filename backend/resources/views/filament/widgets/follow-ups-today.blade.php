{{-- Arriba, los pendientes que recepción anota a mano; lo hecho hoy queda tachado para el turno que sigue.
     Luego dos bloques: "Resolver hoy" (lo que espera a alguien hoy) y "Seguimiento" (a quién escribirle o llamar),
     plegado si es largo y hay algo que resolver. Solo se pinta el número de lo que no puede esperar, y arriba se
     cuenta cuántos son. El primer grupo con personas va abierto. --}}
<x-filament-widgets::widget class="dash-tall">
    <x-filament::section heading="Por hacer hoy" icon="heroicon-o-clipboard-document-check" description="Avisos, respuestas y a quién escribirle hoy.">
        <x-slot name="afterHeader">
            @if ($urgent > 0)
                <span class="dash-count is-warning dash-urgent">{{ $urgent }} {{ $urgent === 1 ? 'urgente' : 'urgentes' }}</span>
            @endif
            {{ $this->addNoteAction }}
        </x-slot>

        @if ($notes !== [])
            <ul class="dash-rows dash-notes" aria-label="Pendientes anotados">
                @foreach ($notes as $note)
                    <li @class(['is-done' => $note['done']])>
                        <div class="dash-row-text">
                            <span class="dash-name">{{ $note['body'] }}</span>
                            <small @class(['dash-done' => $note['done'], 'dash-late' => $note['late']])>
                                @if ($note['done'])
                                    <x-filament::icon icon="heroicon-m-check" class="dash-done-icon" />
                                @endif
                                {{ $note['status'] }}
                            </small>
                            @if ($note['patient'])
                                <small><a href="{{ $note['patient_url'] }}" class="dash-note-patient">{{ $note['patient'] }}</a></small>
                            @endif
                        </div>
                        <div class="dash-row-actions">
                            {{ $note['done'] ? ($this->noteUndoAction)(['note' => $note['id']]) : ($this->noteDoneAction)(['note' => $note['id']]) }}
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($resolve !== [])
            <section class="dash-block" aria-labelledby="follow-up-resolve">
                <h3 class="dash-block-title" id="follow-up-resolve">Resolver hoy</h3>
                @include('filament.widgets.follow-ups-today-groups', ['groups' => $resolve, 'openKey' => $openKey])
            </section>
        @endif

        @if ($followUp !== [])
            @if ($foldFollowUp)
                <details class="dash-block" wire:key="follow-up-block" wire:ignore.self>
                    <summary class="dash-block-title">
                        Seguimiento
                        <small>{{ count($followUp) }} grupos: presupuestos, planes, controles y más</small>
                    </summary>
                    @include('filament.widgets.follow-ups-today-groups', ['groups' => $followUp, 'openKey' => $openKey])
                </details>
            @else
                <section class="dash-block" aria-labelledby="follow-up-follow">
                    <h3 class="dash-block-title" id="follow-up-follow">Seguimiento</h3>
                    @include('filament.widgets.follow-ups-today-groups', ['groups' => $followUp, 'openKey' => $openKey])
                </section>
            @endif
        @endif

        @if ($resolve === [] && $followUp === [])
            <div @class(['dash-empty', 'has-notes' => $notes !== []])>
                <x-filament::icon icon="heroicon-o-check-circle" class="dash-empty-icon" />
                <p>Todo al día: no hay avisos, presupuestos, planes ni cuotas por perseguir.</p>
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
