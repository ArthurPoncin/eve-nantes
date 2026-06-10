<?php

namespace App\Http\Controllers;

use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class FavoriteController extends Controller
{
    #[OA\Get(
        path: '/api/v1/favorites',
        summary: 'Lieux favoris de l\'utilisateur, triés par nom',
        security: [['sanctum' => []]],
        tags: ['Favoris'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lieux favoris',
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/Venue'),
                    ),
                ]),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        return VenueResource::collection($request->user()->favorites()->orderBy('name')->get());
    }

    #[OA\Post(
        path: '/api/v1/venues/{venue}/favorite',
        summary: 'Ajoute un lieu aux favoris (idempotent)',
        security: [['sanctum' => []]],
        tags: ['Favoris'],
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
                response: 201,
                description: 'Lieu ajouté aux favoris',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Venue'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(
                response: 404,
                description: 'Lieu introuvable',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
        ],
    )]
    public function store(Request $request, Venue $venue): JsonResponse
    {
        $request->user()->favorites()->syncWithoutDetaching([$venue->id]);

        return (new VenueResource($venue))->response()->setStatusCode(201);
    }

    #[OA\Delete(
        path: '/api/v1/venues/{venue}/favorite',
        summary: 'Retire un lieu des favoris',
        security: [['sanctum' => []]],
        tags: ['Favoris'],
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
            new OA\Response(response: 204, description: 'Lieu retiré des favoris'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(
                response: 404,
                description: 'Lieu introuvable',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
        ],
    )]
    public function destroy(Request $request, Venue $venue): Response
    {
        $request->user()->favorites()->detach($venue->id);

        return response()->noContent();
    }
}
