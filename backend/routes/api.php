<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SoireeController;
use App\Http\Controllers\TransportController;
use App\Http\Controllers\VenueController;
use App\Http\Controllers\VireeController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

// Routes API NOCTAMBULE (préfixe global /api). Ajoutées par tranche verticale.
Route::prefix('v1')->group(function (): void {
    Route::get('weather', [WeatherController::class, 'index']);
    Route::get('events', [EventController::class, 'index']);
    Route::get('venues', [VenueController::class, 'index']);
    Route::get('venues/{venue}', [VenueController::class, 'show']);
    // Prochains passages TAN à l'arrêt le plus proche du lieu (open.tan.fr).
    Route::get('venues/{venue}/transport', [TransportController::class, 'show']);
    // Avis publics d'un lieu (note moyenne + commentaires).
    Route::get('venues/{venue}/reviews', [ReviewController::class, 'index']);

    // Cœur du service : compose une soirée (lieu + event + météo + narration IA).
    Route::post('soiree/generate', [SoireeController::class, 'generate']);
    // Partage d'une soirée par email (Resend) — throttlé contre le spam.
    Route::post('soiree/share', [SoireeController::class, 'share'])->middleware('throttle:10,1');

    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::post('venues/{venue}/favorite', [FavoriteController::class, 'store']);
        Route::delete('venues/{venue}/favorite', [FavoriteController::class, 'destroy']);
        // Un avis par utilisateur et par lieu : reposter remplace le précédent.
        Route::post('venues/{venue}/reviews', [ReviewController::class, 'store']);
        // Gamification : tous les badges, débloqués ou non pour l'utilisateur.
        Route::get('badges', [BadgeController::class, 'index']);

        // Virées façon Strava : le 1er check-in de la nuit démarre la virée.
        Route::post('venues/{venue}/checkin', [CheckinController::class, 'store']);
        // Déclarée avant virees/{viree:public_id} pour que « current » prime.
        Route::get('virees/current', [VireeController::class, 'current']);
        Route::post('virees/current/close', [VireeController::class, 'close']);
        Route::get('virees', [VireeController::class, 'index']);
    });

    // Récap partageable d'une virée — public, après virees/current.
    Route::get('virees/{viree:public_id}', [VireeController::class, 'show']);
});
