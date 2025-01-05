<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;
use Tpetry\PostgresqlEnhanced\Schema\Blueprint;
use Tpetry\PostgresqlEnhanced\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->caseInsensitiveText('slug')->nullable();
        });

        // Generate slugs only for visible games
        DB::table('games')
            ->whereNull('slug')
            ->where('is_visible', true)
            ->orderBy('id')
            ->each(function ($game) {
                // Get base slug from game URL
                $baseSlug = basename($game->url);

                // If URL doesn't provide a usable slug, generate from name
                if (empty($baseSlug) || $baseSlug === '/') {
                    $baseSlug = Str::slug($game->name);
                }

                // Find a unique slug
                $slug = $baseSlug;
                $counter = 1;

                while (DB::table('games')->where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }

                DB::table('games')->where('id', $game->id)->update(['slug' => $slug]);
            });

        // Make slug unique but keep it nullable for invisible games
        Schema::table('games', function (Blueprint $table) {
            $table->unique(['slug']);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
