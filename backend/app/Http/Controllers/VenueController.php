<?php

namespace App\Http\Controllers;

use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VenueController extends Controller
{
    /**
     * Liste les lieux (venues), triés par nom.
     */
    public function index(): AnonymousResourceCollection
    {
        return VenueResource::collection(
            Venue::query()->orderBy('name')->get()
        );
    }
}
