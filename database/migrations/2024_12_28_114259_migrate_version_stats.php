<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Get all game versions with stats
        $versions = DB::table('game_versions')
            ->get();

        foreach ($versions as $version) {
            // Get all supported languages for this game's version
            $languages = DB::table('game_supported_languages')
                ->where('game_id', $version->game_id)
                ->get();

            // If we have no languages, add English as supported
            if ($languages->isEmpty()) {
                DB::table('game_supported_languages')->insert([
                    'game_id' => $version->game_id,
                    'iso_code' => 'eng',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $languages = collect([
                    (object) ['iso_code' => 'eng'],
                ]);
            }

            // Create stats entries for each supported language
            foreach ($languages as $lang) {
                DB::table('version_language_stats')->insert([
                    'game_version_id' => $version->id,
                    'iso_code' => $lang->iso_code,
                    // Only copy stats to English entries, use NULL for other languages
                    'blocks' => $lang->iso_code === 'eng' ? $version->stats_blocks : null,
                    'words' => $lang->iso_code === 'eng' ? $version->stats_words : null,
                    'menus' => $lang->iso_code === 'eng' ? $version->stats_menus : null,
                    'options' => $lang->iso_code === 'eng' ? $version->stats_options : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Clear all migrated stats
        DB::table('version_language_stats')->truncate();
    }
};
