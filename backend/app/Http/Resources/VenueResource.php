<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Venue',
    description: 'Lieu nocturne nantais',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Le Chat Noir'),
        new OA\Property(property: 'slug', type: 'string', example: 'le-chat-noir'),
        new OA\Property(property: 'address_line', type: 'string', example: '13 Allée Duguay Trouin'),
        new OA\Property(property: 'postal_code', type: 'string', example: '44000'),
        new OA\Property(property: 'city', type: 'string', example: 'Nantes'),
        new OA\Property(property: 'mood', type: 'string', enum: ['festif', 'chill', 'decouverte', 'afterwork']),
        new OA\Property(property: 'capacity', type: 'integer', nullable: true, example: 150),
        new OA\Property(property: 'latitude', type: 'number', format: 'float', nullable: true, example: 47.2129),
        new OA\Property(property: 'longitude', type: 'number', format: 'float', nullable: true, example: -1.5603),
        new OA\Property(
            property: 'next_event',
            description: 'Prochain événement publié (présent sur la liste des lieux)',
            ref: '#/components/schemas/Event',
            nullable: true,
        ),
        new OA\Property(
            property: 'events',
            description: 'Événements publiés à venir (présent sur le détail d\'un lieu)',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Event'),
        ),
    ],
)]
class VenueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'address_line' => $this->address_line,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'mood' => $this->mood,
            'capacity' => $this->capacity,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'next_event' => $this->whenLoaded(
                'nextEvent',
                fn () => $this->nextEvent ? new EventResource($this->nextEvent) : null
            ),
            'events' => EventResource::collection($this->whenLoaded('events')),
        ];
    }
}
