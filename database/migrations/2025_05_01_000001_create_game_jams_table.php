<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_jams', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->string('url')->unique();
            $table->text('description')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->integer('submission_count')->nullable();
            $table->integer('participant_count')->nullable();
            $table->string('host')->nullable();
            $table->string('theme')->nullable();
            $table->string('logo_url')->nullable();
            $table->jsonb('optimized_logos')->nullable();
        });

        Schema::create('game_game_jam', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_jam_id')->constrained()->cascadeOnDelete();
            $table->string('ranking')->nullable(); // For storing placement info like "1st place", "Runner-up", etc.
            $table->unique(['game_id', 'game_jam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_game_jam');
        Schema::dropIfExists('game_jams');
    }
};
