<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create characters table
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('character_id', 50);  // The internal ID used in the game
            $table->jsonb('display_names');      // Map of language codes to display names
            $table->foreignId('first_seen_in_version_id')->nullable()->constrained('game_versions')->nullOnDelete();
            $table->foreignId('last_seen_in_version_id')->nullable()->constrained('game_versions')->nullOnDelete();
            $table->unique(['game_id', 'character_id']);
        });

        // Create character version stats table
        Schema::create('character_version_stats', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('iso_code', 10);
            $table->integer('blocks')->default(0);
            $table->integer('words')->default(0);
            $table->unique(['game_version_id', 'character_id', 'iso_code']);
        });

        // Migrate existing data
        $this->migrateExistingData();

        // Drop old table
        Schema::dropIfExists('version_character_stats');

        Schema::rename('character_version_stats', 'version_character_stats');
    }

    public function down(): void
    {
        Schema::rename('version_character_stats', 'character_version_stats');

        // Recreate old table
        Schema::create('version_character_stats', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_version_id')->constrained('game_versions')->onDelete('cascade');
            $table->string('iso_code', 10);
            $table->string('character_id', 50);
            $table->string('display_name', 100);
            $table->integer('blocks')->default(0);
            $table->integer('words')->default(0);
            $table->unique(['game_version_id', 'iso_code', 'character_id']);
        });

        // Migrate data back
        $this->migrateDataBack();

        // Drop new tables
        Schema::dropIfExists('character_version_stats');
        Schema::dropIfExists('characters');
    }

    private function migrateExistingData(): void
    {
        // First, gather all unique characters per game
        $characters = DB::table('version_character_stats')
            ->join('game_versions', 'game_versions.id', '=', 'version_character_stats.game_version_id')
            ->select([
                'game_versions.game_id',
                'version_character_stats.character_id',
                DB::raw('MIN(game_versions.id) as first_version_id'),
                DB::raw('MAX(game_versions.id) as last_version_id'),
                DB::raw('jsonb_object_agg(
                    version_character_stats.iso_code,
                    version_character_stats.display_name
                ) as display_names'),
            ])
            ->groupBy(['game_versions.game_id', 'version_character_stats.character_id'])
            ->get();

        // Insert characters
        foreach ($characters as $char) {
            DB::table('characters')->insert([
                'created_at' => now(),
                'updated_at' => now(),
                'game_id' => $char->game_id,
                'character_id' => $char->character_id,
                'display_names' => $char->display_names,
                'first_seen_in_version_id' => $char->first_version_id,
                'last_seen_in_version_id' => $char->last_version_id,
            ]);
        }

        // Migrate stats
        $stats = DB::table('version_character_stats')
            ->join('game_versions', 'game_versions.id', '=', 'version_character_stats.game_version_id')
            ->join('characters', function ($join) {
                $join->on('characters.game_id', '=', 'game_versions.game_id')
                    ->on('characters.character_id', '=', 'version_character_stats.character_id');
            })
            ->select([
                'version_character_stats.game_version_id',
                'characters.id as character_id',
                'version_character_stats.iso_code',
                'version_character_stats.blocks',
                'version_character_stats.words',
            ])
            ->get();

        foreach ($stats as $stat) {
            DB::table('character_version_stats')->insert([
                'created_at' => now(),
                'updated_at' => now(),
                'game_version_id' => $stat->game_version_id,
                'character_id' => $stat->character_id,
                'iso_code' => $stat->iso_code,
                'blocks' => $stat->blocks,
                'words' => $stat->words,
            ]);
        }
    }

    private function migrateDataBack(): void
    {
        // Migrate stats back to old format
        $stats = DB::table('character_version_stats')
            ->join('characters', 'characters.id', '=', 'character_version_stats.character_id')
            ->select([
                'character_version_stats.game_version_id',
                'characters.character_id',
                'character_version_stats.iso_code',
                'character_version_stats.blocks',
                'character_version_stats.words',
                DB::raw('characters.display_names->character_version_stats.iso_code as display_name'),
            ])
            ->get();

        foreach ($stats as $stat) {
            DB::table('version_character_stats')->insert([
                'created_at' => now(),
                'updated_at' => now(),
                'game_version_id' => $stat->game_version_id,
                'character_id' => $stat->character_id,
                'iso_code' => $stat->iso_code,
                'blocks' => $stat->blocks,
                'words' => $stat->words,
                'display_name' => $stat->display_name ?? $stat->character_id,
            ]);
        }
    }
};
