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
        Schema::create('discord_server_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discord_server_id')->constrained('discord_servers')->onDelete('cascade');
            $table->string('notification_channel_id')->nullable();
            $table->enum('notification_format', ['compact', 'detailed', 'custom'])->default('detailed');
            $table->text('custom_template')->nullable();
            $table->boolean('include_game_description')->default(true);
            $table->boolean('include_thumbnail')->default(true);
            $table->boolean('include_ratings')->default(true);
            $table->string('ping_role_id')->nullable();
            $table->timestamps();

            $table->unique('discord_server_id');
            $table->index('notification_channel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discord_server_configs');
    }
};

