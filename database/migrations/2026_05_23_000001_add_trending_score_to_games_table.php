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
            $table->unsignedInteger('trending_score')->default(0)->after('rating_count');
            $table->timestamp('trending_score_calculated_at')->nullable()->after('trending_score');

            $table->index(['is_visible', 'trending_score'], 'games_visible_trending_score_index');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex('games_visible_trending_score_index');
            $table->dropColumn(['trending_score', 'trending_score_calculated_at']);
        });
    }
};
