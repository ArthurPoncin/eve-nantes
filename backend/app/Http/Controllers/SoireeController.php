<?php

namespace App\Http\Controllers;

use App\Models\Venue;
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
}
