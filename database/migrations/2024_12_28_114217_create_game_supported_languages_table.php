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
        // Create a table to store all supported languages per game
        Schema::create('game_supported_languages', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->string('iso_code', 3);

            $table->unique(['game_id', 'iso_code']);
        });

        // Common language mappings
        $languageMap = [
            // Basic mappings
            'English' => 'eng',
            'French' => 'fra',
            'Chinese' => 'zho',
            'Chinese (Simplified)' => 'zho',
            'Japanese' => 'jpn',
            'Korean' => 'kor',
            'German' => 'deu',
            'Spanish' => 'spa',
            'Spanish; Castilian' => 'spa',
            'Russian' => 'rus',
            'Italian' => 'ita',
            'Portuguese' => 'por',
            'Portuguese (Portugal)' => 'por',
            'Vietnamese' => 'vie',
            'Thai' => 'tha',
            'Arabic' => 'ara',
            'Indonesian' => 'ind',
            'Polish' => 'pol',
            'Turkish' => 'tur',

            // Additional common variations
            'Brazilian Portuguese' => 'pob',
            'Br Portuguese' => 'pob',
            'Brazilian' => 'pob',
            'Portuguese (Brazil)' => 'pob',
            'Portuguese (European)' => 'por',
            'Mexican Spanish' => 'esm',
            'Spanish (Mexico)' => 'esm',
            'Mandarin' => 'cmn',
            'Mandarin Chinese' => 'cmn',
            'Simplified Chinese' => 'zho',
            'Traditional Chinese' => 'zho',
            'Cantonese' => 'yue',

            // European languages
            'Dutch' => 'nld',
            'Nederlands' => 'nld',
            'Swedish' => 'swe',
            'Svenska' => 'swe',
            'Norwegian' => 'nor',
            'Norsk' => 'nor',
            'Finnish' => 'fin',
            'Suomi' => 'fin',
            'Danish' => 'dan',
            'Dansk' => 'dan',
            'Czech' => 'ces',
            'Čeština' => 'ces',
            'Slovak' => 'slk',
            'Slovenčina' => 'slk',
            'Hungarian' => 'hun',
            'Magyar' => 'hun',
            'Romanian' => 'ron',
            'Română' => 'ron',
            'Bulgarian' => 'bul',
            'български' => 'bul',
            'Greek' => 'ell',
            'Ελληνικά' => 'ell',
            'Ukrainian' => 'ukr',
            'Українська' => 'ukr',

            // Asian languages
            'Hindi' => 'hin',
            'हिन्दी' => 'hin',
            'Filipino' => 'fil',
            'Tagalog' => 'fil',
            'Malay' => 'msa',
            'Bahasa Melayu' => 'msa',
            'Bahasa Indonesia' => 'ind',

            // Middle Eastern languages
            'Persian' => 'fas',
            'Farsi' => 'fas',
            'فارسی' => 'fas',
            'Hebrew' => 'heb',
            'עברית' => 'heb',
            'العربية' => 'ara',
        ];

        $now = now();

        // Get all games
        $games = DB::table('games')
            ->whereNotNull('languages')
            ->orWhereNotNull('stats_words')  // Also include games with stats but no languages
            ->select(['id', 'languages', 'stats_words'])
            ->get();

        foreach ($games as $game) {
            $languagesArray = [];

            // Parse existing languages string if it exists
            if (! empty($game->languages)) {
                $languagesArray = array_map('trim', explode(',', $game->languages));
            }

            // If no languages specified, but we have stats, assume English
            if (empty($languagesArray) && ! empty($game->stats_words)) {
                $languagesArray = ['English'];
            }

            $processedIsoCodes = [];

            // Insert supported languages
            foreach ($languagesArray as $language) {
                $isoCode = $languageMap[trim($language)] ?? null;
                if ($isoCode) {
                    // Skip duplicate entries
                    if (in_array($isoCode, $processedIsoCodes)) {
                        continue;
                    }
                    $processedIsoCodes[] = $isoCode;

                    // Insert the supported language
                    DB::table('game_supported_languages')->insert([
                        'game_id' => $game->id,
                        'iso_code' => $isoCode,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('game_supported_languages');
    }
};
