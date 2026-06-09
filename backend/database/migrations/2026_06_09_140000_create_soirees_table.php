<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soirees', function (Blueprint $table): void {
            $table->id();
            // Soirée éventuellement anonyme (partagée sans compte).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mood');
            $table->json('weather_snapshot')->nullable();
            $table->text('ai_narrative')->nullable();
            $table->json('shared_with')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soirees');
    }
};
