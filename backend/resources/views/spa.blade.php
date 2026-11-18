{{--
    El documento de la web. Las páginas las pinta el frontend (React, en la carpeta frontend/ del repositorio)
    dentro de #app; aquí va lo que tiene que estar antes de que cargue: el título, la descripción y los datos
    estructurados que leen Google y WhatsApp, y los datos de la página para que salga completa a la primera.
    Lo arma App\Services\Web\SpaDocument.

    meta: título, descripción, imagen y datos estructurados de la página (App\Services\Web\PageMeta).
    preload: las respuestas de la API que el frontend usaría para pintar esta página, por su dirección.
    error: el estado, si Laravel está respondiendo con un error (403, 404, 429…).
    clinic: el nombre del consultorio.
--}}
<!DOCTYPE html>
<html lang="es-PE">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $meta['title'] }}</title>
    @if ($meta['description'])
        <meta name="description" content="{{ $meta['description'] }}">
    @endif
    @if ($meta['noindex'])
        <meta name="robots" content="noindex">
    @else
        <link rel="canonical" href="{{ url()->current() }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_PE">
    <meta property="og:site_name" content="{{ $clinic }}">
    <meta property="og:title" content="{{ $meta['og_title'] }}">
    @if ($meta['description'])
        <meta property="og:description" content="{{ $meta['description'] }}">
    @endif
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $meta['image'] }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#ffffff">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preload" href="{{ asset('fonts/inter-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/poppins-latin-700.woff2') }}" as="font" type="font/woff2" crossorigin>
    {{-- Con JavaScript, las secciones aparecen suaves al llegar a ellas. --}}
    <script>document.documentElement.classList.add('js')</script>
    @viteReactRefresh
    @vite('src/main.tsx', 'app')
    @foreach ($meta['json_ld'] as $data)
        <script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach
</head>
<body>
<div id="app"@if ($error) data-error="{{ $error }}"@endif></div>
<noscript>
    <p style="max-width: 40rem; margin: 3rem auto; padding: 0 1.25rem; font: 1rem/1.5 system-ui, sans-serif;">
        Para ver {{ $clinic }} y reservar tu cita, activa JavaScript en tu navegador.
    </p>
</noscript>
{{-- Los datos de la página: JSON_HEX_TAG no deja cerrar la etiqueta desde un texto. --}}
<script id="preload" type="application/json">{!! json_encode($preload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
</body>
</html>
