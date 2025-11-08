<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add the new optimized_screenshots column
        Schema::table('games', function (Blueprint $table) {
            $table->jsonb('optimized_screenshots')->nullable()->after('screenshots');
        });

        // Migrate data: extract optimized data from screenshots into optimized_screenshots
        // and clean up the screenshots column to only contain original URLs
        DB::statement("
            UPDATE games
            SET
                optimized_screenshots = (
                    SELECT jsonb_agg(
                        jsonb_build_object(
                            'optimized', screenshot->'optimized'
                        )
                    )
                    FROM jsonb_array_elements(screenshots) AS screenshot
                    WHERE screenshot->'optimized' IS NOT NULL
                ),
                screenshots = (
                    SELECT jsonb_agg(
                        screenshot - 'optimized'
                    )
                    FROM jsonb_array_elements(screenshots) AS screenshot
                )
            WHERE screenshots IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Merge optimized_screenshots back into screenshots
        DB::statement("
            UPDATE games
            SET screenshots = (
                SELECT jsonb_agg(
                    CASE
                        WHEN opt_screenshot IS NOT NULL THEN
                            orig_screenshot || opt_screenshot
                        ELSE
                            orig_screenshot
                    END
                )
                FROM jsonb_array_elements(screenshots) WITH ORDINALITY AS orig_screenshot_elem(orig_screenshot, idx)
                LEFT JOIN jsonb_array_elements(optimized_screenshots) WITH ORDINALITY AS opt_screenshot_elem(opt_screenshot, idx2)
                    ON orig_screenshot_elem.idx = opt_screenshot_elem.idx2
            )
            WHERE screenshots IS NOT NULL
        ");

        // Drop the optimized_screenshots column
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('optimized_screenshots');
        });
    }
};
