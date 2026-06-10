<?php

namespace App\Http\Controllers;

use App\Http\Resources\FeedItemResource;
use App\Models\Viree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class FeedController extends Controller
{
    /**
     * Le fil : les virées bouclées des noctambules suivis (et les miennes).
     */
    #[OA\Get(
        path: '/api/v1/feed',
        summary: 'Le fil : virées bouclées des noctambules suivis (et les miennes)',
        description: 'Pagination par curseur : suivre `meta.next_cursor` tant qu\'il est non nul.',
        security: [['sanctum' => []]],
        tags: ['Fil'],
        parameters: [
            new OA\Parameter(
                name: 'cursor',
                description: 'Curseur opaque renvoyé par la page précédente',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: '10 virées par page, des plus récentes aux plus anciennes',
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/FeedItem'),
                    ),
                    new OA\Property(
                        property: 'meta',
                        properties: [
                            new OA\Property(property: 'next_cursor', type: 'string', nullable: true),
                            new OA\Property(property: 'per_page', type: 'integer', example: 10),
                        ],
                        type: 'object',
                    ),
                ]),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        // Mes virées comptent aussi : un compte tout neuf voit déjà son fil vivre.
        $authorIds = $user->following()->pluck('users.id')->push($user->id);

        $page = Viree::query()
            ->whereIn('user_id', $authorIds)
            ->whereNotNull('ended_at')
            ->with(['user', 'checkins.venue'])
            ->withCount('kudosGivers as kudos_count')
            ->withExists(['kudosGivers as has_kudoed' => fn ($query) => $query->whereKey($user->id)])
            // Tiebreaker id : requis par cursorPaginate pour un ordre total.
            ->orderByDesc('ended_at')
            ->orderByDesc('id')
            ->cursorPaginate(10);

        return FeedItemResource::collection($page);
    }
}
