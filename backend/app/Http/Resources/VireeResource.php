<?php

namespace App\Http\Resources;

use App\Models\Checkin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Viree',
    description: 'Virée nocturne : session de check-ins, façon activité Strava',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'public_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'is_public', type: 'boolean', example: true),
        new OA\Property(property: 'status', type: 'string', enum: ['en_cours', 'terminee']),
        new OA\Property(property: 'started_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'ended_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(
            property: 'stats',
            properties: [
                new OA\Property(property: 'venues', type: 'integer', example: 3),
                new OA\Property(property: 'distance_m', type: 'integer', nullable: true, example: 1840),
                new OA\Property(property: 'duration_min', type: 'integer', example: 154),
                new OA\Property(
                    property: 'moods',
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                    example: ['festif', 'chill'],
                ),
            ],
            type: 'object',
        ),
        new OA\Property(property: 'narrative', type: 'string', nullable: true),
        new OA\Property(property: 'weather', type: 'object', nullable: true),
        new OA\Property(
            property: 'checkins',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Checkin'),
        ),
    ],
)]
class VireeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $checkins = $this->whenLoaded('checkins', fn () => $this->checkins, collect());
        $endedAt = $this->ended_at ?? now();

        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'is_public' => $this->is_public,
            'status' => $this->ended_at === null ? 'en_cours' : 'terminee',
            'started_at' => $this->started_at->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'stats' => [
                'venues' => $checkins->pluck('venue_id')->unique()->count(),
                'distance_m' => $this->distance_m,
                'duration_min' => (int) $this->started_at->diffInMinutes($endedAt),
                // Ambiances dans l'ordre de passage, sans doublon.
                'moods' => $checkins
                    ->map(fn (Checkin $checkin): ?string => $checkin->venue?->mood)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ],
            'narrative' => $this->ai_narrative,
            'weather' => $this->weather_snapshot,
            'checkins' => CheckinResource::collection($checkins),
        ];
    }
}
