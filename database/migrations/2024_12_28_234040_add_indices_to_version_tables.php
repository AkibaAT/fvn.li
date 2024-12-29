<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_versions', function (Blueprint $table) {
            // This allows quick lookup of versions by is_latest and includes game_id for the join
            $table->index(['is_latest', 'game_id', 'id'], 'idx_game_versions_latest_lookup');
        });

        Schema::table('version_language_stats', function (Blueprint $table) {
            // This supports the language stats join condition
            $table->index(['game_version_id', 'iso_code'], 'idx_version_stats_version_lang');
        });
    }

    public function down(): void
    {
        Schema::table('game_versions', function (Blueprint $table) {
            $table->dropIndex('idx_game_versions_latest_lookup');
        });

        Schema::table('version_language_stats', function (Blueprint $table) {
            $table->dropIndex('idx_version_stats_version_lang');
        });
    }
};
