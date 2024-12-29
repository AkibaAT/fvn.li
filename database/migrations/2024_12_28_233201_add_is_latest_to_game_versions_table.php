<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add is_latest column
        Schema::table('game_versions', function (Blueprint $table) {
            $table->boolean('is_latest')->default(false);
            $table->index(['game_id', 'is_latest']);
        });

        // Create function to update is_latest flag
        DB::statement('
            CREATE OR REPLACE FUNCTION update_game_version_latest_flag()
            RETURNS TRIGGER AS $$
            DECLARE
                current_latest_id bigint;
                new_latest_id bigint;
            BEGIN
                -- For INSERT or UPDATE
                IF (TG_OP = \'INSERT\') OR (TG_OP = \'UPDATE\' AND OLD.published_at <> NEW.published_at) THEN
                    -- Get current latest version for this game
                    SELECT id INTO current_latest_id
                    FROM game_versions
                    WHERE game_id = NEW.game_id AND is_latest = true;

                    -- Find what should be the latest version
                    SELECT id INTO new_latest_id
                    FROM game_versions
                    WHERE game_id = NEW.game_id
                    ORDER BY published_at DESC
                    LIMIT 1;

                    -- Only update if there\'s a change in which version is latest
                    IF COALESCE(current_latest_id, 0) <> new_latest_id THEN
                        -- Set is_latest=false for old latest
                        IF current_latest_id IS NOT NULL THEN
                            UPDATE game_versions
                            SET is_latest = false
                            WHERE id = current_latest_id;
                        END IF;

                        -- Set is_latest=true for new latest
                        UPDATE game_versions
                        SET is_latest = true
                        WHERE id = new_latest_id;
                    END IF;
                -- For DELETE
                ELSIF TG_OP = \'DELETE\' THEN
                    -- Only proceed if we\'re deleting a latest version
                    IF OLD.is_latest THEN
                        -- Find new latest version
                        SELECT id INTO new_latest_id
                        FROM game_versions
                        WHERE game_id = OLD.game_id
                        ORDER BY published_at DESC
                        LIMIT 1;

                        -- Set new latest version if one exists
                        IF new_latest_id IS NOT NULL THEN
                            UPDATE game_versions
                            SET is_latest = true
                            WHERE id = new_latest_id;
                        END IF;
                    END IF;
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Create trigger
        DB::statement('
            CREATE TRIGGER update_game_version_latest_flag_trigger
            AFTER INSERT OR UPDATE OF published_at OR DELETE ON game_versions
            FOR EACH ROW
            EXECUTE FUNCTION update_game_version_latest_flag();
        ');

        // Initialize is_latest flags for existing data
        DB::statement('
            WITH latest_versions AS (
                SELECT DISTINCT ON (game_id) id
                FROM game_versions
                ORDER BY game_id, published_at DESC
            )
            UPDATE game_versions
            SET is_latest = (id IN (SELECT id FROM latest_versions));
        ');
    }

    public function down(): void
    {
        // Drop trigger and function
        DB::statement('DROP TRIGGER IF EXISTS update_game_version_latest_flag_trigger ON game_versions');
        DB::statement('DROP FUNCTION IF EXISTS update_game_version_latest_flag');

        // Remove column
        Schema::table('game_versions', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'is_latest']);
            $table->dropColumn('is_latest');
        });
    }
};
