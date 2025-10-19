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
        Schema::create('game_discord_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->foreignId('discord_server_id')->constrained('discord_servers')->onDelete('cascade');
            $table->timestamp('subscribed_at')->useCurrent();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Prevent duplicate subscriptions
            $table->unique(['game_id', 'discord_server_id']);
            
            // Indexes for efficient queries
            $table->index('discord_server_id');
            $table->index('game_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_discord_subscriptions');
    }
};

