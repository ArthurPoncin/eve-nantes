<?php

namespace App\Http\Controllers;

use App\Http\Resources\VireeResource;
use App\Models\Viree;
use App\Services\BadgeService;
use App\Services\ChallengeService;
use App\Services\VireeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class VireeController extends Controller
{
    public function __construct(
        private readonly VireeService $virees,
        private readonly BadgeService $badges,
        private readonly ChallengeService $challenges,
    ) {
    }

    /**
     * La virée en cours de l'utilisateur, ou null.
     */
    #[OA\Get(
        path: '/api/v1/virees/current',
        summary: 'La virée en cours de l\'utilisateur, ou null',
        security: [['sanctum' => []]],
        tags: ['Virées'],
        responses: [
            new OA\Response(
                response: 200,
                description: '`data: null` si aucune virée en cours',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Viree', nullable: true),
                ]),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function current(Request $request): JsonResponse
    {
        $viree = $this->virees->current($request->user());

        return response()->json([
            'data' => $viree ? new VireeResource($viree) : null,
        ]);
    }

    /**
     * Clôture la virée en cours : distance, météo et narration IA.
     */
    #[OA\Post(
        path: '/api/v1/virees/current/close',
        summary: 'Clôture la virée en cours : distance, météo et narration IA',
        security: [['sanctum' => []]],
        tags: ['Virées'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Récap complet de la virée clôturée',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Viree'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(
                response: 404,
                description: 'Aucune virée en cours',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
        ],
    )]
    public function close(Request $request): JsonResponse
    {
        $viree = $request->user()->virees()->active()->first();
        abort_if($viree === null, 404, 'Aucune virée en cours.');

        $viree = $this->virees->close($viree);

        // Boucler une virée peut débloquer un badge (« arpenteur ») ou faire
        // avancer un défi du mois.
        $this->badges->evaluate($request->user());
        $this->challenges->evaluate($request->user());

        return response()->json(['data' => new VireeResource($viree)]);
    }

    /**
     * Les virées terminées de l'utilisateur, des plus récentes aux plus anciennes.
     */
    #[OA\Get(
        path: '/api/v1/virees',
        summary: 'Les virées terminées de l\'utilisateur, les plus récentes d\'abord',
        security: [['sanctum' => []]],
        tags: ['Virées'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Historique des virées clôturées',
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/Viree'),
                    ),
                ]),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $virees = $request->user()->virees()
            ->whereNotNull('ended_at')
            ->with('checkins.venue')
            ->latest('ended_at')
            ->get();

        return response()->json(['data' => VireeResource::collection($virees)]);
    }

    /**
     * Récap public d'une virée, par son identifiant de partage.
     */
    #[OA\Get(
        path: '/api/v1/virees/{viree}',
        summary: 'Récap public d\'une virée, par son identifiant de partage',
        tags: ['Virées'],
        parameters: [
            new OA\Parameter(
                name: 'viree',
                description: 'Identifiant public (UUID) de la virée',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Récap de la virée',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Viree'),
                ]),
            ),
            new OA\Response(
                response: 404,
                description: 'Virée introuvable',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
        ],
    )]
    public function show(Viree $viree): JsonResponse
    {
        // Route publique à viewer optionnel : une virée privée n'est visible
        // que de son auteur et de ses abonnés — 404 pour ne rien révéler.
        abort_unless($viree->isVisibleTo(auth('sanctum')->user()), 404);

        $viree->load('checkins.venue');

        return response()->json(['data' => new VireeResource($viree)]);
    }
}
