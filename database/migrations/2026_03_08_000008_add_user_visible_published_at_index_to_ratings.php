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
            $table->index(['user_id', 'is_visible', 'published_at'], 'idx_ratings_user_visible_published_at');
            $table->index(['user_id', 'is_visible', 'rating'], 'idx_ratings_user_visible_rating');
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropIndex('idx_ratings_user_visible_published_at');
            $table->dropIndex('idx_ratings_user_visible_rating');
        });
    }
};
