{{-- Estilos y scripts propios del panel, en archivos que el navegador guarda en caché (el hosting no compila CSS ni JS). --}}
<link rel="stylesheet" href="{{ asset('css/panel.css') }}?v={{ @filemtime(public_path('css/panel.css')) }}">
{{-- El odontograma registra su componente de Alpine: va antes que Alpine, sin defer. --}}
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
