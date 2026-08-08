<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('ALTER TABLE discord_notification_history ADD COLUMN attempts smallint NOT NULL DEFAULT 0');
        DB::statement('CREATE INDEX CONCURRENTLY discord_notification_history_status_updated_index ON discord_notification_history (delivery_status, updated_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS discord_notification_history_status_updated_index');
        DB::statement('ALTER TABLE discord_notification_history DROP COLUMN attempts');
    }
};
