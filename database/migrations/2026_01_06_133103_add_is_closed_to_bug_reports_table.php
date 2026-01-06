<?php

declare(strict_types=1);

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
        Schema::table('bug_reports', function (Blueprint $table) {
            $table->boolean('is_closed')->default(false)->after('status');
        });

        // Migrate existing 'closed' status to is_closed = true with status = 'resolved'
        DB::table('bug_reports')
            ->where('status', 'closed')
            ->update([
                'is_closed' => true,
                'status' => 'resolved',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert is_closed back to status = 'closed'
        DB::table('bug_reports')
            ->where('is_closed', true)
            ->update(['status' => 'closed']);

        Schema::table('bug_reports', function (Blueprint $table) {
            $table->dropColumn('is_closed');
        });
    }
};
