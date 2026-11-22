{{-- Al registrar un paciente, a la derecha: si ya está, a qué familia va y si necesita apoderado (PatientForm::reviewRows). --}}
@php
    $icons = [
        'success' => 'heroicon-m-check-circle',
        'warning' => 'heroicon-m-exclamation-triangle',
        'info' => 'heroicon-m-user-group',
        'gray' => 'heroicon-m-information-circle',
    ];
@endphp
<ul class="reg-check" aria-live="polite">
    @foreach ($rows as $row)
        <li class="reg-check-row is-{{ $row['tone'] }}">
            <x-filament::icon :icon="$icons[$row['tone']] ?? $icons['gray']" class="reg-check-icon" />
            <span>{{ $row['text'] }}</span>
        </li>
    @endforeach
</ul>
