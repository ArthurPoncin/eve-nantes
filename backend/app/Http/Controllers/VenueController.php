<?php

namespace App\Http\Controllers;

use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class VenueController extends Controller
{
    /**
     * Liste les lieux (venues), triés par nom.
     *
     * Filtre optionnel via le paramètre de requête `mood`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'mood' => ['sometimes', Rule::in(Venue::MOODS)],
        ]);

        return VenueResource::collection(
            Venue::query()
                ->when(
                    $validated['mood'] ?? null,
                    fn ($query, $mood) => $query->where('mood', $mood)
                )
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * Affiche un lieu (resolu par slug) avec ses evenements publies a venir.
     */
    public function show(Venue $venue): VenueResource
    {
        $venue->load(['events' => fn ($query) => $query
            ->where('is_published', true)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')]);

        return new VenueResource($venue);
    }
}
