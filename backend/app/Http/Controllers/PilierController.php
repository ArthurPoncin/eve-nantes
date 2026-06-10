<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PilierController extends Controller
{
    public function __construct(private readonly StatsService $stats)
    {
    }

    /**
     * Le « Pilier de bar » du lieu : top check-iner des 90 derniers jours.
     */
    #[OA\Get(
        path: '/api/v1/venues/{venue}/pilier',
        summary: 'Le « Pilier de bar » : top check-iner des 90 derniers jours',
        description: 'Au moins 2 check-ins pour prétendre au trône ; égalité départagée '
            .'par le premier check-in le plus ancien. Mis en cache 60 s.',
        tags: ['Stats'],
        parameters: [
            new OA\Parameter(
                name: 'venue',
                description: 'Slug du lieu',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'le-chat-noir'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: '`pilier: null` si le trône est libre',
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: 'pilier',
                        nullable: true,
                        properties: [
                            new OA\Property(property: 'username', type: 'string', example: 'noctambule44'),
                            new OA\Property(property: 'checkins_count', type: 'integer', example: 7),
                            new OA\Property(property: 'first_checkin_at', type: 'string', format: 'date-time'),
                        ],
                        type: 'object',
                    ),
                ]),
            ),
            new OA\Response(
                response: 404,
                description: 'Lieu introuvable',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
        ],
    )]
    public function show(Venue $venue): JsonResponse
    {
        return response()->json(['pilier' => $this->stats->pilierFor($venue)]);
    }
}
