@php
    use App\Support\Money;
@endphp

{{--
    El panel de la cita, por bloques: la alerta médica arriba de todo (lo que no se puede pasar por alto), quién es y en
    qué está, lo clínico que conviene saber antes de atender, el detalle de la cita, lo que se cobra ese día como una
    mini factura y la nota interna. Los botones del día van abajo, con un solo botón principal.
--}}
<div class="appt-panel">
    @if ($alerts)
        <div @class(['appt-panel-alert', 'is-caution' => ! $alertsSevere]) role="alert">{{ $alertsSevere ? '⚠ ' : '' }}{{ implode(' · ', $alerts) }}</div>
    @endif

    <section class="appt-panel-who">
        <span class="appt-panel-avatar" aria-hidden="true">{{ $initials }}</span>
        <div class="appt-panel-who-body">
            @if ($phone || $document)
                <p class="appt-panel-contact">
                    @if ($phone)
                        <a href="{{ $phoneLink }}" class="appt-panel-phone">
                            <x-filament::icon icon="heroicon-m-phone" class="appt-panel-phone-icon" />
                            {{ $phone }}
                        </a>
                    @endif
                    @if ($document)
                        <span>{{ $document }}</span>
                    @endif
                </p>
            @endif

            <div class="appt-panel-chips">
                <x-filament::badge :color="$state['color']">{{ $state['label'] }}</x-filament::badge>
                @if ($unclosed)
                    <x-filament::badge color="danger" icon="heroicon-m-exclamation-triangle">Sin cerrar: {{ $appointment->status->isChangeable() ? 'marca si vino' : 'falta cerrar su atención' }}</x-filament::badge>
                @elseif ($appointment->needsCall())
                    <x-filament::badge color="danger" icon="heroicon-m-phone">Por confirmar: llamar</x-filament::badge>
                @endif
                @if ($block)
                    <x-filament::badge color="danger" icon="heroicon-m-no-symbol">En bloqueo: muévela</x-filament::badge>
                @endif
                @if ($appointment->is_overbooked)
                    <x-filament::badge color="warning" icon="heroicon-m-square-2-stack">Sobrecupo</x-filament::badge>
                @endif
                @if ($appointment->is_new_patient)
                    <x-filament::badge color="info">Paciente nuevo</x-filament::badge>
                @endif
                @if ($balance !== null)
                    @if ($balance > 0)
                        <x-filament::badge color="warning">Debe {{ Money::short($balance) }}</x-filament::badge>
                    @elseif ($balance < 0)
                        <x-filament::badge color="success">A favor {{ Money::short(-$balance) }}</x-filament::badge>
                    @else
                        <x-filament::badge color="success">Al día</x-filament::badge>
                    @endif
                @endif
            </div>
        </div>
    </section>

    @if ($block)
        <div class="appt-panel-alert is-caution" role="alert">Ese horario quedó bloqueado por "{{ $block->reason ?: $block->type->getLabel() }}" con esta cita ya dada. En "Más" usa "Mover cita": al paciente le queda listo el aviso del cambio.</div>
    @endif

    {{-- Solo para quien atiende. "Sin alergias" solo si alguien llenó los antecedentes: si nadie preguntó, se dice. --}}
    @if ($health !== null && ($health['background'] !== 'alerts' || $health['consent'] !== null))
        <div class="appt-panel-chips appt-panel-health">
            @if ($health['background'] === 'clear')
                <x-filament::badge color="success" icon="heroicon-m-shield-check">Sin alergias ni enfermedades registradas</x-filament::badge>
            @elseif ($health['background'] === 'missing')
                <a href="{{ $health['url'] }}" class="appt-panel-health-link">
                    <x-filament::badge color="warning" icon="heroicon-m-question-mark-circle">Antecedentes sin llenar: completar</x-filament::badge>
                </a>
            @endif
            @if ($health['consent'] === true)
                <x-filament::badge color="success" icon="heroicon-m-check">Consentimiento firmado</x-filament::badge>
            @elseif ($health['consent'] === false)
                <x-filament::badge color="warning" icon="heroicon-m-document-text">Falta consentimiento</x-filament::badge>
            @endif
        </div>
    @endif

    <section class="appt-panel-card">
        <h3>Detalle de la cita</h3>
        <dl>
            <dt>Tratamiento</dt>
            <dd>{{ $appointment->treatment->name }}</dd>
            <dt>Duración</dt>
            <dd>{{ $length }}</dd>
            <dt>Doctor</dt>
            <dd>{{ $appointment->doctor->name }}</dd>
            @if ($series)
                <dt>Se repite</dt>
                <dd>Cita {{ $series['position'] }} de {{ $series['total'] }} de su serie</dd>
            @endif
            @if (filled($appointment->patient_note))
                <dt>Nota del paciente</dt>
                <dd>{{ $appointment->patient_note }}</dd>
            @endif
        </dl>
    </section>

    @if ($toCharge && $toCharge['lines'] !== [])
        <section class="appt-panel-card appt-panel-bill">
            <h3>Para cobrar {{ $isToday ? 'hoy' : 'ese día' }}</h3>
            <table>
                <tbody>
                    @foreach ($toCharge['lines'] as $line)
                        <tr>
                            <th scope="row">{{ $line['kind'] === 'visit' ? ($isToday ? 'Cita de hoy: ' : 'Esta cita: ') : '' }}{{ $line['label'] }} {!! $line['approx'] ? '<small>(aprox.)</small>' : '' !!}</th>
                            <td>{{ $line['amount'] !== null ? Money::format($line['amount']) : 'Incluida' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row">Total a cobrar {!! $toCharge['approx'] ? '<small>(aprox.)</small>' : '' !!}</th>
                        <td>{{ Money::format($toCharge['total']) }}</td>
                    </tr>
                </tfoot>
            </table>
            @if ($toCharge['approx'])
                <p class="appt-panel-hint">El precio del tratamiento es "desde": el doctor puede cobrar más según lo que haga.</p>
            @endif
        </section>
    @endif

    <section class="appt-panel-card appt-panel-note">
        <div class="appt-panel-card-head">
            <h3>Nota interna</h3>
            {{ $action->getModalAction('editNotes') }}
        </div>
        @if (filled($appointment->internal_notes))
            <p>{{ $appointment->internal_notes }}</p>
        @else
            <p class="appt-panel-empty">Sin nota. Solo la ve el personal.</p>
        @endif
    </section>

    <div class="appt-panel-links">
        <x-filament::button tag="a" :href="$patientUrl" color="gray" outlined size="sm" icon="heroicon-m-user">Ficha del paciente</x-filament::button>
        <x-filament::button tag="a" :href="$appointmentUrl" color="gray" outlined size="sm" icon="heroicon-m-arrow-top-right-on-square">Ver todo de la cita</x-filament::button>
    </div>
</div>
