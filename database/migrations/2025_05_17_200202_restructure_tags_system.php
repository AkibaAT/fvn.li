<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Create tags table
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Create game_tag pivot table
        Schema::create('game_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['game_id', 'tag_id']);
        });

        // Migrate existing tags to the new structure
        $games = DB::table('games')->whereNotNull('tags')->get();

        foreach ($games as $game) {
            $tags = array_filter(array_map('trim', explode(',', $game->tags)));

            foreach ($tags as $tagName) {
                if (empty($tagName)) {
                    continue;
                }

                // Get or create the tag
                $tag = DB::table('tags')
                    ->where('name', $tagName)
                    ->first();

                if (! $tag) {
                    $tagId = DB::table('tags')->insertGetId([
                        'name' => $tagName,
                        'slug' => Str::slug($tagName),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $tagId = $tag->id;
                }

                // Create the relationship
                DB::table('game_tag')->insertOrIgnore([
                    'game_id' => $game->id,
                    'tag_id' => $tagId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Drop the pivot table first due to foreign key constraints
        Schema::dropIfExists('game_tag');

        // Drop the tags table
        Schema::dropIfExists('tags');
    }
};
