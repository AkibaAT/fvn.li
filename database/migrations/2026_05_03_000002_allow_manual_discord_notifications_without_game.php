<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discord_notification_history', function (Blueprint $table) {
            $table->foreignId('game_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('discord_notification_history', function (Blueprint $table) {
            $table->foreignId('game_id')->nullable(false)->change();
        });
    }
};
