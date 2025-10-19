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
        Schema::create('discord_server_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discord_server_id')->constrained('discord_servers')->onDelete('cascade');
            $table->string('tag_name');
            $table->boolean('is_subscribed')->default(false);
            $table->timestamps();

            // Prevent duplicate tag subscriptions per server
            $table->unique(['discord_server_id', 'tag_name']);
            
            // Indexes
            $table->index('discord_server_id');
            $table->index('is_subscribed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discord_server_tags');
    }
};

