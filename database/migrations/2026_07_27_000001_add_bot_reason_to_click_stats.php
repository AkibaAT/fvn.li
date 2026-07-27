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
        Schema::table('click_stats', function (Blueprint $table): void {
            $table->string('bot_reason', 32)->nullable();
        });

        // Analytics reads only ever ask for human rows, so the index carries
        // the null predicate instead of the column.
        DB::statement('
            CREATE INDEX click_stats_human_game_type_clicked_at_index
            ON click_stats (game_id, type, clicked_at)
            WHERE bot_reason IS NULL
        ');

        DB::statement('
            CREATE INDEX click_stats_human_type_clicked_at_index
            ON click_stats (type, clicked_at)
            WHERE bot_reason IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS click_stats_human_type_clicked_at_index');
        DB::statement('DROP INDEX IF EXISTS click_stats_human_game_type_clicked_at_index');

        Schema::table('click_stats', function (Blueprint $table): void {
            $table->dropColumn('bot_reason');
        });
    }
};
