<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('game_rater');
    }

    public function down(): void
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
};
