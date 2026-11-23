<?php

// En todo el sistema se trata de "tú" (la traducción de Filament usa "usted").
return [

    'heading' => 'Entra a tu cuenta',

    'actions' => [
        'request_password_reset' => [
            'label' => '¿Olvidaste tu contraseña?',
        ],
    ],

    // Después de la contraseña: el código de la aplicación del celular.
    'multi_factor' => [

        'heading' => 'Confirma que eres tú',

        'subheading' => 'Abre Google Authenticator en tu celular.',

        'form' => [

            'provider' => [
                'label' => '¿Cómo quieres confirmar?',
            ],

            'actions' => [

                'authenticate' => [
                    'label' => 'Entrar',
                ],

            ],

        ],

    ],

    'messages' => [
        'failed' => 'El correo o la contraseña no coinciden.',
    ],

    'notifications' => [
        'throttled' => [
            'title' => 'Demasiados intentos',
            'body' => 'Vuelve a intentarlo en :seconds segundos.',
        ],
    ],

];
