<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Checkin',
    description: 'Étape d\'une virée : passage horodaté dans un lieu',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 12),
        new OA\Property(property: 'happened_at', type: 'string', format: 'date-time'),
        new OA\Property(
            property: 'venue',
            description: 'Lieu allégé (assez pour la carte et la timeline)',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 3),
                new OA\Property(property: 'name', type: 'string', example: 'Le Chat Noir'),
                new OA\Property(property: 'slug', type: 'string', example: 'le-chat-noir'),
                new OA\Property(property: 'mood', type: 'string', nullable: true, enum: ['festif', 'chill', 'decouverte', 'afterwork']),
                new OA\Property(property: 'latitude', type: 'number', format: 'float', nullable: true, example: 47.2129),
                new OA\Property(property: 'longitude', type: 'number', format: 'float', nullable: true, example: -1.5603),
            ],
            type: 'object',
        ),
    ],
)]
class CheckinResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'happened_at' => $this->happened_at->toIso8601String(),
            'venue' => [
                'id' => $this->venue->id,
                'name' => $this->venue->name,
                'slug' => $this->venue->slug,
                'mood' => $this->venue->mood,
                'latitude' => $this->venue->latitude,
                'longitude' => $this->venue->longitude,
            ],
        ];
    }
}
