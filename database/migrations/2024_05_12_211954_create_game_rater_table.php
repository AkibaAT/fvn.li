<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_rater', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rater_id')->constrained()->cascadeOnDelete();
            $table->index(['game_id']);
            $table->index(['rater_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games_raters');
    }
};
