{{-- Para el administrador, al pie del menú: si la copia de cada día se hizo y cuándo se bajó la última fuera del hosting. --}}
@if ($status)
    <a href="{{ $status['url'] }}" class="backup-status is-{{ $status['tone'] }}" title="Ver las copias de seguridad">
        <x-filament::icon :icon="$status['tone'] === 'success' ? 'heroicon-m-shield-check' : 'heroicon-m-exclamation-triangle'" class="backup-status-icon" />
        <span>
            <strong>{{ $status['text'] }}</strong>
            <small>{{ $status['detail'] }}</small>
        </span>
    </a>
@endif
