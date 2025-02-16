<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->decimal('average_score', 8, 4)->nullable();
            $table->integer('rating_count')->default(0);
            $table->decimal('weighted_score', 8, 4)->nullable();
            $table->timestamp('score_calculated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('average_score');
            $table->dropColumn('rating_count');
            $table->dropColumn('weighted_score');
            $table->dropColumn('score_calculated_at');
        });
    }
};
