<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discord_server_games', function (Blueprint $table) {
            $table->string('discord_payload_hash', 64)->nullable()->index();
        });

        Schema::table('discord_notification_history', function (Blueprint $table) {
            $table->string('delivery_mode', 20)->default('send')->index();
            $table->string('payload_hash', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('discord_notification_history', function (Blueprint $table) {
            $table->dropColumn(['delivery_mode', 'payload_hash']);
        });

        Schema::table('discord_server_games', function (Blueprint $table) {
            $table->dropColumn('discord_payload_hash');
        });
    }
};
