<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS games_name_trgm');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS games_authors_trgm');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS games_custom_tags_trgm');
        DB::statement('CREATE INDEX CONCURRENTLY games_name_trgm ON games USING gin ((name::text) gin_trgm_ops) WHERE is_visible');
        DB::statement("CREATE INDEX CONCURRENTLY games_authors_trgm ON games USING gin ((regexp_replace(authors::text, '<[^>]*>', '', 'g')) gin_trgm_ops) WHERE is_visible");
        DB::statement('CREATE INDEX CONCURRENTLY games_custom_tags_trgm ON games USING gin ((custom_tags::text) gin_trgm_ops) WHERE is_visible');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS games_custom_tags_trgm');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS games_authors_trgm');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS games_name_trgm');
        DB::statement('CREATE INDEX CONCURRENTLY games_name_trgm ON games USING gin ((name::text) gin_trgm_ops)');
        DB::statement('CREATE INDEX CONCURRENTLY games_authors_trgm ON games USING gin ((authors::text) gin_trgm_ops)');
    }
};
