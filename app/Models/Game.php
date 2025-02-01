<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\GameArchiveService;
use App\Services\GameStatsService;
use App\ValueObjects\Upload;
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
            return $this->latestVersion->languageStats
                ->whereNotNull('language')  // Exclude stats without valid language records
                ->where(function ($stat) {
                    return ! str_starts_with($stat->iso_code, 'q');  // Exclude placeholder codes
                })
                ->map(fn ($stat) => [
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
                $fileId = (int) $upload['id'];
                $currentFilename = $upload['filename'];
                $currentDisplayName = $upload['display_name'] ?? null;
                $currentMd5 = $upload['md5_hash'] ?? null;
                $currentUpdatedAt = $upload['updated_at'];
                $currentBuildId = $upload['build_id'] ?? null;
                $currentBuild = $upload['build'] ?? [];
                $currentUserVersion = $currentBuild['user_version'] ?? null;
                $currentBuildUpdatedAt = $currentBuild['updated_at'] ?? null;

                // Always store upload info regardless of processability
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
                        'traits' => $upload['traits'] ?? [],
                        'type' => $upload['type'] ?? '',
                    ];

                    // Only add to candidate uploads if it's a processable file type
                    $candidateUpload = Upload::fromArray($seenUploads[$fileId], $fileId);
                    if ($candidateUpload->isProcessable()) {
                        $candidateUploads[] = $candidateUpload;
                    }
                }

                // Update platform flags based on traits
                if (! empty($upload['traits'])) {
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
            }

            $this->uploads = $seenUploads;

            if (! $hasChanges && ! $force) {
                DB::commit();

                return;
            }

            $bestUpload = Upload::getBest(collect($candidateUploads));
            if (! $bestUpload) {
                return;
            }

            $newVersion = $this->extractVersion($seenUploads[$bestUpload->id], true);
            $uploadTimestamp = $bestUpload->updatedAt;

            // Get existing version if any
            $existingVersion = $this->gameVersions()
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

                // Create new version with basic info first
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
                    'is_latest' => ! $existingVersion,
                ]);

                $this->gameVersions()->save($gameVersion);

                // Process statistics if it's a Ren'Py game
                if (! $this->game_engine || $this->game_engine === "Ren'Py" || $this->game_engine === 'unknown') {
                    try {
                        $archiveService = app(GameArchiveService::class);
                        $statsService = app(GameStatsService::class);

                        // Download and process
                        $result = $archiveService->downloadAndProcess(
                            $this->url,
                            $bestUpload->filename,
                            $bestUpload->id,
                            $this->id,
                            $gameVersion->id
                        );

                        if ($result['stats']) {
                            $this->game_engine = "Ren'Py";
                            $this->save();

                            // Save the stats
                            $statsService->saveVersionStats($gameVersion, $result['stats'], $this->source_language_id);

                            // Add language support entries
                            foreach ($result['stats']['languages'] as $isoCode => $langStats) {
                                $gameVersion->addSupportedLanguage($isoCode);
                            }
                        } else {
                            // Copy language support from previous version
                            $this->copyLanguageSupport($gameVersion);
                        }
                    } catch (Exception $e) {
                        Log::error('Failed to extract game stats', [
                            'game_id' => $this->id,
                            'version' => $newVersion,
                            'error' => $e->getMessage(),
                        ]);

                        // Copy language support from previous version on error
                        $this->copyLanguageSupport($gameVersion);
                    }
                } else {
                    // For non-Ren'Py games, copy language support from previous version
                    $this->copyLanguageSupport($gameVersion);
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

    /**
     * Get all characters for this game.
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class)->orderBy('character_id');
    }

    /**
     * Get the character stats for the latest version in a specific language.
     */
    public function getLatestCharacterStats(string $isoCode): Collection
    {
        return $this->latestVersion?->getCharacterStatsForLanguage($isoCode) ?? collect();
    }

    /**
     * Extract version information from upload metadata
     *
     * @throws DateMalformedStringException
     */
    public function extractVersion(array $upload, bool $allowDateFallback = false): ?string
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

        if (! empty($upload['user_version'])) {
            $version = $upload['user_version'];
            if ($this->isProbableVersion($version)) {
                $candidates[] = [$version, 3];
            }
        }

        // Check display_name (high priority)
        if (! empty($upload['display_name'])) {
            // Look for explicit version
            if (preg_match('/[vV]ersion\s*(\d+(?:\.\d+)*[a-zA-Z]*)/i', $upload['display_name'], $matches)) {
                if ($this->isProbableVersion($matches[1])) {
                    $candidates[] = [$matches[1], 2];
                }
            } else {
                // Look for other version patterns
                preg_match_all('/(?:[vV](?:ersion)?)?\s*(\d+(?:\.\d+)*[a-zA-Z]*)(?=[-_. ]|$)/i',
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
            preg_match_all('/(?:[vV](?:ersion)?)?\s*(\d+(?:\.\d+)*[a-zA-Z]*)(?=[-_. ]|$)/i',
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

        // Only return date-based version if explicitly allowed
        if ($allowDateFallback) {
            $timestamp = new DateTime($upload['updated_at']);

            return $timestamp->format('Y.m.d');
        }

        return null;
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

        // Match version pattern with optional letter suffix
        if (! preg_match('/^(\d+(?:\.\d+)*?)([a-zA-Z]+)?$/', $version, $matches)) {
            return null;
        }

        try {
            $parts = array_map('intval', explode('.', $matches[1]));
            $suffix = $matches[2] ?? '';

            return [$parts, $suffix];
        } catch (Exception) {
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

    private function copyLanguageSupport(GameVersion $newVersion): void
    {
        // Find the previous version with language support
        $previousVersion = $this->gameVersions()
            ->where('id', '!=', $newVersion->id)
            ->whereHas('supportedLanguages')
            ->latest('published_at')
            ->first();

        if ($previousVersion) {
            // Copy all supported languages from previous version
            foreach ($previousVersion->getSupportedLanguageCodes() as $isoCode) {
                $newVersion->addSupportedLanguage($isoCode);
            }
        } else {
            // If no previous version exists, add only source language
            if ($this->source_language_id) {
                $newVersion->addSupportedLanguage($this->source_language_id);
            } else {
                // Fallback to English only if no source language is defined
                $newVersion->addSupportedLanguage('eng');
            }
        }
    }
}
