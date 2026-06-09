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
}
