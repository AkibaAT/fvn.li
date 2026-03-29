<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Composite index for the varChangesByContext lookup pattern in RouteGraphService
        // where the key is label|context — this covers the (game_version_id, label, context)
        // query pattern used during graph computation.
        Schema::table('version_route_variable_changes', function (Blueprint $table) {
            $table->index(['game_version_id', 'label', 'context'], 'vrc_version_label_context_index');
        });

        // Index for filtering ending labels (used in bridgeDisconnectedComponents)
        Schema::table('version_route_labels', function (Blueprint $table) {
            $table->index(['game_version_id', 'is_ending'], 'vrl_version_is_ending_index');
        });
    }

    public function down(): void
    {
        Schema::table('version_route_variable_changes', function (Blueprint $table) {
            $table->dropIndex('vrc_version_label_context_index');
        });

        Schema::table('version_route_labels', function (Blueprint $table) {
            $table->dropIndex('vrl_version_is_ending_index');
        });
    }
};
