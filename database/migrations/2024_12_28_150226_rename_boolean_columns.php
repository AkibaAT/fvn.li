<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->renameColumn('visible', 'is_visible');
            // Platform flags
            $table->renameColumn('platform_windows', 'is_windows');
            $table->renameColumn('platform_linux', 'is_linux');
            $table->renameColumn('platform_mac', 'is_mac');
            $table->renameColumn('platform_android', 'is_android');
            $table->renameColumn('platform_web', 'is_web');
            // Content flag
            $table->renameColumn('nsfw', 'is_nsfw');
        });

        Schema::table('game_versions', function (Blueprint $table) {
            // Platform flags
            $table->renameColumn('platform_windows', 'is_windows');
            $table->renameColumn('platform_linux', 'is_linux');
            $table->renameColumn('platform_mac', 'is_mac');
            $table->renameColumn('platform_android', 'is_android');
            $table->renameColumn('platform_web', 'is_web');
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->renameColumn('visible', 'is_visible');
            $table->renameColumn('has_review', 'is_reviewed');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->renameColumn('is_visible', 'visible');
            $table->renameColumn('is_windows', 'platform_windows');
            $table->renameColumn('is_linux', 'platform_linux');
            $table->renameColumn('is_mac', 'platform_mac');
            $table->renameColumn('is_android', 'platform_android');
            $table->renameColumn('is_web', 'platform_web');
            $table->renameColumn('is_nsfw', 'nsfw');
        });

        Schema::table('game_versions', function (Blueprint $table) {
            $table->renameColumn('is_windows', 'platform_windows');
            $table->renameColumn('is_linux', 'platform_linux');
            $table->renameColumn('is_mac', 'platform_mac');
            $table->renameColumn('is_android', 'platform_android');
            $table->renameColumn('is_web', 'platform_web');
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->renameColumn('is_visible', 'visible');
            $table->renameColumn('is_reviewed', 'has_review');
        });
    }
};
