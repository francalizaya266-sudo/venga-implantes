{{--
    En el celular la página llega hasta los bordes (viewport-fit=cover): así valen las reglas de panel.css que dejan libre
    la franja de abajo del iPhone ("safe-area-inset"). Va después del meta de Filament, que no lo trae, y lo reemplaza.
    La barra del navegador toma el color de la barra de arriba del panel, en claro y en oscuro.
--}}
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0b1327" media="(prefers-color-scheme: dark)">
{{-- Si se eligió claro u oscuro a mano en el menú, la barra sigue al panel y no al celular. --}}
<script>
    (function () {
        var paint = function () {
            var color = document.documentElement.classList.contains('dark') ? '#0b1327' : '#ffffff';

            document.querySelectorAll('meta[name="theme-color"]').forEach(function (meta) { meta.setAttribute('content', color); });
        };

        paint();
        new MutationObserver(paint).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    })();
</script>
{{-- Estilos y scripts propios del panel, en archivos que el navegador guarda en caché (el hosting no compila CSS ni JS). --}}
<link rel="stylesheet" href="{{ asset('css/panel.css') }}?v={{ @filemtime(public_path('css/panel.css')) }}">
{{-- "Dictar" y el odontograma van antes que Alpine, sin defer: el odontograma registra su componente y usa "Dictar". --}}
<script src="{{ asset('js/dictation.js') }}?v={{ @filemtime(public_path('js/dictation.js')) }}"></script>
<script src="{{ asset('js/odontogram.js') }}?v={{ @filemtime(public_path('js/odontogram.js')) }}"></script>
{{-- Safari del iPhone solo muestra el estado "tocado" (:active) si la página escucha los toques. --}}
<script>document.addEventListener('touchstart', function () {}, { passive: true });</script>
{{--
    Al atender, el menú se pliega solo para dejarle el ancho a la atención, y vuelve a abrirse al salir (si estaba
    abierto). Filament recuerda el menú para todas las pantallas, por eso se anota quién lo plegó. Va antes de Alpine,
    que lo lee al empezar: así no se ve abrirse y cerrarse.
--}}
<script>
    (function (attending) {
        try {
            var open = 'filament:sidebar:desktop_open', foldedByAttend = 'consultorio:sidebar:folded_by_attend';

            if (attending && localStorage.getItem(open) !== 'false') {
                localStorage.setItem(open, 'false');
                localStorage.setItem(foldedByAttend, '1');
            } else if (! attending && localStorage.getItem(foldedByAttend)) {
                localStorage.setItem(open, 'true');
                localStorage.removeItem(foldedByAttend);
            }
        } catch (error) {}
    })(@js(request()->routeIs(\App\Filament\Resources\Consultations\ConsultationResource::getRouteBaseName().'.edit')));
</script>
