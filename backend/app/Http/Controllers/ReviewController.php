<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Venue;
use App\Services\BadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private readonly BadgeService $badges)
    {
    }

    /**
     * Avis publics d'un lieu : note moyenne + liste, du plus récent au plus ancien.
     */
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
