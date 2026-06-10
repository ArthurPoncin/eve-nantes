<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\TransportService;
use Illuminate\Http\JsonResponse;

class TransportController extends Controller
{
    public function __construct(private readonly TransportService $transport)
    {
    }

    /**
     * Arrêt TAN le plus proche du lieu + prochains passages temps réel.
     *
     * Renvoie `stop: null` quand le lieu n'a pas de coordonnées ou
     * qu'aucun arrêt n'est référencé à proximité — le front masque le bloc.
     */
    public function show(Venue $venue): JsonResponse
    {
        return response()->json(
            $this->transport->nextDepartures($venue->latitude, $venue->longitude)
        );
    }
}
