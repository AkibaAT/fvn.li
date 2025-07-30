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
            $table->decimal('rating_score', 3, 2)->nullable()->after('custom_css');
            $table->integer('rating_count')->default(0)->after('rating_score');

            $table->index(['rating_score', 'rating_count']);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['rating_score', 'rating_count']);
            $table->dropColumn(['rating_score', 'rating_count']);
        });
    }
};
