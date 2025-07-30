<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('click_stats', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('game_id')->constrained()->onDelete('set null');
            $table->index(['user_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('click_stats', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'clicked_at']);
            $table->dropColumn('user_id');
        });
    }
};
