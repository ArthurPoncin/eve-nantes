<?php

namespace App\Http\Controllers;

use App\Services\BadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Badge',
    description: 'Badge de gamification, débloqué ou non pour l\'utilisateur',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'critique'),
        new OA\Property(property: 'label', type: 'string', example: 'Critique'),
        new OA\Property(property: 'description', type: 'string', example: 'Poster son premier avis'),
        new OA\Property(property: 'icon', type: 'string', example: '☆'),
        new OA\Property(property: 'unlocked', type: 'boolean', example: true),
        new OA\Property(property: 'unlocked_at', type: 'string', format: 'date-time', nullable: true),
    ],
)]
class BadgeController extends Controller
{
    public function __construct(private readonly BadgeService $badges)
    {
    }

    #[OA\Get(
        path: '/api/v1/badges',
        summary: 'Tous les badges, avec leur statut pour l\'utilisateur connecté',
        security: [['sanctum' => []]],
        tags: ['Badges'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Badges triés par id',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Badge'),
                ),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->badges->forUser($request->user()));
    }
}
