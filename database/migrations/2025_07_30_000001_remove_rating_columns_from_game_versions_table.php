<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_versions', function (Blueprint $table) {
            $table->dropColumn(['rating', 'rating_count']);
        });
    }

    public function down(): void
    {
        Schema::table('game_versions', function (Blueprint $table) {
            $table->decimal('rating', 3, 2)->nullable()->after('is_web');
            $table->integer('rating_count')->nullable()->after('rating');
        });
    }
};
