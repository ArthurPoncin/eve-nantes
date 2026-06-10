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

    // Transports TAN/Naolib (tram/busway/bus nantais) : API publique, sans clé.
    // L'API JSON historique (open.tan.fr) n'est plus alimentée depuis déc. 2025
    // (remplacée par du SIRI à clé) ; le miroir v2 preprod sert encore le temps réel.
    'tan' => [
        'endpoint' => env('TAN_API_URL', 'https://openv2-preprod.tan.fr/ewp'),
    ],

    'nantes_open_data' => [
        'events_url' => env('NANTES_EVENTS_URL', 'https://data.nantesmetropole.fr/api/explore/v2.1/catalog/datasets/244400404_agenda-evenements-nantes-metropole_v2/records'),
    ],

    // Import des bars/pubs/boîtes nantais depuis OpenStreetMap (gratuit, sans
    // clé). Liste d'instances séparées par des virgules, essayées dans l'ordre
    // — l'instance principale rate-limite agressivement certaines IPs.
    'overpass' => [
        'url' => env('OVERPASS_URL', 'https://overpass-api.de/api/interpreter,https://overpass.kumi.systems/api/interpreter'),
    ],

    // Narration IA via Mistral (provider par défaut du plan, modèle free tier).
    // La clé vient de .env (gitignored) — jamais committée.
    'mistral' => [
        'key' => env('MISTRAL_API_KEY'),
        'model' => env('MISTRAL_MODEL', 'mistral-small-latest'),
        'endpoint' => env('MISTRAL_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions'),
    ],

    // Envoi d'email (partage de soirée) via Resend. Clé dans .env (gitignored).
    // En l'absence de domaine vérifié, n'utiliser que l'expéditeur de test Resend.
    'resend' => [
        'key' => env('RESEND_API_KEY'),
        'from' => env('RESEND_FROM', 'NOCTAMBULE <onboarding@resend.dev>'),
        'endpoint' => env('RESEND_ENDPOINT', 'https://api.resend.com/emails'),
    ],

    // URL du front (liens cliquables dans les emails).
    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:5173'),
    ],

];
