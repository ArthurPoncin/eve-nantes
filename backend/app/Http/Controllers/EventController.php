<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class EventController extends Controller
{
    /**
     * Liste les événements publiés (avec lieu et catégories), triés par date de début.
     */
    #[OA\Get(
        path: '/api/v1/events',
        summary: 'Liste des événements publiés, triés par date de début',
        tags: ['Événements'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Événements avec lieu et catégories',
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/Event'),
                    ),
                ]),
            ),
        ],
    )]
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
