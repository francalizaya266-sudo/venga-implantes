{{-- La campana de Filament. Al abrirla, lo que solo informa queda visto; al cerrarla, se va. --}}
<div
    x-on:x-modal-opened.document="if ($event.detail.id === @js(\App\Livewire\BellAlerts::MODAL_ID)) $wire.opened()"
    x-on:modal-closed.window="if ($event.detail.id === @js(\App\Livewire\BellAlerts::MODAL_ID)) $wire.closed()"
>
    @include('filament-notifications::database-notifications')
</div>
