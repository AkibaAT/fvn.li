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
        Schema::create('discord_notification_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discord_server_id')->constrained('discord_servers')->onDelete('cascade');
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->enum('notification_type', ['update', 'new_game', 'rating_change', 'manual'])->default('update');
            $table->string('message_id')->nullable();
            $table->string('channel_id');
            $table->timestamp('sent_at')->useCurrent();
            $table->enum('delivery_status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Indexes for efficient queries
            $table->index('discord_server_id');
            $table->index('game_id');
            $table->index('delivery_status');
            $table->index('sent_at');
            $table->index(['discord_server_id', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discord_notification_history');
    }
};

