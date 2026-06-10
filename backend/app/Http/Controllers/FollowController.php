<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class FollowController extends Controller
{
    /**
     * Suivre un noctambule (idempotent, comme les favoris).
     */
    #[OA\Post(
        path: '/api/v1/users/{user}/follow',
        summary: 'Suivre un noctambule (idempotent)',
        security: [['sanctum' => []]],
        tags: ['Social'],
        parameters: [
            new OA\Parameter(
                name: 'user',
                description: 'Pseudo',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'noctambule44'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Abonnement enregistré',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'followers_count', type: 'integer', example: 3),
                    new OA\Property(property: 'is_following', type: 'boolean', example: true),
                ]),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(
                response: 404,
                description: 'Pseudo inconnu',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
            new OA\Response(response: 422, description: 'On ne se suit pas soi-même'),
        ],
    )]
    public function store(Request $request, User $user): JsonResponse
    {
        abort_if($user->is($request->user()), 422, 'On ne se suit pas soi-même.');

        $request->user()->following()->syncWithoutDetaching([$user->id]);

        return response()->json([
            'followers_count' => $user->followers()->count(),
            'is_following' => true,
        ], 201);
    }

    /**
     * Ne plus suivre un noctambule.
     */
    #[OA\Delete(
        path: '/api/v1/users/{user}/follow',
        summary: 'Ne plus suivre un noctambule',
        security: [['sanctum' => []]],
        tags: ['Social'],
        parameters: [
            new OA\Parameter(
                name: 'user',
                description: 'Pseudo',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'noctambule44'),
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Abonnement retiré'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(
                response: 404,
                description: 'Pseudo inconnu',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
        ],
    )]
    public function destroy(Request $request, User $user): Response
    {
        $request->user()->following()->detach($user->id);

        return response()->noContent();
    }
}
