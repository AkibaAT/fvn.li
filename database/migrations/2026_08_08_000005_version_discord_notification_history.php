<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('ALTER TABLE discord_notification_history ADD COLUMN game_version_id bigint NULL');
        DB::statement('ALTER TABLE discord_notification_history ADD CONSTRAINT discord_notification_history_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES game_versions(id) ON DELETE SET NULL NOT VALID');
        DB::statement('ALTER TABLE discord_notification_history VALIDATE CONSTRAINT discord_notification_history_game_version_id_foreign');
        DB::statement('ALTER TABLE discord_notification_history ALTER COLUMN sent_at DROP DEFAULT');
        DB::statement('ALTER TABLE discord_notification_history ALTER COLUMN sent_at DROP NOT NULL');
        DB::statement("UPDATE discord_notification_history SET sent_at = NULL WHERE delivery_status <> 'sent'");
        DB::statement('CREATE INDEX CONCURRENTLY discord_notification_history_version_dedup_index ON discord_notification_history (discord_server_id, game_id, game_version_id, notification_type) WHERE game_version_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS discord_notification_history_version_dedup_index');
        DB::statement('ALTER TABLE discord_notification_history DROP CONSTRAINT discord_notification_history_game_version_id_foreign');
        DB::statement('ALTER TABLE discord_notification_history DROP COLUMN game_version_id');
        DB::statement('UPDATE discord_notification_history SET sent_at = created_at WHERE sent_at IS NULL');
        DB::statement('ALTER TABLE discord_notification_history ALTER COLUMN sent_at SET NOT NULL');
        DB::statement('ALTER TABLE discord_notification_history ALTER COLUMN sent_at SET DEFAULT CURRENT_TIMESTAMP');
    }
};
