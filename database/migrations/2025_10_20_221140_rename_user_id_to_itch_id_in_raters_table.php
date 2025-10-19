<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('raters', function (Blueprint $table) {
            // Rename user_id to itch_id and make it nullable
            $table->renameColumn('user_id', 'itch_id');
        });

        Schema::table('raters', function (Blueprint $table) {
            // Make itch_id nullable (needs separate statement after rename)
            $table->bigInteger('itch_id')->nullable()->change();
        });

        // Set external_platform to 'itch_io' for all existing raters
        // (they were all from itch.io before multi-platform support)
        DB::table('raters')
            ->whereNull('external_platform')
            ->update(['external_platform' => 'itch_io']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raters', function (Blueprint $table) {
            // Make itch_id non-nullable first
            $table->bigInteger('itch_id')->nullable(false)->change();
        });

        Schema::table('raters', function (Blueprint $table) {
            // Rename back to user_id
            $table->renameColumn('itch_id', 'user_id');
        });
    }
};
