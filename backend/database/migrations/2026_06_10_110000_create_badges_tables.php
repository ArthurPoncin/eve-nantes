<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Définitions des badges, seedées (id lisible : 'noctambule', 'fidele'…).
        Schema::create('badges', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('label');
            $table->text('description');
            $table->string('icon');
            $table->json('criteria'); // règles d'unlock évaluées par BadgeService
            $table->timestamps();
        });

        Schema::create('user_badges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('badge_id');
            $table->foreign('badge_id')->references('id')->on('badges')->cascadeOnDelete();
            $table->timestamp('unlocked_at')->useCurrent();
            $table->unique(['user_id', 'badge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
    }
};
