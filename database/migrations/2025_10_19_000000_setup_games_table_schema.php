<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Rename game_id to itch_id for clarity since we now have distinct steam_app_id
            if (Schema::hasColumn('games', 'game_id')) {
                $table->renameColumn('game_id', 'itch_id');
            }

            // Platform field to support multiple game sources
            // itch_io: Games hosted on itch.io (itch_id for API)
            // steam: Games on Steam (steam_app_id for API)
            // other: Games from other sources
            if (!Schema::hasColumn('games', 'platform')) {
                $table->enum('platform', ['itch_io', 'steam', 'other'])
                    ->nullable()
                    ->after('url')
                    ->comment('Platform where the game is hosted');
            }

            // Steam App ID for Steam games
            // Extracted from Steam store URL
            // Example: 1084640 from https://store.steampowered.com/app/1084640/...
            if (!Schema::hasColumn('games', 'steam_app_id')) {
                $table->unsignedBigInteger('steam_app_id')
                    ->nullable()
                    ->after('platform')
                    ->comment('Steam App ID for Steam games');
            }

            // Add content_type enum to categorize games
            if (!Schema::hasColumn('games', 'content_type')) {
                $table->enum('content_type', ['visual_novel', 'adjacent', 'other'])
                    ->default('visual_novel')
                    ->comment('Content type: visual_novel (listed on fvn.li), adjacent (related games), other (non-FVN)');
            }
        });

        // Convert url field from VARCHAR to JSONB to support multiple platform URLs
        // Structure: { "itch_io": "https://...", "steam": "https://...", "other": "https://..." }
        DB::statement("ALTER TABLE games ALTER COLUMN url TYPE jsonb USING CASE WHEN url IS NOT NULL THEN jsonb_build_object('itch_io', url) ELSE NULL END");

        // Add indexes if they don't exist
        DB::statement("CREATE INDEX IF NOT EXISTS games_platform_index ON games(platform)");
        DB::statement("CREATE INDEX IF NOT EXISTS games_steam_app_id_index ON games(steam_app_id)");

        // Populate existing games with platform='itch_io'
        // All existing games in the database are from itch.io
        DB::statement("UPDATE games SET platform = 'itch_io' WHERE platform IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes first
        DB::statement("DROP INDEX IF EXISTS games_platform_index");
        DB::statement("DROP INDEX IF EXISTS games_steam_app_id_index");

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'platform',
                'steam_app_id',
                'content_type',
            ]);

            if (Schema::hasColumn('games', 'itch_id')) {
                $table->renameColumn('itch_id', 'game_id');
            }
        });
    }
};

