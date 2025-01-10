<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('
            CREATE OR REPLACE FUNCTION update_game_slug()
            RETURNS TRIGGER AS $$
            DECLARE
                base_slug TEXT;
                new_slug TEXT;
                counter INTEGER;
            BEGIN
                IF NEW.is_visible = true AND (OLD.is_visible = false OR OLD.is_visible IS NULL) AND
                   (NEW.slug IS NULL OR NEW.slug = \'\') THEN
                    -- Create base slug from name
                    base_slug := LOWER(REGEXP_REPLACE(NEW.name, \'[^a-zA-Z0-9]+\', \'-\', \'g\'));
                    -- Remove leading/trailing hyphens
                    base_slug := TRIM(BOTH \'-\' FROM base_slug);

                    -- Start with base slug
                    new_slug := base_slug;
                    counter := 1;

                    -- Keep trying with incrementing numbers until we find a unique slug
                    WHILE EXISTS (
                        SELECT 1 FROM games
                        WHERE slug = new_slug
                        AND id != NEW.id
                    ) LOOP
                        new_slug := base_slug || \'-\' || counter;
                        counter := counter + 1;
                    END LOOP;

                    NEW.slug := new_slug;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS update_game_slug_trigger ON games;

            CREATE TRIGGER update_game_slug_trigger
            BEFORE UPDATE OR INSERT ON games
            FOR EACH ROW
            EXECUTE FUNCTION update_game_slug();
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            DROP TRIGGER IF EXISTS update_game_slug_trigger ON games;
            DROP FUNCTION IF EXISTS update_game_slug;
        ');
    }
};
