<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discord_servers', function (Blueprint $table) {
            $table->jsonb('available_channels')->nullable();
            $table->timestamp('channels_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('discord_servers', function (Blueprint $table) {
            $table->dropColumn(['available_channels', 'channels_synced_at']);
        });
    }
};
