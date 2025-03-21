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
        Schema::table('vn_list_entries', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('completed_at');
        });

        // Initialize sort_order for existing entries
        DB::statement('
            WITH ranked AS (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY vn_list_id ORDER BY created_at) * 10 as new_order
                FROM vn_list_entries
            )
            UPDATE vn_list_entries
            SET sort_order = ranked.new_order
            FROM ranked
            WHERE vn_list_entries.id = ranked.id
        ');
    }

    public function down(): void
    {
        Schema::table('vn_list_entries', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
