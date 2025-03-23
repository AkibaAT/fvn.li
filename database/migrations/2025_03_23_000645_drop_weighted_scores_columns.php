<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove columns from raters table
        Schema::table('raters', function (Blueprint $table) {
            $table->dropColumn([
                'weight',
                'entropy_score',
                'rating_count_score',
                'weight_calculated_at',
            ]);
        });

        // Remove processed_at column from ratings table
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropColumn('processed_at');
        });

        // Remove columns from games table
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'weighted_score',
                'raw_weighted_rating',
                'score_calculated_at',
            ]);
        });
    }

    public function down(): void
    {
        // Restore columns to raters table
        Schema::table('raters', function (Blueprint $table) {
            $table->float('weight')->nullable();
            $table->float('entropy_score')->nullable();
            $table->float('rating_count_score')->nullable();
            $table->timestamp('weight_calculated_at')->nullable();
        });

        // Restore processed_at column to ratings table
        Schema::table('ratings', function (Blueprint $table) {
            $table->timestamp('processed_at')->nullable();
        });

        // Restore columns to games table
        Schema::table('games', function (Blueprint $table) {
            $table->float('weighted_score')->nullable();
            $table->float('raw_weighted_rating')->nullable();
            $table->timestamp('score_calculated_at')->nullable();
        });
    }
};
