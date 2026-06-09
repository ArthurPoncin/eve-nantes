<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
