<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserSummaryResource;
use App\Http\Resources\VireeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    /**
     * Recherche de noctambules par pseudo (pour les suivre).
     */
    #[OA\Get(
        path: '/api/v1/users/search',
        summary: 'Recherche de noctambules par pseudo',
        security: [['sanctum' => []]],
        tags: ['Social'],
        parameters: [
            new OA\Parameter(
                name: 'q',
                description: 'Fragment de pseudo (2 caractères minimum)',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string', minLength: 2, example: 'noct'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: '10 résultats maximum, l\'utilisateur courant exclu',
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'username', type: 'string'),
                            new OA\Property(property: 'followers_count', type: 'integer'),
                            new OA\Property(property: 'is_following', type: 'boolean'),
                        ]),
                    ),
                ]),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(
                response: 422,
                description: 'Requête trop courte',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
            ),
        ],
    )]
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:2']]);
        $me = $request->user();

        $users = User::query()
            ->where('username', 'like', '%'.$validated['q'].'%')
            ->whereKeyNot($me->id)
            ->withCount('followers')
            ->orderBy('username')
            ->limit(10)
            ->get();

        $followingIds = $me->following()->pluck('users.id')->flip();

        return response()->json([
            'data' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'username' => $user->username,
                'followers_count' => $user->followers_count,
                'is_following' => $followingIds->has($user->id),
            ]),
        ]);
    }

    /**
     * Profil public d'un noctambule : stats, abonnés, virées récentes.
     */
    #[OA\Get(
        path: '/api/v1/users/{user}',
        summary: 'Profil public d\'un noctambule',
        description: 'Jamais d\'email ici. `is_following` est null pour un visiteur anonyme.',
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
                response: 200,
                description: 'Profil public',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'username', type: 'string'),
                    new OA\Property(property: 'member_since', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'badge_count', type: 'integer'),
                    new OA\Property(property: 'followers_count', type: 'integer'),
                    new OA\Property(property: 'following_count', type: 'integer'),
                    new OA\Property(property: 'is_following', type: 'boolean', nullable: true),
                    new OA\Property(
                        property: 'stats',
                        properties: [
                            new OA\Property(property: 'virees_count', type: 'integer'),
                            new OA\Property(property: 'total_km', type: 'number', format: 'float'),
                            new OA\Property(property: 'distinct_venues', type: 'integer'),
                        ],
                        type: 'object',
                    ),
                    new OA\Property(
                        property: 'recent_virees',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/Viree'),
                    ),
                ]),
            ),
            new OA\Response(
                response: 404,
                description: 'Pseudo inconnu',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
        ],
    )]
    public function show(User $user): JsonResponse
    {
        // Route publique à viewer optionnel : auth('sanctum') donne
        // l'utilisateur connecté s'il y en a un, null pour un anonyme.
        $viewer = auth('sanctum')->user();

        $virees = $user->virees()
            ->whereNotNull('ended_at')
            ->visibleTo($viewer)
            ->latest('ended_at')
            ->get();

        $distinctVenues = \App\Models\Checkin::query()
            ->whereIn('viree_id', $virees->pluck('id'))
            ->distinct()
            ->count('venue_id');

        return response()->json([
            'username' => $user->username,
            'member_since' => $user->created_at->toIso8601String(),
            'badge_count' => $user->badges()->count(),
            'followers_count' => $user->followers()->count(),
            'following_count' => $user->following()->count(),
            'is_following' => $viewer?->isFollowing($user),
            'stats' => [
                'virees_count' => $virees->count(),
                'total_km' => round($virees->sum('distance_m') / 1000, 1),
                'distinct_venues' => $distinctVenues,
            ],
            'recent_virees' => VireeResource::collection(
                $virees->take(5)->load('checkins.venue'),
            ),
        ]);
    }

    /**
     * Les abonnés d'un noctambule.
     */
    #[OA\Get(
        path: '/api/v1/users/{user}/followers',
        summary: 'Les abonnés d\'un noctambule',
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
                response: 200,
                description: 'Du plus récent au plus ancien (100 max)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/UserSummary'),
                    ),
                ]),
            ),
            new OA\Response(
                response: 404,
                description: 'Pseudo inconnu',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
        ],
    )]
    public function followers(User $user): JsonResponse
    {
        return response()->json([
            'data' => UserSummaryResource::collection(
                $user->followers()->latest('follows.created_at')->limit(100)->get(),
            ),
        ]);
    }

    /**
     * Les abonnements d'un noctambule.
     */
    #[OA\Get(
        path: '/api/v1/users/{user}/following',
        summary: 'Les abonnements d\'un noctambule',
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
                response: 200,
                description: 'Du plus récent au plus ancien (100 max)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/UserSummary'),
                    ),
                ]),
            ),
            new OA\Response(
                response: 404,
                description: 'Pseudo inconnu',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
        ],
    )]
    public function following(User $user): JsonResponse
    {
        return response()->json([
            'data' => UserSummaryResource::collection(
                $user->following()->latest('follows.created_at')->limit(100)->get(),
            ),
        ]);
    }
}
