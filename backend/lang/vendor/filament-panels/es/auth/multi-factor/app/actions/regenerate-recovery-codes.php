<?php

// Mi perfil: sacar códigos de recuperación nuevos si se perdieron los de antes. Se trata de "tú".
return [

    'label' => 'Nuevos códigos de recuperación',

    'modal' => [

        'heading' => 'Nuevos códigos de recuperación',

        'description' => 'Si perdiste tus códigos de recuperación, saca unos nuevos. Los de antes dejan de servir en ese momento.',

        'form' => [

            'code' => [

                'label' => 'Escribe los 6 números de la aplicación',

                'validation_attribute' => 'código',

                'messages' => [

                    'invalid' => 'Ese código no coincide. Escribe el que aparece ahora en la aplicación.',

                    'rate_limited' => 'Demasiados intentos. Espera un momento y vuelve a intentarlo.',

                ],

            ],

            'password' => [

                'label' => 'Tu contraseña',

                'validation_attribute' => 'contraseña',

            ],

        ],

        'actions' => [

            'submit' => [
                'label' => 'Sacar códigos nuevos',
            ],

        ],

    ],

    'notifications' => [

        'regenerated' => [
            'title' => 'Ya tienes códigos de recuperación nuevos',
        ],

    ],

    'show_new_recovery_codes' => [

        'modal' => [

            'heading' => 'Tus códigos de recuperación nuevos',

            'description' => 'Guárdalos en un lugar seguro. Te sirven para entrar si un día no tienes tu celular a la mano. Cada uno sirve una sola vez y solo se muestran ahora:',

            'actions' => [

                'submit' => [
                    'label' => 'Ya los guardé',
                ],

            ],

        ],

    ],

];
