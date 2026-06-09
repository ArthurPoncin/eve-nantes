<?php

namespace App\Http\Controllers;

use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VenueController extends Controller
{
    /**
     * Liste les lieux (venues), triés par nom.
     *
     * Filtre optionnel via le paramètre de requête `mood`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return VenueResource::collection(
            Venue::query()
                ->when(
                    $request->query('mood'),
                    fn ($query, $mood) => $query->where('mood', $mood)
                )
                ->orderBy('name')
                ->get()
        );
    }
}
