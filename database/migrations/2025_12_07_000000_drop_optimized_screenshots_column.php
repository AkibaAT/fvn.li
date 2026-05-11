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
        DB::statement(<<<'SQL'
            UPDATE games
            SET screenshots = (
                SELECT jsonb_agg(
                    CASE
                        WHEN jsonb_exists(orig_screenshot, 'optimized') THEN orig_screenshot
                        WHEN jsonb_exists(opt_screenshot, 'optimized') THEN
                            orig_screenshot || jsonb_build_object('optimized', opt_screenshot->'optimized')
                        ELSE orig_screenshot
                    END
                    ORDER BY idx
                )
                FROM jsonb_array_elements(screenshots) WITH ORDINALITY AS orig_screenshot_elem(orig_screenshot, idx)
                LEFT JOIN jsonb_array_elements(COALESCE(optimized_screenshots, '[]'::jsonb))
                    WITH ORDINALITY AS opt_screenshot_elem(opt_screenshot, idx2)
                    ON orig_screenshot_elem.idx = opt_screenshot_elem.idx2
            )
            WHERE screenshots IS NOT NULL
                AND optimized_screenshots IS NOT NULL
                AND jsonb_typeof(screenshots) = 'array'
                AND jsonb_typeof(optimized_screenshots) = 'array'
        SQL);

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('optimized_screenshots');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->jsonb('optimized_screenshots')->nullable()->after('screenshots');
        });

        DB::statement(<<<'SQL'
            UPDATE games
            SET
                optimized_screenshots = (
                    SELECT jsonb_agg(
                        CASE
                            WHEN jsonb_exists(screenshot, 'optimized') THEN
                                jsonb_build_object('optimized', screenshot->'optimized')
                            ELSE '{}'::jsonb
                        END
                        ORDER BY idx
                    )
                    FROM jsonb_array_elements(screenshots) WITH ORDINALITY AS screenshot_elem(screenshot, idx)
                ),
                screenshots = (
                    SELECT jsonb_agg(screenshot - 'optimized' ORDER BY idx)
                    FROM jsonb_array_elements(screenshots) WITH ORDINALITY AS screenshot_elem(screenshot, idx)
                )
            WHERE screenshots IS NOT NULL
                AND jsonb_typeof(screenshots) = 'array'
        SQL);
    }
};
