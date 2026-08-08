<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE user_notification_preferences ADD COLUMN discord_dm_status varchar(20) NOT NULL DEFAULT 'unverified'");
        DB::statement('ALTER TABLE user_notification_preferences ADD COLUMN discord_dm_status_reason varchar(20) NULL');
        DB::statement('ALTER TABLE user_notification_preferences ADD COLUMN discord_dm_verified_at timestamp(0) without time zone NULL');
        DB::statement('ALTER TABLE user_notification_preferences ADD COLUMN discord_dm_last_failed_at timestamp(0) without time zone NULL');
        DB::statement("ALTER TABLE user_notification_preferences ADD CONSTRAINT user_notification_preferences_discord_dm_status_check CHECK (discord_dm_status IN ('unverified', 'deliverable', 'undeliverable'))");
        DB::statement("ALTER TABLE user_notification_preferences ADD CONSTRAINT user_notification_preferences_discord_dm_reason_check CHECK (discord_dm_status_reason IS NULL OR discord_dm_status_reason IN ('cannot_dm', 'not_authorized', 'not_linked', 'unknown'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE user_notification_preferences DROP CONSTRAINT user_notification_preferences_discord_dm_reason_check');
        DB::statement('ALTER TABLE user_notification_preferences DROP CONSTRAINT user_notification_preferences_discord_dm_status_check');
        DB::statement('ALTER TABLE user_notification_preferences DROP COLUMN discord_dm_last_failed_at');
        DB::statement('ALTER TABLE user_notification_preferences DROP COLUMN discord_dm_verified_at');
        DB::statement('ALTER TABLE user_notification_preferences DROP COLUMN discord_dm_status_reason');
        DB::statement('ALTER TABLE user_notification_preferences DROP COLUMN discord_dm_status');
    }
};
