@include('errors.page', [
    'status' => 429,
    'title' => 'Demasiados intentos seguidos',
    'lead' => 'Por seguridad, espera unos minutos y vuelve a intentarlo.',
])
