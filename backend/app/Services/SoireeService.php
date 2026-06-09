<?php

namespace App\Services;

use App\Http\Resources\EventResource;
use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SoireeService
{
    public function __construct(
        private readonly WeatherService $weather,
        private readonly AIService $ai,
    ) {
    }

    /**
     * Compose une suggestion de soirée pour une ambiance : un lieu (avec son
     * prochain événement), la météo du soir et une narration IA. Suggestion
     * éphémère (rien n'est persisté ici).
     *
     * @return array<string, mixed>
     *
     * @throws ModelNotFoundException si aucun lieu ne correspond à l'ambiance.
     */
    public function generate(string $mood, ?string $district = null): array
    {
        $base = Venue::query()
            ->with('nextEvent')
            ->where('mood', $mood)
            ->when($district, fn ($query, $d) => $query->where('city', $d));

        // Privilégie (au hasard) un lieu qui a un événement à venir, pour que
        // « régénérer » propose des soirées variées ; sinon n'importe quel lieu
        // de l'ambiance.
        $venue = (clone $base)
            ->whereHas('events', fn ($query) => $query
                ->where('is_published', true)
                ->where('starts_at', '>=', now()))
            ->inRandomOrder()
            ->first()
            ?? $base->inRandomOrder()->first();

        if ($venue === null) {
            throw new ModelNotFoundException('Aucun lieu pour cette ambiance.');
        }

        $weather = $this->weather->current();
        $event = $venue->nextEvent;

        $narrative = $this->ai->narrate([
            'mood' => $mood,
            'venue' => $venue->name,
            'district' => $district ?? $venue->city,
            'event' => $event?->title ?? '',
            'weather' => $weather['condition'] ?? '',
        ]);

        return [
            'mood' => $mood,
            'venue' => new VenueResource($venue),
            'event' => $event ? new EventResource($event) : null,
            'weather' => $weather,
            'narrative' => $narrative,
        ];
    }
}
