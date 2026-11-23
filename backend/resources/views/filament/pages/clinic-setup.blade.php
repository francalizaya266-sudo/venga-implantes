<x-filament-panels::page>
    {{-- La misma lista que la puesta a punto de la web: lo pendiente primero, con su enlace; lo listo al final. --}}
    @include('filament.pages.setup-checklist', [
        'items' => $items,
        'heading' => $allDone ? 'Todo listo para atender' : 'Lo que falta para empezar a atender',
        'subheading' => $allDone
            ? 'Si algo deja de estar listo (las tareas automáticas se apagan, la copia extra falla), vuelve a aparecer aquí y en Hoy.'
            : 'Lo que falta va primero, con el enlace a donde se hace.',
    ])
</x-filament-panels::page>
