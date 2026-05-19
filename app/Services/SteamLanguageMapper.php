<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SteamLanguageMapper
{
    private const STEAM_TO_ISO = [
        'English' => 'eng',
        'French' => 'fra',
        'German' => 'deu',
        'Spanish - Spain' => 'spa',
        'Spanish - Latin America' => 'spa',
        'Spanish' => 'spa',
        'Italian' => 'ita',
        'Portuguese' => 'por',
        'Portuguese - Brazil' => 'por',
        'Russian' => 'rus',
        'Japanese' => 'jpn',
        'Korean' => 'kor',
        'Simplified Chinese' => 'zho',
        'Traditional Chinese' => 'zho',
        'Chinese' => 'zho',
        'Polish' => 'pol',
        'Turkish' => 'tur',
        'Dutch' => 'nld',
        'Swedish' => 'swe',
        'Norwegian' => 'nor',
        'Danish' => 'dan',
        'Finnish' => 'fin',
        'Czech' => 'ces',
        'Hungarian' => 'hun',
        'Romanian' => 'ron',
        'Bulgarian' => 'bul',
        'Greek' => 'ell',
        'Arabic' => 'ara',
        'Thai' => 'tha',
        'Vietnamese' => 'vie',
        'Ukrainian' => 'ukr',
        'Indonesian' => 'ind',
        'Malay' => 'msa',
        'Hindi' => 'hin',
        'Bengali' => 'ben',
        'Tamil' => 'tam',
        'Telugu' => 'tel',
        'Marathi' => 'mar',
        'Kannada' => 'kan',
        'Gujarati' => 'guj',
        'Malayalam' => 'mal',
        'Punjabi' => 'pan',
        'Urdu' => 'urd',
        'Hebrew' => 'heb',
        'Persian' => 'fas',
        'Afrikaans' => 'afr',
        'Albanian' => 'sqi',
        'Amharic' => 'amh',
        'Armenian' => 'hye',
        'Azerbaijani' => 'aze',
        'Basque' => 'eus',
        'Belarusian' => 'bel',
        'Bosnian' => 'bos',
        'Catalan' => 'cat',
        'Croatian' => 'hrv',
        'Estonian' => 'est',
        'Filipino' => 'fil',
        'Galician' => 'glg',
        'Georgian' => 'kat',
        'Icelandic' => 'isl',
        'Irish' => 'gle',
        'Kazakh' => 'kaz',
        'Khmer' => 'khm',
        'Kyrgyz' => 'kir',
        'Lao' => 'lao',
        'Latvian' => 'lav',
        'Lithuanian' => 'lit',
        'Luxembourgish' => 'ltz',
        'Macedonian' => 'mkd',
        'Mongolian' => 'mon',
        'Nepali' => 'nep',
        'Serbian' => 'srp',
        'Sinhala' => 'sin',
        'Slovak' => 'slk',
        'Slovenian' => 'slv',
        'Swahili' => 'swa',
        'Tajik' => 'tgk',
        'Tatar' => 'tat',
        'Turkmen' => 'tuk',
        'Uzbek' => 'uzb',
        'Welsh' => 'cym',
    ];

    public function parseSupportedLanguageHtml(string $steamLanguagesHtml, int $gameId): array
    {
        $languagesText = strip_tags($steamLanguagesHtml);
        $languagesText = str_replace('*', '', $languagesText);
        $languagesText = preg_replace('/\s*languages with.*$/is', '', $languagesText);

        $languageNames = array_filter(array_map('trim', explode(',', $languagesText)), fn ($name) => ! empty($name));

        if (empty($languageNames)) {
            Log::warning('No languages found in Steam data', [
                'game_id' => $gameId,
                'raw_html' => $steamLanguagesHtml,
            ]);

            return [];
        }

        $isoCodes = $this->mapNamesToIsoCodes($languageNames);

        if (empty($isoCodes)) {
            Log::warning('Could not map any Steam languages to ISO codes', [
                'game_id' => $gameId,
                'language_names' => $languageNames,
            ]);

            return [];
        }

        Log::debug('Parsed Steam languages', [
            'game_id' => $gameId,
            'language_count' => count($isoCodes),
            'languages' => $isoCodes,
        ]);

        return $isoCodes;
    }

    private function mapNamesToIsoCodes(array $steamLanguageNames): array
    {
        $isoCodes = [];
        foreach ($steamLanguageNames as $steamName) {
            if (isset(self::STEAM_TO_ISO[$steamName])) {
                $isoCodes[] = self::STEAM_TO_ISO[$steamName];
            } else {
                Log::warning('Unknown Steam language', [
                    'language_name' => $steamName,
                ]);
            }
        }

        return array_unique($isoCodes);
    }
}
