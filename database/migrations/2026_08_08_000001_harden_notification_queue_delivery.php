<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('ALTER TABLE notification_queue ADD COLUMN attempts smallint NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE notification_queue ADD COLUMN batch_key varchar(64) NULL');
        DB::statement('ALTER TABLE notification_queue ALTER COLUMN game_id DROP NOT NULL');
        DB::statement('ALTER TABLE notification_queue ALTER COLUMN game_version_id DROP NOT NULL');
        DB::statement('CREATE INDEX CONCURRENTLY notification_queue_batch_key_index ON notification_queue (batch_key)');
        DB::statement('CREATE INDEX CONCURRENTLY notification_queue_channel_status_updated_index ON notification_queue (channel, status, updated_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS notification_queue_channel_status_updated_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS notification_queue_batch_key_index');
        DB::statement('ALTER TABLE notification_queue ALTER COLUMN game_id SET NOT NULL');
        DB::statement('ALTER TABLE notification_queue ALTER COLUMN game_version_id SET NOT NULL');
        DB::statement('ALTER TABLE notification_queue DROP COLUMN batch_key');
        DB::statement('ALTER TABLE notification_queue DROP COLUMN attempts');
    }
};
