{{-- El QR de Yape o Plin del consultorio, para que el paciente lo escanee desde el mostrador. --}}
<figure class="pay-qr">
    <img src="{{ $url }}" alt="Código QR de {{ $method }} del consultorio" class="pay-qr-image" loading="lazy">
    <figcaption class="pay-qr-text">
        <strong>Escanea con {{ $method }}</strong>
        @if (filled($holder))
            <span>A nombre de {{ $holder }}</span>
        @endif
        <small>Cuando el paciente te muestre el pago en su celular, anota el n.º de operación.</small>
    </figcaption>
</figure>
