<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create helper function for generating unique slugs
        DB::statement("
            CREATE OR REPLACE FUNCTION generate_unique_slug(p_name TEXT, p_id BIGINT)
            RETURNS TEXT AS $$
            DECLARE
                base_slug TEXT;
                new_slug TEXT;
                counter INTEGER;
            BEGIN
                -- Create base slug from name
                base_slug := LOWER(REGEXP_REPLACE(p_name, '[^a-zA-Z0-9]+', '-', 'g'));
                -- Remove leading/trailing hyphens
                base_slug := TRIM(BOTH '-' FROM base_slug);

                -- Start with base slug
                new_slug := base_slug;
                counter := 1;

                -- Keep trying with incrementing numbers until we find a unique slug
                WHILE EXISTS (
                    SELECT 1 FROM games
                    WHERE slug = new_slug
                    AND id != p_id
                ) LOOP
                    new_slug := base_slug || '-' || counter;
                    counter := counter + 1;
                END LOOP;

                RETURN new_slug;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Now fix all games with NULL slugs
        DB::statement('
            UPDATE games
            SET slug = generate_unique_slug(name, id)
            WHERE slug IS NULL AND name IS NOT NULL
        ');

        // Add the NOT NULL constraint now that all games have slugs
        DB::statement('ALTER TABLE games ALTER COLUMN slug SET NOT NULL');
    }

    public function down(): void
    {
        // Remove the NOT NULL constraint
        DB::statement('ALTER TABLE games ALTER COLUMN slug DROP NOT NULL');

        // Drop the helper function
        DB::statement('DROP FUNCTION IF EXISTS generate_unique_slug(TEXT, BIGINT)');
    }
};
