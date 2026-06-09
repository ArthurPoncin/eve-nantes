<?php

namespace App\Http\Controllers;

use App\Models\Soiree;
use App\Models\Venue;
use App\Services\MailService;
use App\Services\SoireeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SoireeController extends Controller
{
    public function __construct(private readonly SoireeService $soirees)
    {
    }

    /**
     * Génère une suggestion de soirée à partir d'une ambiance (et d'un quartier
     * optionnel). Public — pas besoin d'être connecté pour explorer.
     */
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
    public function share(Request $request, MailService $mail): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'mood' => ['required', Rule::in(Venue::MOODS)],
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'narrative' => ['required', 'string', 'max:600'],
            'weather' => ['sometimes', 'array'],
        ]);

        $soiree = Soiree::create([
            'user_id' => optional($request->user())->id,
            'venue_id' => $validated['venue_id'],
            'event_id' => $validated['event_id'] ?? null,
            'mood' => $validated['mood'],
            'ai_narrative' => $validated['narrative'],
            'weather_snapshot' => $validated['weather'] ?? null,
            'shared_with' => [$validated['email']],
        ]);
        $soiree->load(['venue', 'event']);

        if (! $mail->shareSoiree($soiree, $validated['email'])) {
            return response()->json(
                ['error' => "L'email n'a pas pu être envoyé.", 'code' => 'MAIL_FAILED'],
                502,
            );
        }

        return response()->json(['status' => 'sent', 'id' => $soiree->id], 202);
    }
}
