<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('rater_id')
                ->constrained('users')->nullOnDelete();
            $table->boolean('has_spoilers')->default(false)->after('is_reviewed');
            $table->index('user_id');
            $table->unique(['user_id', 'game_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'game_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'has_spoilers']);
        });
    }
};
