<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\GameStatsService;
use DateMalformedStringException;
use DateTime;
use Dom\HTMLDocument;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Game extends Model
{
    protected $fillable = [
        'game_id',
        'slug',
        'name',
        'status',
        'is_visible',
        'is_nsfw',
        'description',
        'url',
        'thumb_url',
        'game_engine',
        'authors',
        'custom_tags',
        'source_language_id',
    ];

    protected $casts = [
        'initially_published_at' => 'datetime',
        'latest_version_published_at' => 'datetime',
        'rating' => 'float',
        'rating_count' => 'integer',
        'is_windows' => 'boolean',
        'is_linux' => 'boolean',
        'is_mac' => 'boolean',
        'is_android' => 'boolean',
        'is_web' => 'boolean',
        'is_nsfw' => 'boolean',
        'is_visible' => 'boolean',
        'supported_languages' => 'collection',
        'uploads' => 'array',
    ];

    /**
     * Get all game versions for this game.
     */
    public function gameVersions(): HasMany
    {
        return $this->hasMany(GameVersion::class)->orderByDesc('published_at');
    }

    /**
     * Get the latest version of the game.
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(GameVersion::class)->where('is_latest', true);
    }

    /**
     * Get all ratings for this game.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Get the supported languages collection for the latest version.
     */
    public function getSupportedLanguages(): Collection
    {
        if ($this->relationLoaded('latestVersion') &&
            $this->latestVersion?->relationLoaded('languageStats')) {
            return $this->latestVersion->languageStats->map(fn ($stat) => [
                'iso_code' => $stat->iso_code,
                'ref_name' => $stat->language->ref_name,
                'flag_code' => $stat->language->flag_code,
            ])->collect();
        }

        return collect();
    }

    /**
     * Get the English word count from the latest version.
     */
    public function getEnglishWordCount(): ?int
    {
        // First try to get from the english_word_count attribute which is pre-loaded in list views
        if (isset($this->attributes['english_word_count'])) {
            return $this->english_word_count;
        }

        // Otherwise load from the latest version
        if ($this->relationLoaded('latestVersion')) {
            $englishStats = $this->latestVersion?->getStatsForLanguage('eng');

            return $englishStats?->words;
        }

        return null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function resolveRouteBinding($value, $field = null): Game
    {
        $query = $this->where($field, $value);

        return $query->firstOrFail();
    }

    /**
     * Refresh the base game information from itch.io
     *
     * @throws DateMalformedStringException
     * @throws GuzzleException
     */
    public function refreshBaseInfo(Client $client): void
    {
        $url = 'https://api.itch.io/games/' . $this->game_id;

        $response = $client->get($url);
        $game = json_decode($response->getBody()->getContents(), true);

        if (isset($game['game'])) {
            $this->initially_published_at = new DateTime($game['game']['published_at']);
            $this->thumb_url = $game['game']['cover_url'];
        }
    }

    /**
     * Refresh the game's tags and rating information
     *
     * @throws GuzzleException
     */
    public function refreshTagsAndRating(Client $client, ?string &$devlogLink = null, ?float &$rating = null, ?int &$ratingCount = null): void
    {
        $response = $client->get($this->url, ['cookies' => false]);
        $html = $response->getBody()->getContents();
        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        // Update status if not abandoned/canceled
        if (! in_array($this->status, ['Abandoned', 'Canceled'])) {
            $gameInfo = $doc->querySelector('div.game_info_panel_widget');
            if ($gameInfo) {
                $statusLinks = $gameInfo->querySelectorAll('a');
                if (count($statusLinks) > 0) {
                    $this->status = $statusLinks[0]->textContent;
                }
            }
        }

        // Get rating information
        $ratingElement = $doc->querySelector('div[itemprop=ratingValue]');
        $ratingCountElement = $doc->querySelector('span[itemprop=ratingCount]');

        if ($ratingElement && $ratingCountElement) {
            $rating = (float) $ratingElement->getAttribute('content');
            $ratingCount = (int) $ratingCountElement->getAttribute('content');
        }

        // Get game info table data
        $infoTable = $doc->querySelector('div.game_info_panel_widget table');
        if ($infoTable) {
            foreach ($infoTable->querySelectorAll('tr') as $row) {
                $cells = $row->querySelectorAll('td');
                if (count($cells) < 2) {
                    continue;
                }

                $label = trim($cells[0]->textContent);
                $value = trim($cells[1]->textContent);

                switch ($label) {
                    case 'Tags':
                        $this->tags = $value;
                        break;
                    case 'Author':
                    case 'Authors':
                        $this->authors = '';
                        foreach ($cells[1]->querySelectorAll('a') as $author) {
                            if ($this->authors !== '') {
                                $this->authors .= ',<br>';
                            }
                            $this->authors .= sprintf(
                                '<a href="%s" target="_blank">%s</a>',
                                $author->getAttribute('href'),
                                $author->textContent
                            );
                        }
                        break;
                }
            }
        }

        // Check NSFW status
        $nsfw = $doc->querySelector('div.content_warning_inner');
        $this->is_nsfw = $nsfw !== null;

        // Get devlog link if present
        $devlog = $doc->querySelector('section#devlog');
        if ($devlog) {
            $devlogLinks = $devlog->querySelectorAll('a');
            if (count($devlogLinks) > 0) {
                $devlogLink = $devlogLinks[0]->getAttribute('href');
            }
        }
    }

    /**
     * Refresh version information for the game
     *
     * @throws GuzzleException|DateMalformedStringException
     * @throws Exception
     */
    public function refreshVersion(Client $client, bool $force = false): void
    {
        DB::beginTransaction();

        try {
            $url = "https://api.itch.io/games/{$this->game_id}/uploads";

            $response = $client->get($url);
            if ($response->getStatusCode() === 404 || $response->getStatusCode() === 400) {
                $this->is_visible = false;
                $this->save();
                DB::commit();

                return;
            }

            $uploadsData = json_decode($response->getBody()->getContents(), true);
            if (! isset($uploadsData['uploads'])) {
                return;
            }

            $seenUploads = $this->uploads ?: [];
            $hasChanges = false;
            $candidateUploads = [];

            // Platform flags for the latest version
            $isWindows = false;
            $isLinux = false;
            $isMac = false;
            $isAndroid = false;
            $isWeb = false;

            foreach ($uploadsData['uploads'] as $upload) {
                $fileId = (string) $upload['id'];
                $currentFilename = $upload['filename'];
                $currentDisplayName = $upload['display_name'] ?? null;
                $currentMd5 = $upload['md5_hash'] ?? null;
                $currentUpdatedAt = $upload['updated_at'];
                $currentBuildId = $upload['build_id'] ?? null;
                $currentBuild = $upload['build'] ?? [];
                $currentUserVersion = $currentBuild['user_version'] ?? null;
                $currentBuildUpdatedAt = $currentBuild['updated_at'] ?? null;

                // Update platform flags
                if (isset($upload['traits'])) {
                    if (in_array('p_windows', $upload['traits'])) {
                        $isWindows = true;
                    }
                    if (in_array('p_linux', $upload['traits'])) {
                        $isLinux = true;
                    }
                    if (in_array('p_osx', $upload['traits'])) {
                        $isMac = true;
                    }
                    if (in_array('p_android', $upload['traits'])) {
                        $isAndroid = true;
                    }
                }
                if ($upload['type'] === 'html') {
                    $isWeb = true;
                }

                // Check if upload is new or changed
                $isNewOrChanged = (
                    ! isset($seenUploads[$fileId]) ||
                    $seenUploads[$fileId]['md5_hash'] !== $currentMd5 ||
                    $seenUploads[$fileId]['updated_at'] !== $currentUpdatedAt ||
                    $seenUploads[$fileId]['build_id'] !== $currentBuildId ||
                    $seenUploads[$fileId]['build_updated_at'] !== $currentBuildUpdatedAt
                );

                if ($isNewOrChanged) {
                    $hasChanges = true;
                    $seenUploads[$fileId] = [
                        'display_name' => $currentDisplayName,
                        'md5_hash' => $currentMd5,
                        'updated_at' => $currentUpdatedAt,
                        'build_id' => $currentBuildId,
                        'build_updated_at' => $currentBuildUpdatedAt,
                        'user_version' => $currentUserVersion,
                        'filename' => $currentFilename,
                    ];
                    $candidateUploads[] = $upload;
                }
            }

            $this->uploads = $seenUploads;

            if (! $hasChanges && ! $force) {
                DB::commit();

                return;
            }

            // Sort uploads by priority
            usort($candidateUploads, function ($a, $b) {
                $criteria = [
                    'linux' => in_array('p_linux', $a['traits'] ?? []) <=> in_array('p_linux', $b['traits'] ?? []),
                    'windows' => in_array('p_windows', $a['traits'] ?? []) <=> in_array('p_windows', $b['traits'] ?? []),
                    'zip' => (strtolower(pathinfo($a['filename'], PATHINFO_EXTENSION)) === 'zip') <=>
                        (strtolower(pathinfo($b['filename'], PATHINFO_EXTENSION)) === 'zip'),
                    'date' => strtotime($a['updated_at']) <=> strtotime($b['updated_at']),
                    'build_date' => strtotime($a['build']['updated_at'] ?? '1970-01-01') <=>
                        strtotime($b['build']['updated_at'] ?? '1970-01-01'),
                ];

                foreach ($criteria as $result) {
                    if ($result !== 0) {
                        return -$result;
                    }
                }

                return 0;
            });

            if (empty($candidateUploads)) {
                return;
            }

            $uploadToProcess = $candidateUploads[0];
            $newVersion = $this->extractVersion($uploadToProcess);
            $uploadTimestamp = new DateTime($uploadToProcess['updated_at']);

            // Get existing version if any
            $existingVersion = $this->gameVersions()
                ->where('is_latest', true)
                ->where('version', $newVersion)
                ->first();

            if (! $existingVersion || $force) {
                // Update the game's info & get rating info
                sleep(10);
                $devlogLink = null;
                $versionRating = null;
                $versionRatingCount = null;

                // Get devlog link and ratings
                $this->refreshTagsAndRating($client, $devlogLink, $versionRating, $versionRatingCount);
                $this->save();

                // Create new version
                $gameVersion = new GameVersion([
                    'version' => $newVersion,
                    'devlog' => $devlogLink,
                    'is_windows' => $isWindows,
                    'is_linux' => $isLinux,
                    'is_mac' => $isMac,
                    'is_android' => $isAndroid,
                    'is_web' => $isWeb,
                    'published_at' => $uploadTimestamp,
                    'rating' => $versionRating,
                    'rating_count' => $versionRatingCount,
                    'is_latest' => true,
                ]);

                $this->gameVersions()->save($gameVersion);

                // Process statistics if it's a Ren'Py game
                if (! $this->game_engine || $this->game_engine === "Ren'Py" || $this->game_engine === 'unknown') {
                    try {
                        $statsService = app(GameStatsService::class);
                        $stats = $statsService->getUploadStats($this->url, $uploadToProcess['filename'], $uploadToProcess['id']);

                        if ($stats) {
                            $this->game_engine = "Ren'Py";
                            $this->save();
                            $statsService->saveVersionStats($gameVersion, $stats);
                        } else {
                            // If stats extraction failed, create empty English stats
                            $versionStats = new VersionLanguageStats([
                                'iso_code' => 'eng',
                            ]);
                            $gameVersion->languageStats()->save($versionStats);
                        }
                    } catch (Exception $e) {
                        Log::error('Failed to extract game stats', [
                            'game_id' => $this->id,
                            'version' => $newVersion,
                            'error' => $e->getMessage(),
                        ]);

                        // Create empty English stats on failure
                        $versionStats = new VersionLanguageStats([
                            'iso_code' => 'eng',
                        ]);
                        $gameVersion->languageStats()->save($versionStats);
                    }
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @throws DateMalformedStringException
     * @throws GuzzleException
     */
    public function loadFullDetails(Client $client): void
    {
        try {
            $this->refreshBaseInfo($client);
            sleep(10);
            $this->refreshVersion($client);
            $this->error = null;
        } catch (Exception $exception) {
            $this->error = $exception->getMessage();
            throw $exception;
        }
    }

    protected function devlog(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->latestVersion?->devlog
        );
    }

    protected function rating(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->latestVersion?->rating
        );
    }

    protected function ratingCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->latestVersion?->rating_count
        );
    }

    /**
     * Get the platforms attribute.
     */
    protected function platforms(): Attribute
    {
        return Attribute::make(
            get: fn () => [
                'windows' => $this->latestVersion?->is_windows ?? false,
                'linux' => $this->latestVersion?->is_linux ?? false,
                'mac' => $this->latestVersion?->is_mac ?? false,
                'android' => $this->latestVersion?->is_android ?? false,
                'web' => $this->latestVersion?->is_web ?? false,
            ],
        );
    }

    /**
     * Parse a version string into a normalized format
     */
    private function parseSemanticVersion(string $version): ?array
    {
        // Remove leading 'v' or 'version'
        $version = preg_replace('/^[vV]ersion\s*/', '', $version);
        $version = preg_replace('/^[vV]\s*/', '', $version);

        // Match version pattern
        if (! preg_match('/^(\d+(?:\.\d+)*?)([a-zA-Z])?$/', $version, $matches)) {
            return null;
        }

        try {
            $parts = array_map('intval', explode('.', $matches[1]));
            $suffix = $matches[2] ?? '';

            return [$parts, $suffix];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Check if a string looks like a probable version number
     */
    private function isProbableVersion(string $version): bool
    {
        if (empty($version)) {
            return false;
        }

        $parsed = $this->parseSemanticVersion($version);
        if (! $parsed) {
            return false;
        }

        [$parts] = $parsed;

        // Reject if first number is too large or looks like a year
        if ($parts[0] > 2100 || ($parts[0] > 100 && strlen((string) $parts[0]) === 4)) {
            return false;
        }

        // Reject if any part is suspiciously large
        if (array_filter($parts, fn ($p) => $p > 10000)) {
            return false;
        }

        return true;
    }

    /**
     * Extract version information from upload metadata
     *
     * @throws DateMalformedStringException
     */
    private function extractVersion(array $upload): string
    {
        // Collect version candidates with source and priority
        $candidates = [];

        // Check build.user_version first (highest priority)
        if (! empty($upload['build']['user_version'])) {
            $version = $upload['build']['user_version'];
            if ($this->isProbableVersion($version)) {
                $candidates[] = [$version, 3];
            }
        }

        // Check display_name (high priority)
        if (! empty($upload['display_name'])) {
            // Look for explicit version
            if (preg_match('/[vV]ersion\s*(\d+(?:\.\d+)*)(?:[a-zA-Z][a-z]*)?/', $upload['display_name'], $matches)) {
                if ($this->isProbableVersion($matches[1])) {
                    $candidates[] = [$matches[1], 2];
                }
            } else {
                // Look for other version patterns
                preg_match_all('/(?:[vV](?:ersion)?)?(\d+(?:\.\d+)*)(?:[a-zA-Z][a-z]*)?(?=[-\s._)]|$)/',
                    $upload['display_name'], $matches);
                foreach ($matches[1] as $version) {
                    if ($this->isProbableVersion($version)) {
                        $candidates[] = [$version, 2];
                    }
                }
            }
        }

        // Check filename (lowest priority)
        $filename = $upload['filename'] ?? '';
        $cleanedFilename = preg_replace('/\.(zip|tar\.bz2|tar\.gz)$/', '', $filename);

        // Look for build numbers
        if (preg_match('/[bB]uild[_\s-]*(\d+)/', $cleanedFilename, $matches)) {
            if ($this->isProbableVersion($matches[1])) {
                $candidates[] = [$matches[1], 1];
            }
        } else {
            // Look for version patterns in filename
            preg_match_all('/(?:[vV](?:ersion)?)?(\d+(?:\.\d+)*)(?:[a-zA-Z][a-z]*)?(?=[-\s._)]|$)/',
                $cleanedFilename, $matches);
            foreach ($matches[1] as $version) {
                if ($this->isProbableVersion($version)) {
                    $candidates[] = [$version, 0];
                }
            }
        }

        if (! empty($candidates)) {
            // Sort by priority (desc) then version string
            usort($candidates, fn ($a, $b) => $b[1] <=> $a[1] ?: strcmp($a[0], $b[0]));

            return $candidates[0][0];
        }

        // Fallback to timestamp
        $timestamp = new DateTime($upload['updated_at']);

        return $timestamp->format('Y.m.d');
    }
}
