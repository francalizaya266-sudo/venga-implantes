@use('App\Support\Money')
@use('App\Filament\Widgets\CashClosingCheck')
{{-- Si el día cuadra: lo que había para cobrar contra lo cobrado, quién se fue debiendo y el arqueo del efectivo. --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Cuadre del día" icon="heroicon-o-scale">
        @if ($wholeDay)
            <dl class="cc-figures">
                <div><dt>Para cobrar</dt><dd>{{ Money::format($expected) }}</dd></div>
                <div><dt>Cobrado</dt><dd>{{ Money::format($collected) }}</dd></div>
                <div @class(['is-short' => $pendingTotal > 0])><dt>Falta</dt><dd>{{ Money::format($pendingTotal) }}</dd></div>
            </dl>

            @if ($pending->isNotEmpty())
                <h3 class="cc-title">Se fueron debiendo</h3>
                <ul class="cc-rows">
                    @foreach ($pending as $row)
                        <li>
                            <span class="cc-row-text">
                                <strong>{{ $row['patient']->full_name }}</strong>
                                <small>{{ $row['concepts'] }}</small>
                            </span>
                            <span class="cc-amount">{{ Money::format($row['due']) }}</span>
                            {{ ($this->collectAction)(['patient' => $row['patient']->getKey()]) }}
                        </li>
                    @endforeach
                </ul>
            @elseif ($expected > 0)
                <p class="cc-ok">Se cobró todo lo de este día.</p>
            @endif

            {{-- Sin cerrar: ya pasó su hora y nadie marcó si vino (la misma regla y la misma cuenta de Hoy). Se resuelven
                 aquí con los botones de la agenda; una acción que no aplica a esa cita no se pinta. --}}
            @if ($unclosed->isNotEmpty())
                <h3 class="cc-title">Citas sin cerrar <span class="cc-title-count">{{ $unclosed->count() }}</span></h3>
                <ul class="cc-rows">
                    @foreach ($unclosed as $row)
                        <li wire:key="cc-unclosed-{{ $row['id'] }}">
                            <span class="cc-row-text">
                                <strong>{{ $row['time'] }} · {{ $row['name'] }}</strong>
                                <small>{{ $row['detail'] }}</small>
                            </span>
                            <span class="cc-row-actions">
                                @foreach ([$this->completeAction, $this->missedAction] as $action)
                                    @if (($action = $action(['appointment' => $row['id']]))->isVisible())
                                        {{ $action }}
                                    @endif
                                @endforeach
                                <x-filament::link :href="$row['url']" size="sm" color="gray">Ver cita</x-filament::link>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($earlierUnclosed > 0)
                <p class="cc-aside">
                    {{ $earlierUnclosed === 1 ? 'Además queda 1 cita de días anteriores sin cerrar.' : 'Además quedan '.$earlierUnclosed.' citas de días anteriores sin cerrar.' }}
                    <x-filament::link :href="$unclosedUrl" size="sm">Verlas en Citas</x-filament::link>
                </p>
            @endif

            {{-- En curso: el paciente sigue en sala o en atención. No es un olvido, pero todavía no se cobró. --}}
            @if ($inCourse->isNotEmpty())
                <h3 class="cc-title">En curso <span class="cc-title-count">{{ $inCourse->count() }}</span></h3>
                <ul class="cc-rows">
                    @foreach ($inCourse as $row)
                        <li wire:key="cc-in-course-{{ $row['id'] }}">
                            <span class="cc-row-text">
                                <strong>{{ $row['time'] }} · {{ $row['name'] }}</strong>
                                <small>{{ $row['detail'] }}</small>
                            </span>
                            <span class="cc-row-actions">
                                @if (($complete = ($this->completeAction)(['appointment' => $row['id']]))->isVisible())
                                    {{ $complete }}
                                @endif
                                <x-filament::link :href="$row['url']" size="sm" color="gray">Ver cita</x-filament::link>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div @class(['cc-status', 'is-closed' => $record])>
                @if ($record)
                    <x-filament::icon icon="heroicon-o-lock-closed" class="cc-status-icon" />
                    <span class="cc-row-text">
                        <strong>Cerrado por {{ $record->closedBy?->name ?? 'alguien que ya no está' }} a las {{ $record->created_at->format('H:i') }}</strong>
                        <small>
                            Fondo {{ Money::format($record->opening_cash) }} · debía haber {{ Money::format($record->expected_cash) }} · se contó {{ Money::format($record->counted_cash) }}.
                            {{ CashClosingCheck::differenceText($record->difference()) }}{{ $record->notes ? ' '.$record->notes : '' }}
                        </small>
                    </span>
                    {{ $this->reopenDayAction }}
                @else
                    <x-filament::icon icon="heroicon-o-calculator" class="cc-status-icon" />
                    <span class="cc-row-text">
                        <strong>Falta el arqueo</strong>
                        <small>Al terminar el día, cuenta el efectivo y ciérralo: queda la diferencia, si la hay.</small>
                    </span>
                    {{ $this->closeDayAction }}
                @endif
            </div>
        @else
            <p class="cc-ok">Cobrado por esta persona: {{ Money::format($collected) }}. El cuadre y el arqueo se hacen con el día completo (Recibió: Todos).</p>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
