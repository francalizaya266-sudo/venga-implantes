<?php

// La doble verificación es obligatoria: en Mi perfil no se "apaga". Este botón es para pasarla a un celular nuevo
// (ver PhoneAuthentication): se confirma con el celular de antes y enseguida se escanea el código con el nuevo.
return [

    'label' => 'Cambié de celular',

    'modal' => [

        'heading' => 'Pasar la doble verificación a tu celular nuevo',

        'description' => 'Confirma con el código de tu celular de antes o con un código de recuperación. Enseguida escaneas un código nuevo con el celular nuevo.',

        'form' => [

            'password' => [
                'label' => 'Tu contraseña',
                'validation_attribute' => 'contraseña',
            ],

            'code' => [

                'label' => 'Escribe los 6 números de la aplicación',

                'validation_attribute' => 'código',

                'actions' => [

                    'use_recovery_code' => [
                        'label' => 'Ya no tengo el celular de antes',
                    ],

                ],

                'messages' => [

                    'invalid' => 'Ese código no coincide. Escribe el que aparece ahora en la aplicación.',

                    'rate_limited' => 'Demasiados intentos. Espera un momento y vuelve a intentarlo.',

                ],

            ],

            'recovery_code' => [

                'label' => 'Escribe uno de los códigos de recuperación que guardaste',

                'validation_attribute' => 'código de recuperación',

                'messages' => [

                    'invalid' => 'Ese código de recuperación no coincide o ya se usó.',

                    'rate_limited' => 'Demasiados intentos. Espera un momento y vuelve a intentarlo.',

                ],

            ],

        ],

        'actions' => [

            'submit' => [
                'label' => 'Seguir',
            ],

        ],

    ],

    'notifications' => [

        'disabled' => [
            'title' => 'Ahora escanea el código con tu celular nuevo',
        ],

    ],

];
