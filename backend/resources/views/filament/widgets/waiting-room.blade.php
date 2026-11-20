<x-filament-widgets::widget>
    <x-filament::section heading="Sala de espera" icon="heroicon-o-user-group" :id="\App\Filament\Widgets\WaitingRoom::ANCHOR" class="dash-anchor" wire:poll.30s>
        {{-- Quién atiende y quién está libre: el que está libre y tiene a alguien esperando, en ámbar (hay que pasarlo). --}}
        @if ($rooms !== [])
            <ul class="dash-rooms" aria-label="Quién atiende ahora">
                @foreach ($rooms as $room)
                    <li>
                        <span @class(['dash-room-dot', 'is-busy' => $room['busy']]) aria-hidden="true"></span>
                        <span class="dash-room-text">
                            <strong>{{ $room['name'] }}</strong>
                            <small>{{ $room['status'] }}@if ($room['waiting'] > 0) · <b @class(['is-call' => ! $room['busy']])>{{ $room['waiting'] }} esperando</b>@endif</small>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        @forelse ($patients as $patient)
            <a href="{{ $patient['url'] }}" class="dash-waiting">
                <span>
                    <strong>{{ $patient['name'] }}</strong>
                    <small>{{ $patient['detail'] }}</small>
                </span>
                <span @class(['dash-waiting-time', 'is-long' => $patient['long']])>{{ $patient['waited'] }}</span>
            </a>
        @empty
            <p class="dash-hint">Nadie esperando. Cuando alguien llegue, márcalo con "Llegó" en la agenda.</p>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
