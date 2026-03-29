<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('version_route_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->onDelete('cascade');
            $table->string('ending_label');
            $table->json('path_labels');
            $table->unsignedInteger('step_count');
            $table->unsignedInteger('word_count');
            $table->unsignedInteger('choice_count');
            $table->json('choices')->nullable();
            $table->timestamps();

            $table->unique(['game_version_id', 'ending_label']);
            $table->index('game_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('version_route_paths');
    }
};
