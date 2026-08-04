<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
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

                -- Names with no ASCII slug characters still need a stable, unique slug
                IF base_slug IS NULL OR base_slug = '' THEN
                    base_slug := 'game-' || p_id::TEXT;
                END IF;

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
        SQL);
    }

    public function down(): void
    {
        // The function is referenced by slug backfills, so it stays in place.
    }
};
