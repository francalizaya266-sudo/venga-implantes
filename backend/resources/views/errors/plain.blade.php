{{-- Error sin base de datos ni sesión: sale aunque el sistema esté caído. --}}
@php
    $clinic = config('consultorio.defaults.clinic.name');
    $whatsapp = preg_replace('/\D/', '', (string) config('consultorio.defaults.clinic.whatsapp'));
@endphp
<!DOCTYPE html>
<html lang="es-PE">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ $clinic }}</title>
    {{-- Los estilos van aquí mismo: esta página tiene que verse aunque falte todo lo demás. --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font: 400 1.0625rem/1.55 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: #0c1a2e; background: #eef3fa; }
        h1 { margin: 0; font-size: clamp(1.625rem, 5.6vw, 2.125rem); font-weight: 750; letter-spacing: -.025em; line-height: 1.15; }
        p { margin: 0; }
        .error-page { min-height: 100svh; display: grid; place-items: center; padding: 24px 16px; }
        .error-card { width: 100%; max-width: 32rem; padding: 32px 28px; border-radius: 14px; background: #fff; box-shadow: 0 1px 2px rgb(12 26 46 / .06), 0 8px 24px rgb(12 26 46 / .06); }
        .error-card > * + * { margin-top: 16px; }
        .error-brand { color: #1e4f91; font-weight: 700; }
        .lead { font-size: 1.125rem; color: #4a5a6e; }
        .error-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 24px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 52px; padding: 12px 24px; border: 0; border-radius: 999px; font: 650 1.0625rem/1.2 inherit; font-family: inherit; text-decoration: none; cursor: pointer; background: #0e2f6b; color: #fff; }
        .btn.quiet { background: #eef3fa; color: #0c1a2e; }
        .btn.whatsapp { background: #11824a; }
    </style>
</head>
<body>
    <main class="error-page">
        <div class="error-card">
            <p class="error-brand">{{ $clinic }}</p>
            <h1>{{ $title }}</h1>
            <p class="lead">{{ $lead }}</p>
            <p class="error-actions">
                @if ($inPanel)
                    <a href="{{ url('/panel') }}" class="btn">Volver al panel</a>
                    <button type="button" class="btn quiet" onclick="history.back()">Atrás</button>
                @else
                    <button type="button" class="btn" onclick="location.reload()">Volver a intentar</button>
                    @if ($whatsapp !== '')
                        <a href="https://wa.me/{{ $whatsapp }}" class="btn whatsapp" rel="noopener">WhatsApp</a>
                    @endif
                @endif
            </p>
        </div>
    </main>
</body>
</html>
