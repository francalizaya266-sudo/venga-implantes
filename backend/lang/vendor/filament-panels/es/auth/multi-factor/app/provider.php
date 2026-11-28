<?php

// La doble verificación con la aplicación del celular: en Mi perfil y al entrar. Se trata de "tú", sin tecnicismos.
return [

    'management_schema' => [

        'actions' => [

            'label' => 'Código en tu celular',

            'below_content' => 'Además de tu contraseña, escribes los 6 números que te muestra Google Authenticator en tu celular. En cada computadora o celular se te pide una vez al día.',

            'messages' => [
                'enabled' => 'Activada',
                'disabled' => 'Sin activar',
            ],

        ],

    ],

    'login_form' => [

        'label' => 'Con el código de tu celular',

        'code' => [

            'label' => 'Escribe los 6 números de la aplicación',

            'validation_attribute' => 'código',

            'actions' => [

                'use_recovery_code' => [
                    'label' => 'No tengo mi celular a la mano',
                ],

            ],

            'messages' => [

                'invalid' => 'Ese código no coincide. Escribe el que aparece ahora en la aplicación.',

            ],

        ],

        'recovery_code' => [

            'label' => 'Escribe uno de los códigos de recuperación que guardaste',

            'validation_attribute' => 'código de recuperación',

            'messages' => [

                'invalid' => 'Ese código de recuperación no coincide o ya se usó. Si no tienes ninguno, pídele a quien administra que te quite la doble verificación.',

            ],

        ],

    ],

];
