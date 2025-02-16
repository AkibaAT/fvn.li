<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add indexes
        DB::statement('CREATE INDEX idx_ratings_visible_rater ON ratings (is_visible, rater_id)');
        DB::statement('CREATE INDEX idx_ratings_rater_processed ON ratings (rater_id, processed_at)');
        DB::statement('CREATE INDEX idx_raters_weight_calc ON raters (weight_calculated_at)');
        DB::statement('CREATE INDEX idx_ratings_visible_only ON ratings (rater_id, rating) WHERE is_visible = true');
    }

    public function down(): void
    {
        // Remove indexes
        DB::statement('DROP INDEX IF EXISTS idx_ratings_visible_rater');
        DB::statement('DROP INDEX IF EXISTS idx_ratings_rater_processed');
        DB::statement('DROP INDEX IF EXISTS idx_raters_weight_calc');
        DB::statement('DROP INDEX IF EXISTS idx_ratings_visible_only');
    }
};
