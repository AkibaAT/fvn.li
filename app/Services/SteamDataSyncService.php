<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Tag;
use DateTime;
use Dom\HTMLDocument;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SteamDataSyncService
{
    private Client $httpClient;
    private array $parsedLanguageIsoCodes = [];
    private array $parsedPlatforms = [];

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ],
        ]);
    }

    /**
     * Load full game details from Steam
     *
     * @throws GuzzleException
     * @throws Throwable
     */
    public function loadFullDetails(Game $game): void
    {
        try {
            // Get the Steam URL from the JSONB url field
            $steamUrl = $game->getPrimaryUrl();
            if (! $steamUrl) {
                throw new Exception('Game does not have a Steam URL');
            }

            // Extract Steam App ID from URL
            $appId = $this->extractSteamAppId($steamUrl);
            if (! $appId) {
                throw new Exception("Could not extract Steam App ID from URL: {$steamUrl}");
            }

            // Store the Steam App ID
            $game->steam_app_id = $appId;

            // Fetch data from Steam API (this will also parse languages)
            $this->refreshFromSteamApi($game, $appId);

            sleep(2);

            // Fetch additional data from store page HTML
            $this->refreshFromStorePage($game, $steamUrl);

            // Create game version and add languages
            $this->syncGameVersion($game);

            // Sync tags from Steam genres and user tags
            $this->syncSteamTags($game);

            $game->error = null;
        } catch (Exception $exception) {
            $game->error = $exception->getMessage();
            Log::error('Steam data sync failed', [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'platform' => $game->platform,
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    /**
     * Create or update game version from Steam data
     *
     * @throws Throwable
     */
    public function syncGameVersion(Game $game): void
    {
        if (! $game->steam_app_id) {
            throw new Exception('Game does not have a Steam App ID');
        }

        DB::beginTransaction();

        try {
            // For Steam games, we don't have detailed version history like itch.io
            // Create a single "current" version
            $version = GameVersion::firstOrCreate(
                [
                    'game_id' => $game->id,
                    'version' => 'current',
                ],
                [
                    'published_at' => $game->initially_published_at ?? now(),
                    'is_windows' => $this->parsedPlatforms['windows'] ?? true,
                    'is_linux' => $this->parsedPlatforms['linux'] ?? false,
                    'is_mac' => $this->parsedPlatforms['mac'] ?? false,
                    'is_android' => false, // Steam doesn't support Android
                    'is_web' => false, // Steam doesn't support web
                ]
            );

            // Update platform support if version already exists
            if (! $version->wasRecentlyCreated) {
                $version->is_windows = $this->parsedPlatforms['windows'] ?? $version->is_windows;
                $version->is_linux = $this->parsedPlatforms['linux'] ?? $version->is_linux;
                $version->is_mac = $this->parsedPlatforms['mac'] ?? $version->is_mac;
                $version->save();
            }

            // Mark as latest
            GameVersion::where('game_id', $game->id)
                ->where('id', '!=', $version->id)
                ->update(['is_latest' => false]);

            $version->is_latest = true;
            $version->save();

            // Refresh the game to get the latest version relationship
            $game->load('latestVersion');

            // Add languages to the version if they were parsed from Steam API
            $this->addLanguagesToVersion($game, $this->parsedLanguageIsoCodes);

            // Clear the parsed data after use
            $this->parsedLanguageIsoCodes = [];
            $this->parsedPlatforms = [];

            DB::commit();

            Log::info('Steam game version synced', [
                'game_id' => $game->id,
                'version_id' => $version->id,
                'platforms' => [
                    'windows' => $version->is_windows,
                    'linux' => $version->is_linux,
                    'mac' => $version->is_mac,
                ],
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Add Steam languages to the game version
     */
    public function addLanguagesToVersion(Game $game, array $isoCodes): void
    {
        if (empty($isoCodes)) {
            return;
        }

        $version = $game->latestVersion;
        if (! $version) {
            Log::warning('No game version found to add languages', [
                'game_id' => $game->id,
            ]);

            return;
        }

        // Add supported languages to the version
        foreach ($isoCodes as $isoCode) {
            $version->addSupportedLanguage($isoCode, true);
        }

        Log::info('Added Steam languages to version', [
            'game_id' => $game->id,
            'version_id' => $version->id,
            'language_count' => count($isoCodes),
            'languages' => $isoCodes,
        ]);
    }

    /**
     * Extract Steam App ID from URL
     */
    private function extractSteamAppId(string $url): ?string
    {
        // Pattern: https://store.steampowered.com/app/123456/Game_Name/
        if (preg_match('/\/app\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Fetch game data from Steam Store API
     *
     * @throws GuzzleException
     */
    private function refreshFromSteamApi(Game $game, string $appId): void
    {
        $url = "https://store.steampowered.com/api/appdetails?appids={$appId}&cc=us&l=english";

        Log::info('Fetching Steam API data', [
            'app_id' => $appId,
            'url' => $url,
        ]);

        $response = $this->httpClient->get($url);
        $data = json_decode($response->getBody()->getContents(), true);

        if (! isset($data[$appId]['success']) || ! $data[$appId]['success']) {
            throw new Exception("Steam API returned unsuccessful response for app ID: {$appId}");
        }

        $appData = $data[$appId]['data'];

        // Extract basic information
        $game->name = $appData['name'] ?? $game->name;
        $game->description = $appData['short_description'] ?? null;
        $game->full_description = $appData['detailed_description'] ?? null;

        // Extract pricing information
        if (isset($appData['is_free'])) {
            $game->is_paid = ! $appData['is_free'];
            $game->min_price = 0;
            $game->currency = 'USD'; // Default for free games
        }

        if (isset($appData['price_overview'])) {
            $game->is_paid = true;
            // Steam prices are in cents
            $game->min_price = $appData['price_overview']['initial'] / 100;
            $discountPercent = $appData['price_overview']['discount_percent'] ?? 0;
            $game->is_on_sale = $discountPercent > 0;
            $game->sale_discount_percent = $discountPercent > 0 ? $discountPercent : null;
            // Extract currency from Steam API (ISO 4217 codes like USD, EUR, JPY)
            $game->currency = strtoupper($appData['price_overview']['currency'] ?? 'USD');
        }

        // Extract release date
        if (isset($appData['release_date']['date'])) {
            try {
                $game->initially_published_at = new DateTime($appData['release_date']['date']);
            } catch (Exception $e) {
                Log::warning('Could not parse Steam release date', [
                    'date' => $appData['release_date']['date'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Extract header image (cover)
        if (isset($appData['header_image'])) {
            $game->thumb_url = $appData['header_image'];
        }

        // Extract screenshots
        if (isset($appData['screenshots']) && is_array($appData['screenshots'])) {
            $screenshots = [];
            foreach ($appData['screenshots'] as $screenshot) {
                $screenshots[] = [
                    'url' => $screenshot['path_full'] ?? null,
                    'thumbnail_url' => $screenshot['path_thumbnail'] ?? null,
                ];
            }
            if (! empty($screenshots)) {
                $game->screenshots = $screenshots;
            }
        }

        // Extract demo availability
        if (isset($appData['demos']) && is_array($appData['demos']) && ! empty($appData['demos'])) {
            $game->has_demo = true;
            Log::debug('Steam game has demo', [
                'app_id' => $appId,
                'demo_count' => count($appData['demos']),
                'demo_app_ids' => array_column($appData['demos'], 'appid'),
            ]);
        } else {
            $game->has_demo = false;
        }

        // Extract developers and publishers
        if (isset($appData['developers']) && is_array($appData['developers'])) {
            $developerNames = $appData['developers'];
            $game->developer = implode(', ', $developerNames);

            // Also set authors field for consistency with itch.io games
            // For Steam games, we use plain text instead of HTML links
            $game->authors = implode(', ', $developerNames);

            Log::debug('Extracted Steam developers', [
                'app_id' => $appId,
                'developers' => $developerNames,
            ]);
        }

        // Extract platform support
        if (isset($appData['platforms']) && is_array($appData['platforms'])) {
            $this->parsedPlatforms = [
                'windows' => $appData['platforms']['windows'] ?? false,
                'linux' => $appData['platforms']['linux'] ?? false,
                'mac' => $appData['platforms']['mac'] ?? false,
            ];

            Log::debug('Extracted Steam platforms', [
                'app_id' => $appId,
                'platforms' => $this->parsedPlatforms,
            ]);
        }

        // Extract genres/tags
        if (isset($appData['genres']) && is_array($appData['genres'])) {
            $genres = array_map(fn ($g) => $g['description'] ?? '', $appData['genres']);
            // Store in a custom field or handle separately
            $game->steam_genres = $genres;
        }

        // Extract supported languages
        if (isset($appData['supported_languages'])) {
            // Steam returns HTML with language names
            $game->steam_languages = strip_tags($appData['supported_languages']);

            // Parse languages and store in class property for later use
            $this->parsedLanguageIsoCodes = $this->importSteamLanguages($game, $appData['supported_languages']);
        }

        // Extract content descriptors (NSFW check)
        if (isset($appData['content_descriptors']['ids']) && is_array($appData['content_descriptors']['ids'])) {
            // Steam content descriptor IDs: 3 = Nudity or Sexual Content, 4 = Adult Only Sexual Content
            $game->is_nsfw = in_array(3, $appData['content_descriptors']['ids']) ||
                            in_array(4, $appData['content_descriptors']['ids']);
        } else {
            // No content descriptors means SFW game
            $game->is_nsfw = false;
        }

        // Determine status based on release date
        if (isset($appData['release_date']['coming_soon']) && $appData['release_date']['coming_soon']) {
            $game->status = 'In development';
        } else {
            $game->status = 'Released';
        }

        Log::info('Steam API data extracted', [
            'app_id' => $appId,
            'name' => $game->name,
            'is_paid' => $game->is_paid,
            'min_price' => $game->min_price,
            'is_nsfw' => $game->is_nsfw,
        ]);
    }

    /**
     * Fetch additional data from Steam store page HTML
     *
     * @throws GuzzleException
     */
    private function refreshFromStorePage(Game $game, string $steamUrl): void
    {
        Log::info('Fetching Steam store page', [
            'url' => $steamUrl,
        ]);

        try {
            // Create a cookie jar with mature content cookies
            $cookieJar = CookieJar::fromArray([
                'birthtime' => (string) strtotime('-30 years'),
                'mature_content' => '1',
            ], 'steampowered.com');

            $response = $this->httpClient->get($steamUrl, [
                'cookies' => $cookieJar,
            ]);

            $html = $response->getBody()->getContents();
            $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);

            // Extract additional metadata that might not be in the API
            // For example, user tags, reviews, etc.

            // Extract user-defined tags
            $tags = [];
            $tagElements = $doc->querySelectorAll('.app_tag');
            foreach ($tagElements as $tagElement) {
                $tagText = trim($tagElement->textContent);
                if (! empty($tagText) && $tagText !== '+') {
                    $tags[] = $tagText;
                }
            }
            if (! empty($tags)) {
                $game->steam_user_tags = array_slice($tags, 0, 20); // Limit to 20 tags
            }

            Log::info('Steam store page data extracted', [
                'url' => $steamUrl,
                'tags_count' => count($tags),
            ]);
        } catch (Exception $e) {
            Log::warning('Could not fetch Steam store page HTML', [
                'url' => $steamUrl,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - API data is more important
        }
    }

    /**
     * Parse and import Steam languages to game version
     * Returns the parsed ISO codes
     */
    private function importSteamLanguages(Game $game, string $steamLanguagesHtml): array
    {
        // Steam returns HTML like: "English<strong>*</strong>, French, German<br><strong>*</strong>languages with full audio support"
        // Strip HTML tags and extract language names
        $languagesText = strip_tags($steamLanguagesHtml);

        // Remove the footnote about audio support
        $languagesText = preg_replace('/\*.*$/s', '', $languagesText);

        // Split by comma and clean up
        $languageNames = array_map('trim', explode(',', $languagesText));
        $languageNames = array_filter($languageNames, fn ($name) => ! empty($name));

        if (empty($languageNames)) {
            Log::warning('No languages found in Steam data', [
                'game_id' => $game->id,
                'raw_html' => $steamLanguagesHtml,
            ]);

            return [];
        }

        // Map Steam language names to ISO codes
        $isoCodes = $this->mapSteamLanguagesToIsoCodes($languageNames);

        if (empty($isoCodes)) {
            Log::warning('Could not map any Steam languages to ISO codes', [
                'game_id' => $game->id,
                'language_names' => $languageNames,
            ]);

            return [];
        }

        Log::debug('Parsed Steam languages', [
            'game_id' => $game->id,
            'language_count' => count($isoCodes),
            'languages' => $isoCodes,
        ]);

        return $isoCodes;
    }

    /**
     * Map Steam language names to ISO 639-3 codes
     */
    private function mapSteamLanguagesToIsoCodes(array $steamLanguageNames): array
    {
        // Steam language name to ISO 639-3 code mapping
        $steamToIso = [
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

        $isoCodes = [];
        foreach ($steamLanguageNames as $steamName) {
            if (isset($steamToIso[$steamName])) {
                $isoCodes[] = $steamToIso[$steamName];
            } else {
                Log::warning('Unknown Steam language', [
                    'language_name' => $steamName,
                ]);
            }
        }

        // Remove duplicates (e.g., multiple Chinese variants)
        return array_unique($isoCodes);
    }

    /**
     * Sync tags from Steam genres and user tags
     */
    private function syncSteamTags(Game $game): void
    {
        $tagNames = [];

        // Add Steam genres (official categories)
        if (! empty($game->steam_genres)) {
            foreach ($game->steam_genres as $genre) {
                if (! empty($genre)) {
                    $tagNames[] = $genre;
                }
            }
        }

        // Add Steam user tags (community-defined tags)
        if (! empty($game->steam_user_tags)) {
            foreach ($game->steam_user_tags as $userTag) {
                if (! empty($userTag)) {
                    $tagNames[] = $userTag;
                }
            }
        }

        if (empty($tagNames)) {
            Log::debug('No Steam tags to sync', [
                'game_id' => $game->id,
            ]);

            return;
        }

        // Remove duplicates and limit to reasonable number
        $tagNames = array_unique($tagNames);
        $tagNames = array_slice($tagNames, 0, 30); // Limit to 30 tags

        // Create or find tags and collect their IDs
        $tagIds = [];
        foreach ($tagNames as $tagName) {
            // Try to find by name first (case-insensitive)
            $tag = Tag::whereRaw('LOWER(name) = ?', [strtolower($tagName)])->first();

            if (! $tag) {
                // Try to create, but handle slug collisions
                try {
                    $tag = Tag::firstOrCreate(['name' => $tagName]);
                } catch (UniqueConstraintViolationException $e) {
                    // Slug collision - find the existing tag by slug
                    $slug = Str::slug($tagName);
                    $tag = Tag::where('slug', $slug)->first();

                    if (! $tag) {
                        // Still couldn't find it, skip this tag
                        Log::warning('Could not create or find tag', [
                            'tag_name' => $tagName,
                            'error' => $e->getMessage(),
                        ]);

                        continue;
                    }
                }
            }

            $tagIds[] = $tag->id;
        }

        // Sync tags to the game
        $game->tags()->sync($tagIds);

        Log::info('Synced Steam tags', [
            'game_id' => $game->id,
            'tag_count' => count($tagIds),
            'tags' => $tagNames,
        ]);
    }
}
