<?php

namespace App\Http\Controllers;

use App\Services\ChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Challenge',
    description: 'Défi mensuel, avec la progression de l\'utilisateur',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'explorateur-du-mois'),
        new OA\Property(property: 'label', type: 'string', example: 'Explorateur du mois'),
        new OA\Property(property: 'description', type: 'string', example: 'Explore 5 nouveaux lieux ce mois-ci'),
        new OA\Property(property: 'icon', type: 'string', example: '◈'),
        new OA\Property(property: 'goal', type: 'integer', example: 5),
        new OA\Property(property: 'progress', type: 'integer', example: 3),
        new OA\Property(property: 'completed', type: 'boolean', example: false),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'ends_at', type: 'string', format: 'date-time'),
    ],
)]
class ChallengeController extends Controller
{
    public function __construct(private readonly ChallengeService $challenges)
    {
    }

    #[OA\Get(
        path: '/api/v1/challenges',
        summary: 'Les défis du moment, avec la progression de l\'utilisateur',
        security: [['sanctum' => []]],
        tags: ['Défis'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Défis actifs triés par id',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Challenge'),
                ),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->challenges->forUser($request->user()));
    }
}
