<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Défis : des objectifs de badges, mais bornés dans le temps et avec
        // une progression visible (« 3/5 »). Seedés à dates roulantes.
        Schema::create('challenges', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('label');
            $table->text('description');
            $table->string('icon');
            $table->json('criteria'); // règles évaluées par ChallengeService
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
        });

        Schema::create('user_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('challenge_id');
            $table->foreign('challenge_id')->references('id')->on('challenges')->cascadeOnDelete();
            $table->unsignedInteger('progress')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'challenge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_challenges');
        Schema::dropIfExists('challenges');
    }
};
