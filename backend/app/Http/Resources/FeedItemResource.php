<?php

namespace App\Http\Resources;

use App\Models\Checkin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'FeedItem',
    description: 'Une virée bouclée dans le fil des noctambules suivis',
    properties: [
        new OA\Property(property: 'public_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'is_public', type: 'boolean'),
        new OA\Property(property: 'user', ref: '#/components/schemas/UserSummary'),
        new OA\Property(property: 'ended_at', type: 'string', format: 'date-time'),
        new OA\Property(
            property: 'stats',
            properties: [
                new OA\Property(property: 'venues', type: 'integer', example: 3),
                new OA\Property(property: 'distance_m', type: 'integer', nullable: true),
                new OA\Property(property: 'duration_min', type: 'integer'),
                new OA\Property(property: 'moods', type: 'array', items: new OA\Items(type: 'string')),
            ],
            type: 'object',
        ),
        new OA\Property(property: 'narrative_snippet', type: 'string', nullable: true),
        new OA\Property(property: 'kudos_count', type: 'integer', example: 4),
        new OA\Property(property: 'has_kudoed', type: 'boolean'),
    ],
)]
class FeedItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $checkins = $this->whenLoaded('checkins', fn () => $this->checkins, collect());

        return [
            'public_id' => $this->public_id,
            'is_public' => $this->is_public,
            'user' => new UserSummaryResource($this->user),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'stats' => [
                'venues' => $checkins->pluck('venue_id')->unique()->count(),
                'distance_m' => $this->distance_m,
                'duration_min' => (int) $this->started_at->diffInMinutes($this->ended_at ?? now()),
                'moods' => $checkins
                    ->map(fn (Checkin $checkin): ?string => $checkin->venue?->mood)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ],
            'narrative_snippet' => $this->ai_narrative ? Str::limit($this->ai_narrative, 180) : null,
            'kudos_count' => (int) ($this->kudos_count ?? 0),
            'has_kudoed' => (bool) ($this->has_kudoed ?? false),
        ];
    }
}
