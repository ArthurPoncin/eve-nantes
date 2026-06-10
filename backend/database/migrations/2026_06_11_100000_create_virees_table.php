<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une virée = la session de nuit d'un utilisateur (façon activité
        // Strava) : une suite de check-ins dans des lieux, clôturée par un
        // récap (distance, météo, narration IA).
        Schema::create('virees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Identifiant public non devinable pour le lien de partage du récap.
            $table->uuid('public_id')->unique();
            $table->boolean('is_public')->default(true);
            $table->timestamp('started_at');
            // NULL = virée en cours ; pas de colonne de statut séparée.
            $table->timestamp('ended_at')->nullable();
            // Renseignés à la clôture seulement.
            $table->unsignedInteger('distance_m')->nullable();
            $table->json('weather_snapshot')->nullable();
            $table->text('ai_narrative')->nullable();
            $table->timestamps();
            // Lookup « virée active de l'utilisateur ».
            $table->index(['user_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virees');
    }
};
