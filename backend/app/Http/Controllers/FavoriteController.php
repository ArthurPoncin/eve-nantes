<?php

namespace App\Http\Controllers;

use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FavoriteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return VenueResource::collection($request->user()->favorites()->orderBy('name')->get());
    }

    public function store(Request $request, Venue $venue): JsonResponse
    {
        $request->user()->favorites()->syncWithoutDetaching([$venue->id]);

        return (new VenueResource($venue))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Venue $venue): Response
    {
        $request->user()->favorites()->detach($venue->id);

        return response()->noContent();
    }
}
