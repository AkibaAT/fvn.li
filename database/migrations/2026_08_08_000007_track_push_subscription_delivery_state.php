<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE push_subscriptions ADD COLUMN delivery_status varchar(20) NOT NULL DEFAULT 'unknown'");
        DB::statement('ALTER TABLE push_subscriptions ADD COLUMN delivery_verified_at timestamp(0) without time zone NULL');
        DB::statement('ALTER TABLE push_subscriptions ADD COLUMN delivery_last_failed_at timestamp(0) without time zone NULL');
        DB::statement('ALTER TABLE push_subscriptions ADD COLUMN delivery_last_error text NULL');
        DB::statement("ALTER TABLE push_subscriptions ADD CONSTRAINT push_subscriptions_delivery_status_check CHECK (delivery_status IN ('unknown', 'verified', 'invalid'))");

        DB::statement('ALTER TABLE user_notification_preferences DROP CONSTRAINT user_notification_preferences_discord_dm_reason_check');
        DB::statement("ALTER TABLE user_notification_preferences ADD CONSTRAINT user_notification_preferences_discord_dm_reason_check CHECK (discord_dm_status_reason IS NULL OR discord_dm_status_reason IN ('cannot_dm', 'not_authorized', 'not_linked', 'account_missing', 'unknown'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE user_notification_preferences DROP CONSTRAINT user_notification_preferences_discord_dm_reason_check');
        DB::statement("ALTER TABLE user_notification_preferences ADD CONSTRAINT user_notification_preferences_discord_dm_reason_check CHECK (discord_dm_status_reason IS NULL OR discord_dm_status_reason IN ('cannot_dm', 'not_authorized', 'not_linked', 'unknown'))");

        DB::statement('ALTER TABLE push_subscriptions DROP CONSTRAINT push_subscriptions_delivery_status_check');
        DB::statement('ALTER TABLE push_subscriptions DROP COLUMN delivery_last_error');
        DB::statement('ALTER TABLE push_subscriptions DROP COLUMN delivery_last_failed_at');
        DB::statement('ALTER TABLE push_subscriptions DROP COLUMN delivery_verified_at');
        DB::statement('ALTER TABLE push_subscriptions DROP COLUMN delivery_status');
    }
};
