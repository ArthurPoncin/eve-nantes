<?php

namespace App\Http\Controllers;

use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;

class WeatherController extends Controller
{
    public function __construct(private readonly WeatherService $weather)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->weather->current());
    }
}
