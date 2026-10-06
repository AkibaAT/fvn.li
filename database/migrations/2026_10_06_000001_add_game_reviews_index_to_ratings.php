<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX ratings_game_reviews_published_at_index ON ratings (game_id, published_at DESC) WHERE is_visible = true AND is_reviewed = true');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ratings_game_reviews_published_at_index');
    }
};
