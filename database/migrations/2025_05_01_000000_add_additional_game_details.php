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
            // Price information
            $table->decimal('min_price', 10, 2)->nullable();
            $table->decimal('suggested_price', 10, 2)->nullable();
            $table->boolean('is_on_sale')->default(false);

            // Screenshots gallery (stored as JSON array of URLs and optimized versions)
            $table->jsonb('screenshots')->nullable();

            // Store the full description HTML from itch.io
            // We'll keep the existing 'description' field as it is
            $table->text('full_description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'min_price',
                'suggested_price',
                'is_on_sale',
                'screenshots',
                'full_description',
            ]);
        });
    }
};
