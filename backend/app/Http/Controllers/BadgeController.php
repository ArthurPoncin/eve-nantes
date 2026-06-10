<?php

namespace App\Http\Controllers;

use App\Services\BadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    public function __construct(private readonly BadgeService $badges)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->badges->forUser($request->user()));
    }
}
