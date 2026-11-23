{{-- Recién activada la doble verificación: los códigos de recuperación, para descargarlos o copiarlos antes de entrar. --}}
@php
    $text = implode(PHP_EOL, $codes);
@endphp
<div class="mfa-codes" x-data="{ copied: false }">
    <ul class="mfa-codes-list" aria-label="Tus códigos de recuperación">
        @foreach ($codes as $code)
            <li>{{ $code }}</li>
        @endforeach
    </ul>

    <div class="mfa-codes-actions">
        <x-filament::button tag="a" color="gray" size="sm" :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowDownTray" href="data:text/plain;charset=utf-8,{{ rawurlencode($text) }}" download="codigos-de-recuperacion.txt">
            Descargar
        </x-filament::button>
        <x-filament::button color="gray" size="sm" :icon="\Filament\Support\Icons\Heroicon::OutlinedClipboardDocument" x-on:click="window.navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($text) }}); copied = true">
            Copiar
        </x-filament::button>
        <span class="mfa-codes-copied" x-show="copied" x-cloak role="status">Copiados</span>
    </div>

    <p class="mfa-step-note">Cada uno sirve una sola vez. Anótalos en un papel o guarda el archivo donde solo tú lo abras: no se vuelven a mostrar.</p>
</div>
