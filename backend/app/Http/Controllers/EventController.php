<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    /**
     * Liste les événements publiés (avec lieu et catégories), triés par date de début.
     */
    public function index(): AnonymousResourceCollection
    {
        $events = Event::query()
            ->where('is_published', true)
            ->with(['venue', 'categories'])
            ->orderBy('starts_at')
            ->get();

        return EventResource::collection($events);
    }
}
