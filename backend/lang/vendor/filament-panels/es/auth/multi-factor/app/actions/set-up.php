<?php

// Activar la doble verificación en tres pasos, como en la pantalla de primer ingreso. Se trata de "tú".
return [

    'label' => 'Activar',

    'modal' => [

        'heading' => 'Protege tu cuenta con tu celular',

        'description' => <<<'BLADE'
            1. Instala Google Authenticator en tu celular (<x-filament::link href="https://apps.apple.com/app/google-authenticator/id388497605" target="_blank">iPhone</x-filament::link>, <x-filament::link href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank">Android</x-filament::link>).
            BLADE,

        'content' => [

            'qr_code' => [

                'instruction' => '2. Escanea este código con la aplicación:',

                'alt' => 'Código para escanear con Google Authenticator',

            ],

            'text_code' => [

                'instruction' => '¿No puedes escanearlo? Escribe esta clave en la aplicación:',

                'messages' => [
                    'copied' => 'Copiada',
                ],

            ],

            'recovery_codes' => [

                'instruction' => 'Guarda estos códigos de recuperación en un lugar seguro. Te sirven para entrar si un día no tienes tu celular a la mano. Cada uno sirve una sola vez y solo se muestran ahora:',

            ],

        ],

        'form' => [

            'password' => [
                'label' => 'Tu contraseña',
                'validation_attribute' => 'contraseña',
            ],

            'code' => [

                'label' => '3. Escribe los 6 números que te muestra',

                'validation_attribute' => 'código',

                'below_content' => 'Desde ahora te los pediremos cada vez que entres, además de tu contraseña.',

                'messages' => [

                    'invalid' => 'Ese código no coincide. Escribe el que aparece ahora en la aplicación.',

                    'rate_limited' => 'Demasiados intentos. Espera un momento y vuelve a intentarlo.',

                ],

            ],

        ],

        'actions' => [

            'submit' => [
                'label' => 'Activar',
            ],

        ],

    ],

    'notifications' => [

        'enabled' => [
            'title' => 'Listo: tu cuenta está protegida con tu celular',
        ],

    ],

];
