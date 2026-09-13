<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discord_server_members', function (Blueprint $table): void {
            $table->dropUnique('discord_server_members_discord_user_id_unique');
            $table->unique(['discord_server_id', 'discord_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('discord_server_members', function (Blueprint $table): void {
            $table->dropUnique(['discord_server_id', 'discord_user_id']);
            $table->unique('discord_user_id');
        });
    }
};
