<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Interoperabilidad de la HCE
    |--------------------------------------------------------------------------
    |
    | Parámetros de las familias de exportación (patrón Abstract Factory,
    | App\Support\Interop). Viven en configuración y no en el código porque
    | cambian por institución y por entorno.
    |
    */

    'rda' => [
        // Código de habilitación del prestador (Res. 3100 de 2019), obligatorio
        // en la cabecera del Resumen Digital de Atención.
        'codigo_habilitacion' => env('RDA_CODIGO_HABILITACION', '110010000000'),
    ],

    'anonymized' => [
        // Sal del seudónimo. Sin ella el hash del documento sería reversible por
        // fuerza bruta: el espacio de cédulas colombianas es pequeño.
        'salt' => env('ANONYMIZATION_SALT', env('APP_KEY', 'hce-salt-local')),
    ],

];
