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
        // Add unique constraint to prevent duplicate notification history records
        Schema::table('notification_history', function (Blueprint $table) {
            $table->unique(['user_id', 'game_id', 'game_version_id', 'type'], 'notification_history_unique_constraint');
        });

        // Add unique constraint to prevent duplicate pending notifications in queue
        Schema::table('notification_queue', function (Blueprint $table) {
            $table->unique(['user_id', 'game_id', 'game_version_id', 'channel'], 'notification_queue_unique_constraint');
        });

        // Add check constraint to ensure notification_queue status is valid
        DB::statement("
            ALTER TABLE notification_queue
            ADD CONSTRAINT notification_queue_status_check
            CHECK (status IN ('pending', 'processing', 'sent', 'failed'))
        ");

        // Add essential indexes for notification queries
        Schema::table('user_game_progress', function (Blueprint $table) {
            $table->index(['game_id', 'receive_updates'], 'user_game_progress_game_notifications_index');
        });

        Schema::table('notification_queue', function (Blueprint $table) {
            $table->index(['status', 'scheduled_at'], 'notification_queue_processing_index');
        });
    }

    public function down(): void
    {
        // Remove check constraints
        DB::statement('ALTER TABLE notification_queue DROP CONSTRAINT IF EXISTS notification_queue_status_check');

        // Remove unique constraints
        Schema::table('notification_history', function (Blueprint $table) {
            $table->dropUnique('notification_history_unique_constraint');
        });

        Schema::table('notification_queue', function (Blueprint $table) {
            $table->dropUnique('notification_queue_unique_constraint');
        });

        // Remove indexes
        Schema::table('user_game_progress', function (Blueprint $table) {
            $table->dropIndex('user_game_progress_game_notifications_index');
        });

        Schema::table('notification_queue', function (Blueprint $table) {
            $table->dropIndex('notification_queue_processing_index');
        });
    }
};
