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
            $steamUrl = $game->getPrimaryUrl();
            if (! $steamUrl) {
                throw new Exception('Game does not have a Steam URL');
            }

            $appId = $this->extractSteamAppId($steamUrl);
            if (! $appId) {
                throw new Exception("Could not extract Steam App ID from URL: {$steamUrl}");
            }

            $game->steam_app_id = $appId;

            $originalThumbUrl = $game->thumb_url;
            $originalScreenshots = $game->screenshots;

            $this->refreshFromSteamApi($game, $appId);

            sleep(2);

            $this->refreshFromStorePage($game, $steamUrl);

            $this->syncGameVersion($game);

            // Sync tags from Steam genres and user tags
            $this->syncSteamTags($game);

            app(SteamImageSyncService::class)->processImages($game, $originalThumbUrl, $originalScreenshots);

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

            if (! $version->wasRecentlyCreated) {
                $version->is_windows = $this->parsedPlatforms['windows'] ?? $version->is_windows;
                $version->is_linux = $this->parsedPlatforms['linux'] ?? $version->is_linux;
                $version->is_mac = $this->parsedPlatforms['mac'] ?? $version->is_mac;
                $version->save();
            }

            GameVersion::where('game_id', $game->id)
                ->where('id', '!=', $version->id)
                ->update(['is_latest' => false]);

            $version->is_latest = true;
            $version->save();

            // Refresh the game to get the latest version relationship
            $game->load('latestVersion');

            $this->addLanguagesToVersion($game, $this->parsedLanguageIsoCodes);

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

        $game->name = $appData['name'] ?? $game->name;
        $game->description = $appData['short_description'] ?? null;
        $game->full_description = $appData['detailed_description'] ?? null;

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
            $game->currency = strtoupper($appData['price_overview']['currency'] ?? 'USD');
        }

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

        if (isset($appData['header_image'])) {
            $game->thumb_url = $appData['header_image'];
        }

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

        if (isset($appData['genres']) && is_array($appData['genres'])) {
            $genres = array_map(fn ($g) => $g['description'] ?? '', $appData['genres']);
            $game->steam_genres = $genres;
        }

        if (isset($appData['supported_languages'])) {
            // Steam returns HTML with language names
            $game->steam_languages = strip_tags($appData['supported_languages']);

            $this->parsedLanguageIsoCodes = app(SteamLanguageMapper::class)
                ->parseSupportedLanguageHtml($appData['supported_languages'], $game->id);
        }

        if (isset($appData['content_descriptors']['ids']) && is_array($appData['content_descriptors']['ids'])) {
            // Steam content descriptor IDs: 3 = Nudity or Sexual Content, 4 = Adult Only Sexual Content
            $game->is_nsfw = in_array(3, $appData['content_descriptors']['ids']) ||
                            in_array(4, $appData['content_descriptors']['ids']);
        }

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
            $cookieJar = CookieJar::fromArray([
                'birthtime' => (string) strtotime('-30 years'),
                'mature_content' => '1',
            ], 'steampowered.com');

            $response = $this->httpClient->get($steamUrl, [
                'cookies' => $cookieJar,
            ]);

            $html = $response->getBody()->getContents();
            $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);

            // For example, user tags, reviews, etc.

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
     * Sync tags from Steam genres and user tags
     */
    private function syncSteamTags(Game $game): void
    {
        $tagNames = [];

        if (! empty($game->steam_genres)) {
            foreach ($game->steam_genres as $genre) {
                if (! empty($genre)) {
                    $tagNames[] = $genre;
                }
            }
        }

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

        $tagNames = array_unique($tagNames);
        $tagNames = array_slice($tagNames, 0, 30); // Limit to 30 tags

        $tagIds = [];
        foreach ($tagNames as $tagName) {
            $tag = Tag::whereRaw('LOWER(name) = ?', [strtolower($tagName)])->first();

            if (! $tag) {
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
