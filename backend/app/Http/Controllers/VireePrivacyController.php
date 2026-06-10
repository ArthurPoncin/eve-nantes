<?php

namespace App\Http\Controllers;

use App\Models\Viree;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class VireePrivacyController extends Controller
{
    /**
     * Rend une virée publique ou privée (réservé à son auteur).
     */
    #[OA\Patch(
        path: '/api/v1/virees/{viree}/visibility',
        summary: 'Rend une virée publique ou privée (réservé à son auteur)',
        security: [['sanctum' => []]],
        tags: ['Virées'],
        parameters: [
            new OA\Parameter(
                name: 'viree',
                description: 'Identifiant public (UUID) de la virée',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['is_public'],
                properties: [
                    new OA\Property(property: 'is_public', type: 'boolean', example: false),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Visibilité mise à jour',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'is_public', type: 'boolean', example: false),
                ]),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Pas votre virée'),
            new OA\Response(
                response: 404,
                description: 'Virée introuvable',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
            new OA\Response(
                response: 422,
                description: 'is_public manquant ou non booléen',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
            ),
        ],
    )]
    public function update(Request $request, Viree $viree): JsonResponse
    {
        abort_unless($viree->user_id === $request->user()->id, 403);

        $validated = $request->validate(['is_public' => ['required', 'boolean']]);

        $viree->update(['is_public' => $validated['is_public']]);

        return response()->json(['is_public' => $viree->is_public]);
    }
}
