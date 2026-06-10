<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Event',
    description: 'Événement nocturne',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 42),
        new OA\Property(property: 'title', type: 'string', example: 'Nuit Techno'),
        new OA\Property(property: 'slug', type: 'string', example: 'nuit-techno'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'price_cents', type: 'integer', nullable: true, example: 1200),
        new OA\Property(
            property: 'venue',
            description: 'Lieu hôte (présent sur la liste des événements)',
            ref: '#/components/schemas/Venue',
            nullable: true,
        ),
        new OA\Property(
            property: 'categories',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Category'),
        ),
    ],
)]
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'price_cents' => $this->price_cents,
            'venue' => $this->whenLoaded('venue', fn () => $this->venue ? new VenueResource($this->venue) : null),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
        ];
    }
}
