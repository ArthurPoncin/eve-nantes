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

    'nantes_open_data' => [
        'events_url' => env('NANTES_EVENTS_URL', 'https://data.nantesmetropole.fr/api/explore/v2.1/catalog/datasets/244400404_agenda-evenements-nantes-metropole_v2/records'),
    ],

];
