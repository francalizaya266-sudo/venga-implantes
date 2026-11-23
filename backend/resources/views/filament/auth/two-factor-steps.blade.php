{{-- Activar la doble verificación: los tres pasos a la vista, con el código para escanear y los 6 números en el tercero. --}}
<ol class="mfa-steps">
    <li class="mfa-step">
        <span class="mfa-step-number" aria-hidden="true">1</span>
        <div class="mfa-step-body">
            <p class="mfa-step-title">Instala Google Authenticator en tu celular.</p>
            <p class="mfa-step-note">
                Es gratis, en la tienda de tu celular:
                <x-filament::link href="https://apps.apple.com/app/google-authenticator/id388497605" target="_blank" rel="noopener">App Store (iPhone)</x-filament::link>
                o
                <x-filament::link href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank" rel="noopener">Play Store (Android)</x-filament::link>.
            </p>
        </div>
    </li>
    <li class="mfa-step">
        <span class="mfa-step-number" aria-hidden="true">2</span>
        <div class="mfa-step-body">
            <p class="mfa-step-title">Escanea este código.</p>
            <p class="mfa-step-note">En la aplicación toca <strong>+</strong> y luego <strong>Escanear un código QR</strong>.</p>
            @if ($qrCode)
                <img src="{{ $qrCode }}" alt="Código para escanear con Google Authenticator" class="mfa-qr" width="176" height="176">
            @endif
            <p class="mfa-step-note">
                ¿No puedes escanearlo? En la aplicación elige escribir una clave y pon esta:
                <code class="mfa-secret">{{ $secret }}</code>
            </p>
        </div>
    </li>
    <li class="mfa-step">
        <span class="mfa-step-number" aria-hidden="true">3</span>
        <div class="mfa-step-body">
            <p class="mfa-step-title">Escribe los 6 números que te muestra.</p>
            {{ $getChildSchema() }}
        </div>
    </li>
</ol>
