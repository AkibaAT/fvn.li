<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addition_requests', function (Blueprint $table): void {
            $table->timestamp('discord_claimed_at')->nullable();
            $table->unsignedSmallInteger('discord_notify_attempts')->default(0);
        });

        Schema::table('review_reports', function (Blueprint $table): void {
            $table->timestamp('discord_claimed_at')->nullable();
            $table->unsignedSmallInteger('discord_notify_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('addition_requests', function (Blueprint $table): void {
            $table->dropColumn(['discord_claimed_at', 'discord_notify_attempts']);
        });

        Schema::table('review_reports', function (Blueprint $table): void {
            $table->dropColumn(['discord_claimed_at', 'discord_notify_attempts']);
        });
    }
};
