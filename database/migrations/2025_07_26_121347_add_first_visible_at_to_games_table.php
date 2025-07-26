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
        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('first_visible_at')->nullable()->after('is_visible');

            // Add index for efficient queries on visibility timeline
            $table->index(['is_visible', 'first_visible_at']);
        });

        // Update existing visible games to set first_visible_at to their created_at
        // This provides a reasonable approximation for historical data
        DB::statement('
            UPDATE games
            SET first_visible_at = created_at
            WHERE is_visible = true AND first_visible_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['is_visible', 'first_visible_at']);
            $table->dropColumn('first_visible_at');
        });
    }
};
