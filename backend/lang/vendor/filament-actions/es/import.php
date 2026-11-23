<?php

// En todo el sistema se trata de "tú" (la traducción de Filament usa "usted"), con frases cortas y sin palabras técnicas.
return [

    'modal' => [

        'form' => [

            'file' => [

                'placeholder' => 'Elige o arrastra tu archivo CSV',

                'rules' => [
                    'duplicate_columns' => '{0} El archivo tiene más de una columna sin título.|{1,*} El archivo tiene columnas con el mismo título: :columns.',
                ],

            ],

            'columns' => [
                'label' => 'Qué columna del archivo es cada dato',
                'placeholder' => 'Elige una columna',
            ],

        ],

        'actions' => [

            'download_example' => [
                'label' => 'Descargar la plantilla',
            ],

        ],

    ],

    'notifications' => [

        'completed' => [

            'title' => 'Importación terminada',

            'actions' => [

                'download_failed_rows_csv' => [
                    'label' => 'Descargar la fila que no se importó|Descargar las :count filas que no se importaron',
                ],

            ],

        ],

        'max_rows' => [
            'title' => 'El archivo es muy grande',
            'body' => 'Se puede importar 1 fila a la vez.|Se pueden importar hasta :count filas a la vez: divide la lista en partes.',
        ],

        'started' => [
            'title' => 'Importando',
            'body' => 'Se está importando 1 fila. Te avisamos en la campana cuando termine.|Se están importando :count filas. Te avisamos en la campana cuando termine.',
        ],

    ],

    'example_csv' => [
        'file_name' => 'plantilla-:importer',
    ],

    'failure_csv' => [
        'file_name' => 'filas-sin-importar-:import_id-:csv_name',
        'error_header' => 'Qué corregir',
        'system_error' => 'No se pudo guardar esta fila. Revísala y vuelve a importarla.',
        'column_mapping_required_for_new_record' => 'Falta elegir qué columna del archivo es :attribute.',
    ],

];
