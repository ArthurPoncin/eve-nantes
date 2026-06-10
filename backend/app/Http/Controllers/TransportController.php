<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\TransportService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class TransportController extends Controller
{
    public function __construct(private readonly TransportService $transport)
    {
    }

    /**
     * Arrêt TAN le plus proche du lieu + prochains passages temps réel.
     *
     * Renvoie `stop: null` quand le lieu n'a pas de coordonnées ou
     * qu'aucun arrêt n'est référencé à proximité — le front masque le bloc.
     */
    #[OA\Get(
        path: '/api/v1/venues/{venue}/transport',
        summary: 'Arrêt TAN le plus proche du lieu et prochains passages temps réel',
        tags: ['Transport'],
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
                description: 'Arrêt + passages ; `stop: null` si rien à proximité',
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: 'stop',
                        nullable: true,
                        properties: [
                            new OA\Property(property: 'code', type: 'string', example: 'CRQU'),
                            new OA\Property(property: 'name', type: 'string', example: 'Place du Cirque'),
                            new OA\Property(property: 'distance', type: 'string', nullable: true, example: '150 m'),
                        ],
                        type: 'object',
                    ),
                    new OA\Property(
                        property: 'departures',
                        type: 'array',
                        items: new OA\Items(properties: [
                            new OA\Property(property: 'line', type: 'string', example: '2'),
                            new OA\Property(property: 'type', type: 'string', example: 'tramway'),
                            new OA\Property(property: 'terminus', type: 'string', example: 'Orvault Grand Val'),
                            new OA\Property(property: 'wait', type: 'string', example: '4 min'),
                            new OA\Property(property: 'realtime', type: 'boolean', example: true),
                        ]),
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
        return response()->json(
            $this->transport->nextDepartures($venue->latitude, $venue->longitude)
        );
    }
}
