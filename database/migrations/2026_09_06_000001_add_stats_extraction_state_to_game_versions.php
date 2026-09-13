<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_versions', function (Blueprint $table): void {
            $table->timestampTz('stats_completed_at')->nullable();
            $table->unsignedSmallInteger('stats_attempts')->default(0);
            $table->timestampTz('stats_retry_at')->nullable()->index();
            $table->text('stats_error')->nullable();
            $table->boolean('stats_skipped')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('game_versions', function (Blueprint $table): void {
            $table->dropColumn(['stats_completed_at', 'stats_attempts', 'stats_retry_at', 'stats_error', 'stats_skipped']);
        });
    }
};
