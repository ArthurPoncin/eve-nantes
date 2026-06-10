<?php

namespace App\Http\Controllers;

use App\Models\Soiree;
use App\Models\Venue;
use App\Services\BadgeService;
use App\Services\MailService;
use App\Services\SoireeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class SoireeController extends Controller
{
    public function __construct(private readonly SoireeService $soirees)
    {
    }

    /**
     * Génère une suggestion de soirée à partir d'une ambiance (et d'un quartier
     * optionnel). Public — pas besoin d'être connecté pour explorer.
     */
    #[OA\Post(
        path: '/api/v1/soiree/generate',
        summary: 'Compose une soirée : lieu + événement + météo + narration IA',
        tags: ['Soirées'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['mood'],
                properties: [
                    new OA\Property(
                        property: 'mood',
                        type: 'string',
                        enum: ['festif', 'chill', 'decouverte', 'afterwork'],
                    ),
                    new OA\Property(
                        property: 'district',
                        description: 'Quartier ou commune (optionnel)',
                        type: 'string',
                        maxLength: 120,
                        nullable: true,
                        example: 'Nantes',
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Suggestion éphémère (rien n\'est persisté)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'mood', type: 'string', example: 'festif'),
                    new OA\Property(property: 'venue', ref: '#/components/schemas/Venue'),
                    new OA\Property(property: 'event', ref: '#/components/schemas/Event', nullable: true),
                    new OA\Property(
                        property: 'weather',
                        description: 'Snapshot météo (même forme que GET /api/v1/weather)',
                        type: 'object',
                    ),
                    new OA\Property(
                        property: 'narrative',
                        description: 'Narration générée par Mistral',
                        type: 'string',
                        example: 'Ce soir, cap sur le Hangar à Bananes…',
                    ),
                ]),
            ),
            new OA\Response(
                response: 404,
                description: 'Aucun lieu pour cette ambiance',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFound'),
            ),
            new OA\Response(
                response: 422,
                description: 'Ambiance manquante ou inconnue',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
            ),
        ],
    )]
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mood' => ['required', Rule::in(Venue::MOODS)],
            'district' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        return response()->json(
            $this->soirees->generate($validated['mood'], $validated['district'] ?? null)
        );
    }

    /**
     * Persiste une soirée et l'envoie par email (Resend). Public + throttlé.
     */
    #[OA\Post(
        path: '/api/v1/soiree/share',
        summary: 'Partage une soirée par email (Resend)',
        description: 'Public mais throttlé (10 req/min). Si un token Sanctum accompagne la requête, '
            .'la soirée est rattachée à l\'utilisateur et peut débloquer des badges.',
        tags: ['Soirées'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'mood', 'venue_id', 'narrative'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(
                        property: 'mood',
                        type: 'string',
                        enum: ['festif', 'chill', 'decouverte', 'afterwork'],
                    ),
                    new OA\Property(property: 'venue_id', type: 'integer', example: 1),
                    new OA\Property(property: 'event_id', type: 'integer', nullable: true, example: 42),
                    new OA\Property(property: 'narrative', type: 'string', maxLength: 600),
                    new OA\Property(property: 'weather', description: 'Snapshot météo (optionnel)', type: 'object'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'Email envoyé, soirée persistée',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'sent'),
                    new OA\Property(property: 'id', type: 'integer', example: 12),
                ]),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation échouée',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
            ),
            new OA\Response(response: 429, description: 'Trop de partages (throttle 10/min)'),
            new OA\Response(
                response: 502,
                description: 'L\'email n\'a pas pu être envoyé',
                content: new OA\JsonContent(ref: '#/components/schemas/Error'),
            ),
        ],
    )]
    public function share(Request $request, MailService $mail, BadgeService $badges): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'mood' => ['required', Rule::in(Venue::MOODS)],
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'narrative' => ['required', 'string', 'max:600'],
            'weather' => ['sometimes', 'array'],
        ]);

        // Route publique : on rattache quand même l'utilisateur si un token
        // Sanctum accompagne la requête (la garde par défaut ne le résout pas).
        $user = $request->user('sanctum');

        $soiree = Soiree::create([
            'user_id' => $user?->id,
            'venue_id' => $validated['venue_id'],
            'event_id' => $validated['event_id'] ?? null,
            'mood' => $validated['mood'],
            'ai_narrative' => $validated['narrative'],
            'weather_snapshot' => $validated['weather'] ?? null,
            'shared_with' => [$validated['email']],
        ]);
        $soiree->load(['venue', 'event']);

        // Chaque soirée partagée peut débloquer un badge (noctambule, fidèle…).
        if ($user !== null) {
            $badges->evaluate($user);
        }

        if (! $mail->shareSoiree($soiree, $validated['email'])) {
            return response()->json(
                ['error' => "L'email n'a pas pu être envoyé.", 'code' => 'MAIL_FAILED'],
                502,
            );
        }

        return response()->json(['status' => 'sent', 'id' => $soiree->id], 202);
    }
}
