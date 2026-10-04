<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['preferred_languages', 'excluded_tags'] as $column) {
            DB::statement("UPDATE user_preferences SET {$column} = '[]'::jsonb WHERE {$column} IS NULL");
            DB::statement("ALTER TABLE user_preferences ALTER COLUMN {$column} SET DEFAULT '[]'::jsonb, ALTER COLUMN {$column} SET NOT NULL");
        }
    }

    public function down(): void
    {
        foreach (['preferred_languages', 'excluded_tags'] as $column) {
            DB::statement("ALTER TABLE user_preferences ALTER COLUMN {$column} DROP NOT NULL, ALTER COLUMN {$column} DROP DEFAULT");
        }
    }
};
