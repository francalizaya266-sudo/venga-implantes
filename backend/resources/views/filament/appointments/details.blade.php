{{-- En qué va la cita (un paso tras otro, con la hora de cada uno) y sus datos. --}}
<div class="ap-details">
    <ol class="ap-steps" aria-label="En qué va la cita">
        @foreach ($steps as $step)
            <li @class(['ap-step', 'is-'.$step['state'], 'is-shown' => $step['shown']]) @if ($step['state'] === 'current') aria-current="step" @endif>
                <span class="ap-step-dot" aria-hidden="true">
                    @if ($step['state'] === 'done')
                        <x-filament::icon icon="heroicon-m-check" class="ap-step-check" />
                    @endif
                </span>
                <span class="ap-step-label">{{ $step['label'] }}</span>
                <span class="ap-step-at">{{ $step['at'] ?? ($step['state'] === 'skipped' ? 'No se marcó' : '') }}</span>
            </li>
        @endforeach
    </ol>

    @if ($closed)
        <p class="ap-closed is-{{ $closed['color'] }}">
            <strong>{{ $closed['label'] }}</strong>
            @if ($closed['detail'])
                <span>{{ $closed['detail'] }}</span>
            @endif
        </p>
    @endif

    @if ($warning)
        <p class="ap-closed is-warning"><strong>{{ $warning }}</strong></p>
    @endif

    {{-- Cada dato es un par etiqueta (con su ícono) y valor (con su aclaración), sin nada más en medio: así un lector de pantalla los lee juntos. --}}
    <dl class="ap-facts">
        @foreach ($facts as $fact)
            <div class="ap-fact">
                <dt>
                    <span class="ap-fact-icon" aria-hidden="true"><x-filament::icon :icon="$fact['icon']" /></span>
                    {{ $fact['label'] }}
                </dt>
                <dd>
                    {{ $fact['value'] }}
                    @if ($fact['hint'])
                        <small>{{ $fact['hint'] }}</small>
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>

    @foreach ($notes as $note)
        <div class="ap-note">
            <span>{{ $note['label'] }}</span>
            <p>{{ $note['text'] }}</p>
        </div>
    @endforeach
</div>
