<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('discord_server_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discord_server_id')->constrained('discord_servers')->onDelete('cascade');
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');

            // Discord-specific data for this game in this server
            $table->string('discord_channel_id')->nullable();
            $table->string('discord_message_id')->nullable();

            // Discord user ratings (separate from fvn.li star ratings)
            $table->jsonb('discord_likes')->default('[]');
            $table->jsonb('discord_dislikes')->default('[]');

            // Game metadata from fvn-bot
            $table->jsonb('abbreviations')->default('[]');
            $table->jsonb('discord_tags')->default('[]');

            // Timestamp for last Discord update
            $table->timestamp('discord_updated_at')->nullable();

            $table->timestamps();

            // Prevent duplicate server-game combinations
            $table->unique(['discord_server_id', 'game_id']);

            // Indexes for efficient queries
            $table->index('discord_channel_id');
            $table->index('discord_message_id');
            $table->index('discord_server_id');
            $table->index('game_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discord_server_games');
    }
};

