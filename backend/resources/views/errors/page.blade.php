{{--
    Página de error en español. En la web pública la muestra el frontend, con el encabezado y el pie de siempre,
    para que el paciente que tocó un enlace viejo pueda seguir. En el panel, y en 500/503 (cuando la base de datos
    puede estar caída), va la versión sin base de datos.
    Recibe: status, title, lead, y opcionales panelTitle, panelLead y plain (forzar la versión sin base de datos).
--}}
@php
    $inPanel = request()->is('panel', 'panel/*');
    $title = $inPanel ? ($panelTitle ?? $title) : $title;
    $lead = $inPanel ? ($panelLead ?? $lead) : $lead;
@endphp
@if ($inPanel || ($plain ?? false))
    @include('errors.plain', ['title' => $title, 'lead' => $lead, 'inPanel' => $inPanel])
@else
    @include('spa', app(\App\Services\Web\SpaDocument::class)->forError(request(), $status))
@endif
