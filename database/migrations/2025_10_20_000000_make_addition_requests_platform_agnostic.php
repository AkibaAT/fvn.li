<?php

declare(strict_types=1);

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
        Schema::table('addition_requests', function (Blueprint $table) {
            // Rename itch_url to game_url to be platform-agnostic
            $table->renameColumn('itch_url', 'game_url');
            
            // Add platform field to track which platform the game is from
            $table->enum('platform', ['itch_io', 'steam', 'other'])
                ->nullable()
                ->after('game_url')
                ->comment('Platform where the game is hosted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addition_requests', function (Blueprint $table) {
            $table->dropColumn('platform');
            $table->renameColumn('game_url', 'itch_url');
        });
    }
};

