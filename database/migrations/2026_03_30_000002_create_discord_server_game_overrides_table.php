<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_server_game_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discord_server_id')->constrained('discord_servers')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_ignored')->default(false);
            $table->string('channel_id')->nullable();
            $table->jsonb('new_game_embed')->nullable();
            $table->jsonb('update_embed')->nullable();
            $table->timestamps();

            $table->unique(['discord_server_id', 'game_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_server_game_overrides');
    }
};
