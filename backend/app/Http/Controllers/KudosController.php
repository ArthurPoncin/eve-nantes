<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserSummaryResource;
use App\Models\Viree;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class KudosController extends Controller
{
    /**
     * Trinque (« Santé ! ») à une virée — idempotent.
     */
    #[OA\Post(
        path: '/api/v1/virees/{viree}/kudos',
        summary: 'Trinque « Santé ! » à une virée (idempotent)',
        security: [['sanctum' => []]],
        tags: ['Kudos'],
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
                response: 201,
                description: 'Santé !',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'kudos_count', type: 'integer', example: 4),
                ]),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(
                response: 404,
                description: 'Virée introuvable ou non visible',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
            new OA\Response(response: 422, description: 'On ne trinque pas à sa propre virée'),
        ],
    )]
    public function store(Request $request, Viree $viree): JsonResponse
    {
        abort_unless($viree->isVisibleTo($request->user()), 404);
        abort_if($viree->user_id === $request->user()->id, 422, 'On ne trinque pas à sa propre virée.');

        $viree->kudosGivers()->syncWithoutDetaching([$request->user()->id]);

        return response()->json(['kudos_count' => $viree->kudosGivers()->count()], 201);
    }

    /**
     * Retire son « Santé ! ».
     */
    #[OA\Delete(
        path: '/api/v1/virees/{viree}/kudos',
        summary: 'Retire son « Santé ! »',
        security: [['sanctum' => []]],
        tags: ['Kudos'],
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
            new OA\Response(response: 204, description: 'Santé retiré'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(
                response: 404,
                description: 'Virée introuvable ou non visible',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
        ],
    )]
    public function destroy(Request $request, Viree $viree): Response
    {
        abort_unless($viree->isVisibleTo($request->user()), 404);

        $viree->kudosGivers()->detach($request->user()->id);

        return response()->noContent();
    }

    /**
     * Qui a trinqué à cette virée.
     */
    #[OA\Get(
        path: '/api/v1/virees/{viree}/kudos',
        summary: 'Qui a trinqué à cette virée',
        tags: ['Kudos'],
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
                description: 'Compteur + donneurs de « Santé ! »',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'count', type: 'integer', example: 4),
                    new OA\Property(
                        property: 'users',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/UserSummary'),
                    ),
                ]),
            ),
            new OA\Response(
                response: 404,
                description: 'Virée introuvable ou non visible',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
        ],
    )]
    public function index(Viree $viree): JsonResponse
    {
        // Route publique à viewer optionnel (cf. profil public).
        abort_unless($viree->isVisibleTo(auth('sanctum')->user()), 404);

        $givers = $viree->kudosGivers()->latest('kudos.created_at')->get();

        return response()->json([
            'count' => $givers->count(),
            'users' => UserSummaryResource::collection($givers),
        ]);
    }
}
