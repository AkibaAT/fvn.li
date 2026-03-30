<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE discord_notification_history DROP CONSTRAINT discord_notification_history_delivery_status_check');
        DB::statement("ALTER TABLE discord_notification_history ADD CONSTRAINT discord_notification_history_delivery_status_check CHECK (delivery_status IN ('pending', 'processing', 'sent', 'failed'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE discord_notification_history DROP CONSTRAINT discord_notification_history_delivery_status_check');
        DB::statement("ALTER TABLE discord_notification_history ADD CONSTRAINT discord_notification_history_delivery_status_check CHECK (delivery_status IN ('pending', 'sent', 'failed'))");
    }
};
