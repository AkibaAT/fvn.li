<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discord_server_configs', function (Blueprint $table) {
            $table->jsonb('routing_rules')->default('[]');
            $table->jsonb('new_game_embed')->nullable();
            $table->jsonb('update_embed')->nullable();
            $table->boolean('use_embeds')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('discord_server_configs', function (Blueprint $table) {
            $table->dropColumn(['routing_rules', 'new_game_embed', 'update_embed', 'use_embeds']);
        });
    }
};
