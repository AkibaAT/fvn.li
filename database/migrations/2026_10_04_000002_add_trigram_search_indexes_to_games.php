<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX games_name_trgm ON games USING gin ((name::text) gin_trgm_ops)');
        DB::statement('CREATE INDEX games_authors_trgm ON games USING gin ((authors::text) gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS games_authors_trgm');
        DB::statement('DROP INDEX IF EXISTS games_name_trgm');
    }
};
