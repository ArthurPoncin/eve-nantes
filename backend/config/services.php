<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | Clés et options des APIs externes consommées par NOCTAMBULE.
    |
    */

    'openweather' => [
        'key' => env('OPENWEATHER_API_KEY'),
        'city' => env('OPENWEATHER_CITY', 'Nantes,FR'),
    ],

];
