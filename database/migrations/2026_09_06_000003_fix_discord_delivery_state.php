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
        Schema::table('discord_servers', function (Blueprint $table): void {
            $table->boolean('bot_present')->default(false);
        });
        DB::table('discord_servers')->update(['bot_present' => DB::raw('is_active')]);
        // Old counters counted claims, including alerts the bot never attempted.
        foreach (['addition_requests', 'review_reports'] as $table) {
            DB::table($table)->where('status', 'pending')->whereNull('discord_notified_at')
                ->update(['discord_notify_attempts' => 0, 'discord_claimed_at' => null]);
        }
        Schema::table('discord_notification_history', function (Blueprint $table): void {
            $table->timestampTz('retry_at')->nullable()->index();
        });
        DB::statement('ALTER TABLE discord_notification_history DROP CONSTRAINT discord_notification_history_notification_type_check');
        DB::statement("ALTER TABLE discord_notification_history ADD CONSTRAINT discord_notification_history_notification_type_check CHECK (notification_type IN ('update', 'new_game', 'rating_change', 'manual', 'test'))");
        Schema::create('discord_catalog_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discord_server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('discord_channel_id');
            $table->string('discord_message_id')->nullable();
            $table->string('discord_payload_hash', 64)->nullable();
            $table->timestampsTz();
            $table->unique(['discord_server_id', 'game_id', 'discord_channel_id'], 'discord_catalog_message_destination_unique');
        });
        DB::statement('INSERT INTO discord_catalog_messages (discord_server_id, game_id, discord_channel_id, discord_message_id, discord_payload_hash, created_at, updated_at)
            SELECT discord_server_id, game_id, discord_channel_id, discord_message_id, discord_payload_hash, created_at, updated_at
            FROM discord_server_games WHERE discord_channel_id IS NOT NULL');
        DB::statement("INSERT INTO discord_catalog_messages (discord_server_id, game_id, discord_channel_id, discord_message_id, discord_payload_hash, created_at, updated_at)
            SELECT DISTINCT ON (discord_server_id, game_id, channel_id)
                discord_server_id, game_id, channel_id, message_id, payload_hash, created_at, updated_at
            FROM discord_notification_history
            WHERE notification_type = 'new_game' AND delivery_status = 'sent' AND game_id IS NOT NULL AND message_id IS NOT NULL
            ORDER BY discord_server_id, game_id, channel_id, sent_at DESC NULLS LAST, id DESC
            ON CONFLICT (discord_server_id, game_id, discord_channel_id) DO NOTHING");
    }

    public function down(): void
    {
        DB::table('discord_notification_history')->where('notification_type', 'test')->update(['notification_type' => 'update']);
        DB::statement('ALTER TABLE discord_notification_history DROP CONSTRAINT discord_notification_history_notification_type_check');
        DB::statement("ALTER TABLE discord_notification_history ADD CONSTRAINT discord_notification_history_notification_type_check CHECK (notification_type IN ('update', 'new_game', 'rating_change', 'manual'))");
        Schema::dropIfExists('discord_catalog_messages');
        Schema::table('discord_notification_history', fn (Blueprint $table) => $table->dropColumn('retry_at'));
        Schema::table('discord_servers', fn (Blueprint $table) => $table->dropColumn('bot_present'));
    }
};
