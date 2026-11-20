{{-- Los 7 días en una fila (cuánto se ocupó cada uno y cuánto queda libre); al tocar uno, debajo, sus huecos. --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Huecos de la semana" icon="heroicon-o-calendar" :description="$summary">
        @if ($days === [])
            <div class="dash-empty">
                <x-filament::icon icon="heroicon-o-calendar" class="dash-empty-icon" />
                <p>No hay horario de atención en los próximos 7 días.</p>
            </div>
        @else
            <div class="dash-week" x-data="{ day: @js($selected) }">
                <div class="dash-week-strip" role="tablist" aria-label="Días de la semana" style="--days: {{ count($days) }}">
                    @foreach ($days as $i => $day)
                        <button
                            type="button"
                            role="tab"
                            id="week-day-{{ $i }}"
                            aria-controls="week-gaps-{{ $i }}"
                            aria-label="{{ $day['label'] }}: {{ $day['free'] }}"
                            x-on:click="day = {{ $i }}"
                            x-bind:aria-selected="day === {{ $i }}"
                            x-bind:class="{ 'is-selected': day === {{ $i }} }"
                            @class(['dash-week-day', 'is-full' => $day['occupancy'] >= 100, 'is-selected' => $i === $selected])
                        >
                            <span class="dash-week-name">{{ $day['short'] }}</span>
                            <span class="dash-week-number">{{ $day['number'] }}</span>
                            <span class="dash-meter" aria-hidden="true"><span style="width: {{ $day['occupancy'] }}%"></span></span>
                            <span class="dash-week-free">{{ $day['free_short'] }}</span>
                        </button>
                    @endforeach
                </div>

                @foreach ($days as $i => $day)
                    <div
                        id="week-gaps-{{ $i }}"
                        role="tabpanel"
                        aria-labelledby="week-day-{{ $i }}"
                        class="dash-day"
                        x-show="day === {{ $i }}"
                        @if ($i !== $selected) x-cloak @endif
                    >
                        <p class="dash-day-head">
                            <b>{{ $day['label'] }}</b>
                            <span @class(['dash-day-free', 'is-full' => $day['occupancy'] >= 100])>{{ $day['free'] }}</span>
                        </p>
                        @foreach ($day['doctors'] as $doctor)
                            <small>@if ($doctor['name'])<b>{{ $doctor['name'] }}:</b> @endif{{ $doctor['gaps'] }}</small>
                        @endforeach
                        @if ($day['waiting'])
                            <a href="{{ $day['waiting_url'] }}" class="dash-day-wait">
                                <x-filament::icon icon="heroicon-m-queue-list" class="dash-day-wait-icon" />
                                {{ $day['waiting'] }}
                            </a>
                        @endif
                        <x-filament::link :href="$day['url']" size="sm" class="dash-day-open">Abrir la agenda de ese día</x-filament::link>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
