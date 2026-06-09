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

    // Météo via Open-Meteo : API gratuite, sans clé. Coordonnées = Nantes.
    'open_meteo' => [
        'latitude' => env('WEATHER_LATITUDE', 47.2184),
        'longitude' => env('WEATHER_LONGITUDE', -1.5536),
    ],

    'nantes_open_data' => [
        'events_url' => env('NANTES_EVENTS_URL', 'https://data.nantesmetropole.fr/api/explore/v2.1/catalog/datasets/244400404_agenda-evenements-nantes-metropole_v2/records'),
    ],

    // Narration IA via Mistral (provider par défaut du plan, modèle free tier).
    // La clé vient de .env (gitignored) — jamais committée.
    'mistral' => [
        'key' => env('MISTRAL_API_KEY'),
        'model' => env('MISTRAL_MODEL', 'mistral-small-latest'),
        'endpoint' => env('MISTRAL_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions'),
    ],

];
