<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class StatsController extends Controller
{
    public function __construct(private readonly StatsService $stats)
    {
    }

    /**
     * Le « Wrapped » nocturne de l'utilisateur : toutes ses stats en un appel.
     */
    #[OA\Get(
        path: '/api/v1/me/stats',
        summary: 'Stats de l\'utilisateur : virées, check-ins, km, ambiances, heatmap',
        security: [['sanctum' => []]],
        tags: ['Stats'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Agrégats calculés à la volée',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'virees_count', type: 'integer', example: 12),
                    new OA\Property(property: 'checkins_count', type: 'integer', example: 47),
                    new OA\Property(property: 'distinct_venues', type: 'integer', example: 9),
                    new OA\Property(property: 'total_km', type: 'number', format: 'float', example: 23.4),
                    new OA\Property(property: 'streak_weeks', type: 'integer', example: 3),
                    new OA\Property(property: 'dominant_mood', type: 'string', nullable: true, example: 'festif'),
                    new OA\Property(
                        property: 'moods',
                        type: 'array',
                        items: new OA\Items(properties: [
                            new OA\Property(property: 'mood', type: 'string', example: 'festif'),
                            new OA\Property(property: 'count', type: 'integer', example: 18),
                        ]),
                    ),
                    new OA\Property(
                        property: 'favorite_venue',
                        nullable: true,
                        properties: [
                            new OA\Property(property: 'slug', type: 'string', example: 'le-chat-noir'),
                            new OA\Property(property: 'name', type: 'string', example: 'Le Chat Noir'),
                            new OA\Property(property: 'checkins_count', type: 'integer', example: 9),
                        ],
                        type: 'object',
                    ),
                    new OA\Property(
                        property: 'heatmap',
                        description: 'Lieux visités, pondérés par nombre de check-ins',
                        type: 'array',
                        items: new OA\Items(properties: [
                            new OA\Property(property: 'slug', type: 'string'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'latitude', type: 'number', format: 'float'),
                            new OA\Property(property: 'longitude', type: 'number', format: 'float'),
                            new OA\Property(property: 'checkins_count', type: 'integer'),
                        ]),
                    ),
                ]),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->stats->forUser($request->user()));
    }
}
