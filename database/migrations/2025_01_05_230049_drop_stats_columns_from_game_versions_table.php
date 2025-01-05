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
            // Drop old stats columns that were moved to version_language_stats
            $table->dropColumn([
                'stats_blocks',
                'stats_menus',
                'stats_options',
                'stats_words',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('game_versions', function (Blueprint $table) {
            // Restore stats columns
            $table->unsignedInteger('stats_blocks')->nullable();
            $table->unsignedInteger('stats_menus')->nullable();
            $table->unsignedInteger('stats_options')->nullable();
            $table->unsignedInteger('stats_words')->nullable();
        });
    }
};
