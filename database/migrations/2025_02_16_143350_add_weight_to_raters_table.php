<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raters', function (Blueprint $table) {
            $table->decimal('weight', 8, 4)->default(0);
            $table->decimal('entropy_score', 8, 4)->nullable();
            $table->decimal('rating_count_score', 8, 4)->nullable();
            $table->timestamp('weight_calculated_at')->nullable();
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->timestamp('processed_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('raters', function (Blueprint $table) {
            $table->dropColumn('weight');
            $table->dropColumn('entropy_score');
            $table->dropColumn('rating_count_score');
            $table->dropColumn('weight_calculated_at');
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->dropColumn('processed_at');
        });
    }
};
