<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discord_notification_history', function (Blueprint $table) {
            $table->string('batch_key')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('discord_notification_history', function (Blueprint $table) {
            $table->dropColumn('batch_key');
        });
    }
};
