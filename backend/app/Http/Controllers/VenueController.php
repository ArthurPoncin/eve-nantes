<?php

namespace App\Http\Controllers;

use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class VenueController extends Controller
{
    /**
     * Liste les lieux (venues), triés par nom.
     *
     * Filtre optionnel via le paramètre de requête `mood`.
     */
    #[OA\Get(
        path: '/api/v1/venues',
        summary: 'Liste des lieux, triés par nom',
        tags: ['Lieux'],
        parameters: [
            new OA\Parameter(
                name: 'mood',
                description: 'Filtre par ambiance',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['festif', 'chill', 'decouverte', 'afterwork']),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lieux (avec leur prochain événement publié)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/Venue'),
                    ),
                ]),
            ),
            new OA\Response(
                response: 422,
                description: 'Ambiance inconnue',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
            ),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'mood' => ['sometimes', Rule::in(Venue::MOODS)],
        ]);

        return VenueResource::collection(
            Venue::query()
                ->with('nextEvent')
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
    #[OA\Get(
        path: '/api/v1/venues/{venue}',
        summary: 'Détail d\'un lieu avec ses événements à venir',
        tags: ['Lieux'],
        parameters: [
            new OA\Parameter(
                name: 'venue',
                description: 'Slug du lieu',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'le-chat-noir'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lieu avec ses événements publiés à venir',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Venue'),
                ]),
            ),
            new OA\Response(
                response: 404,
                description: 'Lieu introuvable',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
        ],
    )]
    public function show(Venue $venue): VenueResource
    {
        $venue->load(['events' => fn ($query) => $query
            ->where('is_published', true)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')]);

        return new VenueResource($venue);
    }
}
