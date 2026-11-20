{{-- El título de la agenda y, al lado, "Todos" y cada doctor: la elegida queda marcada y se recuerda. --}}
<div class="fi-ta-header dash-agenda-header">
    <h3 class="fi-ta-header-heading">Agenda de hoy</h3>

    <nav class="dash-segmented dash-doctor-switch" aria-label="Ver la agenda de">
        @foreach ($doctors as $doctor)
            <button
                type="button"
                wire:click="showDoctor({{ $doctor['id'] ?? 'null' }})"
                aria-pressed="{{ $doctor['active'] ? 'true' : 'false' }}"
                @class(['is-active' => $doctor['active']])
            >{{ $doctor['name'] }}</button>
        @endforeach
    </nav>
</div>
