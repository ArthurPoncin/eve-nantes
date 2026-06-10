<?php

namespace App\Http\Controllers;

use App\Http\Resources\VireeResource;
use App\Models\Venue;
use App\Services\BadgeService;
use App\Services\ChallengeService;
use App\Services\VireeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CheckinController extends Controller
{
    public function __construct(
        private readonly VireeService $virees,
        private readonly BadgeService $badges,
        private readonly ChallengeService $challenges,
    ) {
    }

    /**
     * Check-in « J'y suis » : rejoint la virée active ou en démarre une.
     */
    #[OA\Post(
        path: '/api/v1/venues/{venue}/checkin',
        summary: 'Check-in « J\'y suis » : rejoint la virée active ou en démarre une',
        description: 'Le premier check-in de la nuit crée la virée (démarrage implicite). '
            .'Un check-in consécutif au même lieu est ignoré. Une virée inactive depuis '
            .'plus de 6 h est clôturée automatiquement avant le check-in.',
        security: [['sanctum' => []]],
        tags: ['Virées'],
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
                response: 201,
                description: 'Check-in enregistré, virée active retournée',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Viree'),
                ]),
            ),
            new OA\Response(
                response: 200,
                description: 'Check-in consécutif au même lieu : rien de nouveau',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Viree'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(
                response: 404,
                description: 'Lieu introuvable',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
        ],
    )]
    public function store(Request $request, Venue $venue): JsonResponse
    {
        ['viree' => $viree, 'created' => $created] = $this->virees->checkIn($request->user(), $venue);

        // Un check-in peut débloquer un badge (« habitué ») ou faire avancer
        // un défi du mois.
        $this->badges->evaluate($request->user());
        $this->challenges->evaluate($request->user());

        return response()->json(
            ['data' => new VireeResource($viree)],
            $created ? 201 : 200,
        );
    }
}
