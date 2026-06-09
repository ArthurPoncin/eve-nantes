<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            // Ambiance NOCTAMBULE : festif | chill | decouverte | afterwork (aligné sur les tokens front).
            $table->string('mood')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->dropColumn('mood');
        });
    }
};
