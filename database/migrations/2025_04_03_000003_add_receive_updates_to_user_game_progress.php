<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add receive_updates column to user_game_progress table
        Schema::table('user_game_progress', function (Blueprint $table) {
            $table->boolean('receive_updates')->default(false)->after('status');
        });

        // Remove receive_updates column from vn_list_entries table
        Schema::table('vn_list_entries', function (Blueprint $table) {
            $table->dropColumn('receive_updates');
        });
    }

    public function down(): void
    {
        // Add back receive_updates column to vn_list_entries
        Schema::table('vn_list_entries', function (Blueprint $table) {
            $table->boolean('receive_updates')->default(false)->after('sort_order');
        });

        // Remove receive_updates column from user_game_progress
        Schema::table('user_game_progress', function (Blueprint $table) {
            $table->dropColumn('receive_updates');
        });
    }
};
