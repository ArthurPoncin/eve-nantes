<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1 à 5 étoiles
            $table->text('comment')->nullable();
            $table->timestamps();
            // Un seul avis par utilisateur et par lieu : reposter le remplace.
            $table->unique(['user_id', 'venue_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
