<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Venue;
use App\Services\BadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Review',
    description: 'Avis d\'un utilisateur sur un lieu',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 9),
        new OA\Property(property: 'username', type: 'string', example: 'noctambule44'),
        new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5, example: 4),
        new OA\Property(property: 'comment', type: 'string', maxLength: 500, nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
class ReviewController extends Controller
{
    public function __construct(private readonly BadgeService $badges)
    {
    }

    /**
     * Avis publics d'un lieu : note moyenne + liste, du plus récent au plus ancien.
     */
    #[OA\Get(
        path: '/api/v1/venues/{venue}/reviews',
        summary: 'Avis publics d\'un lieu : note moyenne + liste',
        tags: ['Avis'],
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
                description: 'Du plus récent au plus ancien ; `average: null` sans avis',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'average', type: 'number', format: 'float', nullable: true, example: 4.5),
                    new OA\Property(property: 'count', type: 'integer', example: 2),
                    new OA\Property(
                        property: 'reviews',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/Review'),
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
    public function index(Venue $venue): JsonResponse
    {
        $reviews = $venue->reviews()->with('user')->latest('id')->get();

        return response()->json([
            'average' => $reviews->isEmpty() ? null : round($reviews->avg('rating'), 1),
            'count' => $reviews->count(),
            'reviews' => $reviews->map(fn (Review $review): array => $this->present($review))->values(),
        ]);
    }

    /**
     * Poste (ou remplace) l'avis de l'utilisateur connecté sur ce lieu.
     */
    #[OA\Post(
        path: '/api/v1/venues/{venue}/reviews',
        summary: 'Poste (ou remplace) l\'avis de l\'utilisateur sur ce lieu',
        description: 'Un avis par utilisateur et par lieu : reposter remplace le précédent. '
            .'Peut débloquer le badge « critique ».',
        security: [['sanctum' => []]],
        tags: ['Avis'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['rating'],
                properties: [
                    new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5, example: 4),
                    new OA\Property(property: 'comment', type: 'string', maxLength: 500, nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Avis créé',
                content: new OA\JsonContent(ref: '#/components/schemas/Review'),
            ),
            new OA\Response(
                response: 200,
                description: 'Avis existant remplacé',
                content: new OA\JsonContent(ref: '#/components/schemas/Review'),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(
                response: 404,
                description: 'Lieu introuvable',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
            new OA\Response(
                response: 422,
                description: 'Note hors 1–5 ou commentaire trop long',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
            ),
        ],
    )]
    public function store(Request $request, Venue $venue): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $review = Review::updateOrCreate(
            ['user_id' => $request->user()->id, 'venue_id' => $venue->id],
            ['rating' => $validated['rating'], 'comment' => $validated['comment'] ?? null],
        );
        $review->setRelation('user', $request->user());

        // Poster un avis peut débloquer un badge ('critique').
        $this->badges->evaluate($request->user());

        return response()->json(
            $this->present($review),
            $review->wasRecentlyCreated ? 201 : 200,
        );
    }

    /**
     * @return array{id: int, username: string, rating: int, comment: string|null, created_at: string}
     */
    private function present(Review $review): array
    {
        return [
            'id' => $review->id,
            'username' => $review->user->username,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'created_at' => $review->created_at->toIso8601String(),
        ];
    }
}
