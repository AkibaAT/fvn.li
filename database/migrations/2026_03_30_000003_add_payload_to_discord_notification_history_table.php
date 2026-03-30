<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discord_notification_history', function (Blueprint $table) {
            $table->jsonb('payload')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('discord_notification_history', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
