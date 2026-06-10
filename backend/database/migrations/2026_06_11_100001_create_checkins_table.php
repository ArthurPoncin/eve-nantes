<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('viree_id')->constrained()->cascadeOnDelete();
            // user_id dénormalisé (déductible via la virée) : les critères de
            // badges et les stats comptent les check-ins par utilisateur.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->timestamp('happened_at');
            $table->timestamps();
            // Parcours d'une virée dans l'ordre de passage.
            $table->index(['viree_id', 'happened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkins');
    }
};
