<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration removes the separate optimized_screenshots column.
     * The optimized data is now stored directly in each screenshot object
     * within the screenshots column, keyed by the source URL.
     *
     * New structure:
     * [
     *   {
     *     "url": "https://...",
     *     "optimized": {
     *       "small": { "path": "...", "width": ..., "height": ..., "mime_type": "..." },
     *       "default": { ... },
     *       "large": { ... }
     *     }
     *   }
     * ]
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('optimized_screenshots');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->jsonb('optimized_screenshots')->nullable()->after('screenshots');
        });
    }
};
