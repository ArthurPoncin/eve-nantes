<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\VenueController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

// Routes API NOCTAMBULE (préfixe global /api). Ajoutées par tranche verticale.
Route::prefix('v1')->group(function (): void {
    Route::get('weather', [WeatherController::class, 'index']);
    Route::get('events', [EventController::class, 'index']);
    Route::get('venues', [VenueController::class, 'index']);
});
