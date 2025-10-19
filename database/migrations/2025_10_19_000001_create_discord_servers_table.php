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
        Schema::create('discord_servers', function (Blueprint $table) {
            $table->id();
            $table->string('discord_server_id')->unique();
            $table->string('discord_server_name');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamp('bot_joined_at')->nullable();
            $table->timestamps();

            $table->index('discord_server_id');
            $table->index('owner_user_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discord_servers');
    }
};

