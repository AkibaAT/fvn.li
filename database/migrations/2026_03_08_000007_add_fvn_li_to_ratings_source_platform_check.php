<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename legacy 'db' values to 'itch_io'
        DB::statement("UPDATE ratings SET source_platform = 'itch_io' WHERE source_platform = 'db'");

        // Remove the 'db' default
        DB::statement('ALTER TABLE ratings ALTER COLUMN source_platform DROP DEFAULT');

        // Replace constraint: remove 'db', add 'itch_io' and 'fvn_li'
        DB::statement('ALTER TABLE ratings DROP CONSTRAINT ratings_source_platform_check');
        DB::statement("ALTER TABLE ratings ADD CONSTRAINT ratings_source_platform_check CHECK (source_platform IN ('itch_io', 'steam', 'fvn_li'))");
    }

    public function down(): void
    {
        // Restore 'db' default and values
        DB::statement("ALTER TABLE ratings ALTER COLUMN source_platform SET DEFAULT 'db'");
        DB::statement("UPDATE ratings SET source_platform = 'db' WHERE source_platform = 'itch_io'");

        // Restore original constraint
        DB::statement('ALTER TABLE ratings DROP CONSTRAINT ratings_source_platform_check');
        DB::statement("ALTER TABLE ratings ADD CONSTRAINT ratings_source_platform_check CHECK (source_platform IN ('db', 'steam', 'other'))");
    }
};
