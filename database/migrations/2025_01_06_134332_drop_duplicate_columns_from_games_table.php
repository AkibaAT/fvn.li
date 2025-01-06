<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'devlog',
                'rating',
                'rating_count',
                'is_windows',
                'is_linux',
                'is_mac',
                'is_android',
                'is_web',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('devlog', 250)->nullable();
            $table->float('rating')->nullable();
            $table->integer('rating_count')->nullable();
            $table->boolean('is_windows')->default(false);
            $table->boolean('is_linux')->default(false);
            $table->boolean('is_mac')->default(false);
            $table->boolean('is_android')->default(false);
            $table->boolean('is_web')->default(false);
        });

        // Restore data from latest versions to games
        DB::statement('
            UPDATE games
            SET
                devlog = game_versions.devlog,
                rating = game_versions.rating,
                rating_count = game_versions.rating_count,
                is_windows = game_versions.is_windows,
                is_linux = game_versions.is_linux,
                is_mac = game_versions.is_mac,
                is_android = game_versions.is_android,
                is_web = game_versions.is_web
            FROM game_versions
            WHERE games.id = game_versions.game_id
            AND game_versions.is_latest = true
        ');
    }
};
