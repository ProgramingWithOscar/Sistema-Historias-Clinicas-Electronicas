<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Verificación de interacciones medicamentosas
    |--------------------------------------------------------------------------
    |
    | Configuración del módulo App\Support\Interactions (patrón Adapter).
    |
    */

    // Fuente que se usa cuando la petición no pide una en concreto.
    // El vademécum es local: verificar una fórmula nunca depende de que haya
    // internet ni de que un tercero esté disponible.
    'default' => env('INTERACTIONS_SOURCE', 'vademecum'),

    'vademecum' => [
        'csv' => database_path('data/vademecum-interacciones.csv'),
    ],

    'rxnav' => [
        'timeout' => (int) env('RXNAV_TIMEOUT', 5),

        /*
        | Equivalencias nombre → RxCUI.
        |
        | El API de la NLM sólo entiende códigos numéricos, así que el
        | adaptador necesita traducir la entrada antes de llamarlo. En un
        | despliegue real esto se resolvería contra el endpoint /rxcui del
        | propio servicio; aquí se mantiene un catálogo local con los
        | principios activos que maneja el sistema.
        */
        'rxcui' => [
            'losartan' => 52175,
            'ibuprofeno' => 5640,
            'captopril' => 1998,
            'enalapril' => 3827,
            'espironolactona' => 9997,
            'warfarina' => 11289,
            'acido acetilsalicilico' => 1191,
            'acetaminofen' => 161,
            'metformina' => 6809,
            'clopidogrel' => 32968,
            'omeprazol' => 7646,
            'atorvastatina' => 83367,
            'claritromicina' => 21212,
            'amiodarona' => 703,
            'digoxina' => 3407,
            'simvastatina' => 36567,
            'amlodipino' => 17767,
            'tramadol' => 10689,
            'fluoxetina' => 4493,
            'sildenafil' => 136411,
            'nitroglicerina' => 4917,
            'metotrexato' => 6851,
            'trimetoprim' => 10829,
            'litio' => 6448,
            'glibenclamida' => 4815,
        ],
    ],

];
