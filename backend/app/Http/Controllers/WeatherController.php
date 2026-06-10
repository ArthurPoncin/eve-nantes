<?php

namespace App\Http\Controllers;

use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class WeatherController extends Controller
{
    public function __construct(private readonly WeatherService $weather)
    {
    }

    #[OA\Get(
        path: '/api/v1/weather',
        summary: 'Météo actuelle à Nantes (OpenWeather, cache 10 min)',
        tags: ['Météo'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Conditions actuelles',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'temp', type: 'number', format: 'float', example: 14.2),
                    new OA\Property(property: 'feels_like', type: 'number', format: 'float', example: 12.8),
                    new OA\Property(property: 'condition', type: 'string', example: 'ciel dégagé'),
                    new OA\Property(property: 'icon', type: 'string', example: 'clear-night'),
                    new OA\Property(property: 'wind', type: 'number', format: 'float', example: 18.5),
                    new OA\Property(property: 'humidity', type: 'integer', example: 72),
                ]),
            ),
        ],
    )]
    public function index(): JsonResponse
    {
        return response()->json($this->weather->current());
    }
}
