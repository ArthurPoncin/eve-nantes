<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // « Santé ! » : le kudos NOCTAMBULE, un trinquage par virée et par user.
        Schema::create('kudos', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('viree_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'viree_id']);
            $table->index('viree_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kudos');
    }
};
