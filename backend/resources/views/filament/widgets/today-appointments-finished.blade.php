{{-- Al pie de la agenda: cuántas terminaron y "Ver" u "Ocultar". Plegadas, la agenda deja arriba lo que pide algo. --}}
<div class="dash-agenda-finished">
    <p><span>Terminadas</span> {{ $summary }}</p>

    <x-filament::link
        tag="button"
        type="button"
        wire:click="toggleFinished"
        :icon="$shown ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down'"
        icon-position="after"
        color="gray"
        size="sm"
        :aria-expanded="$shown ? 'true' : 'false'"
    >{{ $shown ? 'Ocultar' : 'Ver' }}</x-filament::link>
</div>
