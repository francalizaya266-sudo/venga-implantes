{{-- Lo que se va a agendar, en filas cortas: es lo que recepción le confirma al paciente antes de "Agendar cita". --}}
@if ($rows === [])
    <p class="appointment-summary-empty">Elige el tratamiento y el horario: aquí verás qué día, a qué hora termina y cuánto cuesta.</p>
@else
    <dl class="appointment-summary-rows" role="status">
        @foreach ($rows as $row)
            <div @class(['is-pending' => $row['pending']])>
                <dt>{{ $row['label'] }}</dt>
                <dd>{{ $row['value'] }}</dd>
            </div>
        @endforeach
    </dl>
@endif
