<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'NOCTAMBULE API',
    description: 'API de la plateforme événementielle nocturne nantaise : lieux, événements, '
        .'composition de soirées avec narration IA, météo, transports TAN, avis et badges.',
)]
#[OA\Server(url: 'http://localhost:8000', description: 'Développement')]
#[OA\Server(url: 'https://noctambule.zespri.duckdns.org', description: 'Production')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    description: 'Token personnel Sanctum obtenu via /api/v1/auth/register ou /api/v1/auth/login.',
)]
#[OA\Schema(
    schema: 'Error',
    description: 'Erreur applicative',
    properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Identifiants invalides'),
        new OA\Property(property: 'code', type: 'string', example: 'INVALID_CREDENTIALS'),
    ],
)]
#[OA\Schema(
    schema: 'ValidationError',
    description: 'Erreur de validation Laravel (422)',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The rating field is required.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
        ),
    ],
)]
#[OA\Schema(
    schema: 'NotFound',
    description: 'Ressource introuvable (404)',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'No query results for model.'),
    ],
)]
abstract class Controller
{
    //
}
