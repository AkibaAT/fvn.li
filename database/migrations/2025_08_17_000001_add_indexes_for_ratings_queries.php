<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Composite indexes to accelerate common filters and sorts
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ratings_visible_published_at ON ratings (is_visible, published_at DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ratings_visible_rating ON ratings (is_visible, rating DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ratings_visible_reviewed ON ratings (is_visible, is_reviewed)');
        // Exact-match counts for stars + visible + reviewed
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ratings_rating_when_visible_reviewed ON ratings (rating) WHERE is_visible = true AND is_reviewed = true');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ratings_game_visible ON games (is_visible)');

        // For rater page filters
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ratings_rater_visible_published_at ON ratings (rater_id, is_visible, published_at DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ratings_rater_visible_rating ON ratings (rater_id, is_visible, rating DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_ratings_visible_published_at');
        DB::statement('DROP INDEX IF EXISTS idx_ratings_visible_rating');
        DB::statement('DROP INDEX IF EXISTS idx_ratings_visible_reviewed');
        DB::statement('DROP INDEX IF EXISTS idx_ratings_rating_when_visible_reviewed');
        DB::statement('DROP INDEX IF EXISTS idx_ratings_game_visible');
        DB::statement('DROP INDEX IF EXISTS idx_ratings_rater_visible_published_at');
        DB::statement('DROP INDEX IF EXISTS idx_ratings_rater_visible_rating');
    }
};
