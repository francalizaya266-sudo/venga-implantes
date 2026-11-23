@include('errors.page', [
    'status' => 500,
    'plain' => true,
    'title' => 'Algo falló de nuestro lado',
    'lead' => 'No es tu culpa. Vuelve a intentarlo en unos minutos y, si sigue pasando, escríbenos por WhatsApp.',
    'panelLead' => 'Vuelve a intentarlo. Si sigue pasando, avisa a quien administra el panel con la hora en que ocurrió.',
])
