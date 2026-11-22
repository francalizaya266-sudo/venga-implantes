{{-- Separador de cada paso de la atención: así se ve de un vistazo en qué parte de la cita va lo que se escribe. La barra fija salta a cada uno. --}}
<div class="consult-step" id="{{ $id }}">
    <span class="consult-step-num" aria-hidden="true">{{ $number }}</span>
    <div>
        <h2 class="consult-step-title">{{ $title }}</h2>
        <p class="consult-step-text">{{ $description }}</p>
    </div>
</div>
