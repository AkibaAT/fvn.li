<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vn_lists', function (Blueprint $table) {
            // Index for user's lists queries (most common)
            $table->index(['user_id', 'created_at'], 'vn_lists_user_created_index');

            // Index for public lists queries
            $table->index(['is_public', 'created_at'], 'vn_lists_public_created_index');

            // Index for type-based filtering
            $table->index(['type', 'is_public', 'created_at'], 'vn_lists_type_public_created_index');

            // Index for user + visibility filtering
            $table->index(['user_id', 'is_public', 'created_at'], 'vn_lists_user_public_created_index');

            // Index for user + type filtering
            $table->index(['user_id', 'type', 'created_at'], 'vn_lists_user_type_created_index');
        });

        Schema::table('vn_list_entries', function (Blueprint $table) {
            // Index for list entries queries (most common)
            $table->index(['vn_list_id', 'sort_order'], 'vn_list_entries_list_sort_index');

            // Index for game-based queries (checking if game is in lists)
            $table->index(['game_id', 'vn_list_id'], 'vn_list_entries_game_list_index');

            // Index for finding max sort_order efficiently
            $table->index(['vn_list_id', 'sort_order'], 'vn_list_entries_max_sort_index');
        });
    }

    public function down(): void
    {
        Schema::table('vn_lists', function (Blueprint $table) {
            $table->dropIndex('vn_lists_user_created_index');
            $table->dropIndex('vn_lists_public_created_index');
            $table->dropIndex('vn_lists_type_public_created_index');
            $table->dropIndex('vn_lists_user_public_created_index');
            $table->dropIndex('vn_lists_user_type_created_index');
        });

        Schema::table('vn_list_entries', function (Blueprint $table) {
            $table->dropIndex('vn_list_entries_list_sort_index');
            $table->dropIndex('vn_list_entries_game_list_index');
            $table->dropIndex('vn_list_entries_max_sort_index');
        });
    }
};
