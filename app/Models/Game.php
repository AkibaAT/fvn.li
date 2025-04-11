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
use Illuminate\Support\Facades\Storage;

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
        'full_description',
        'custom_css',
        'url',
        'thumb_url',
        'game_engine',
        'authors',
        'custom_tags',
        'source_language_id',
        'min_price',
        'suggested_price',
        'is_on_sale',
        'screenshots',
    ];

    protected $casts = [
        'initially_published_at' => 'datetime',
        'latest_version_published_at' => 'datetime',
        'rating' => 'float',
        'rating_count' => 'integer',
        'min_price' => 'float',
        'suggested_price' => 'float',
        'is_windows' => 'boolean',
        'is_linux' => 'boolean',
        'is_mac' => 'boolean',
        'is_android' => 'boolean',
        'is_web' => 'boolean',
        'is_nsfw' => 'boolean',
        'is_visible' => 'boolean',
        'is_on_sale' => 'boolean',
        'optimized_thumbnails' => 'array',
        'supported_languages' => 'collection',
        'uploads' => 'array',
        'screenshots' => 'array',
        'custom_css' => 'string',
        'average_score' => 'float',
        'ratings_count' => 'integer',
    ];

    // Ensure custom_tags is never null
    public function setCustomTagsAttribute($value)
    {
        $this->attributes['custom_tags'] = $value ?? '';
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
     * Get all user progress records for this game.
     */
    public function userProgress(): HasMany
    {
        return $this->hasMany(UserGameProgress::class);
    }

    /**
     * Get the supported languages collection for the latest version.
     * Only includes languages that are marked as available.
     */
    public function getSupportedLanguages(): Collection
    {
        if ($this->relationLoaded('latestVersion') &&
            $this->latestVersion?->relationLoaded('supportedLanguages')) {
            return $this->latestVersion->supportedLanguages
                ->where('is_available', true)  // Only include available languages
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
     * Get all supported languages, including those not available to users.
     * This is used for administrative purposes.
     */
    public function getAllSupportedLanguages(): Collection
    {
        if ($this->relationLoaded('latestVersion') &&
            $this->latestVersion?->relationLoaded('supportedLanguages')) {
            return $this->latestVersion->supportedLanguages
                ->whereNotNull('language')  // Exclude stats without valid language records
                ->where(function ($stat) {
                    return ! str_starts_with($stat->iso_code, 'q');  // Exclude placeholder codes
                })
                ->map(fn ($stat) => [
                    'iso_code' => $stat->iso_code,
                    'ref_name' => $stat->language->ref_name,
                    'flag_code' => $stat->language->flag_code,
                    'is_available' => $stat->is_available,
                ])->collect();
        }

        return collect();
    }

    /**
     * Get all available languages from the latest version.
     */
    public function getAvailableLanguages(): Collection
    {
        if (! $this->latestVersion) {
            return collect();
        }

        return $this->latestVersion->supportedLanguages()
            ->where('is_available', true)
            ->with('language')
            ->get()
            ->map(fn ($sl) => [
                'iso_code' => $sl->iso_code,
                'ref_name' => $sl->language->ref_name,
                'flag_code' => $sl->language->flag_code,
            ]);
    }

    /**
     * Check if a specific language is available in the latest version.
     */
    public function isLanguageAvailable(string $isoCode): bool
    {
        if (! $this->latestVersion) {
            return false;
        }

        $support = $this->latestVersion->supportedLanguages()
            ->where('iso_code', $isoCode)
            ->first();

        return $support && $support->is_available;
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
                DB::rollBack();

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

            if (! $hasChanges && ! $force) {
                DB::rollBack();

                return;
            }

            $this->uploads = $seenUploads;

            $bestUpload = Upload::getBest(collect($candidateUploads));
            if ($bestUpload) {
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

                    // If creating a new version (not just refreshing an existing one)
                    if (! $existingVersion) {
                        // Find previous version that has any unavailable languages
                        $previousVersion = $this->gameVersions()
                            ->where('id', '!=', $gameVersion->id)
                            ->whereHas('supportedLanguages', function ($query) {
                                $query->where('is_available', false);
                            })
                            ->latest('published_at')
                            ->first();

                        // Copy language availability settings from previous version
                        if ($previousVersion) {
                            VersionSupportedLanguage::copyAvailabilitySettings($previousVersion->id, $gameVersion->id);
                        }
                    }

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

                                // Save the stats - pass $this as the game object for game-specific language mappings
                                $statsService->saveVersionStats($gameVersion, $result['stats'], $this->source_language_id, $this);
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
            } else {
                // If we're not creating a new version or doing a forced update,
                // update platform flags of the latest version if they've changed
                $latestVersion = $this->gameVersions()->where('is_latest', true)->first();
                if ($latestVersion) {
                    $platformsChanged = false;

                    if ($latestVersion->is_windows !== $isWindows) {
                        $latestVersion->is_windows = $isWindows;
                        $platformsChanged = true;
                    }

                    if ($latestVersion->is_linux !== $isLinux) {
                        $latestVersion->is_linux = $isLinux;
                        $platformsChanged = true;
                    }

                    if ($latestVersion->is_mac !== $isMac) {
                        $latestVersion->is_mac = $isMac;
                        $platformsChanged = true;
                    }

                    if ($latestVersion->is_android !== $isAndroid) {
                        $latestVersion->is_android = $isAndroid;
                        $platformsChanged = true;
                    }

                    if ($latestVersion->is_web !== $isWeb) {
                        $latestVersion->is_web = $isWeb;
                        $platformsChanged = true;
                    }

                    // Save the changes if any platform flags were updated
                    if ($platformsChanged) {
                        $latestVersion->save();
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
            // Look for version in parentheses first (highest priority for display name)
            if (preg_match('/\(([0-9]+(?:\.[0-9]+)*(?:[a-zA-Z]*)?)\)/', $upload['display_name'], $matches)) {
                if ($this->isProbableVersion($matches[1])) {
                    $candidates[] = [$matches[1], 3];
                }
            }

            // Look for explicit version
            preg_match_all(
                '/(?:[vV](?:ersion)?)?\s*([0-9]+\.[0-9]+(?:\.[0-9]+)*(?:[a-zA-Z]*)?)(?=[-_. ]|$)/i',
                $upload['display_name'],
                $matches
            );

            // Find the highest semantic version
            $highestVersion = null;
            foreach ($matches[1] as $version) {
                if ($this->isProbableVersion($version)) {
                    if (! $highestVersion || version_compare($version, $highestVersion) > 0) {
                        $highestVersion = $version;
                    }
                }
            }

            if ($highestVersion) {
                $candidates[] = [$highestVersion, 2];
            } else {
                // Fallback: only look for single numbers if no semantic version found,
                // but avoid matching numbers that are part of a dotted sequence.
                preg_match_all('/(?<!\.)\b(\d+)\b/', $upload['display_name'], $matches);
                foreach ($matches[1] as $version) {
                    if ($this->isProbableVersion($version)) {
                        if (! $highestVersion || version_compare($version, $highestVersion) > 0) {
                            $highestVersion = $version;
                        }
                    }
                }
                if ($highestVersion) {
                    $candidates[] = [$highestVersion, 1];  // Lower priority for single numbers
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

    /**
     * Get all game versions for this game.
     */
    public function gameVersions(): HasMany
    {
        return $this->hasMany(GameVersion::class)->orderByDesc('published_at');
    }

    /**
     * Refresh the game's tags and rating information
     *
     * @throws GuzzleException
     */
    public function refreshTagsAndRating(
        Client $client,
        ?string &$devlogLink = null,
        ?float &$rating = null,
        ?int &$ratingCount = null
    ): void {
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

        // Get price information
        $this->extractPriceInformation($doc);

        // Get full description
        $this->extractFullDescription($doc);

        // Get screenshots
        $this->extractScreenshots($doc);

        // Get custom CSS
        $this->extractCustomCss($html);

        // Get game jam information
        $this->extractGameJamInfo($doc);

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
     * Get the URL for a thumbnail variant
     */
    public function getThumbnailUrl(string $variant = 'default'): ?string
    {
        if (! isset($this->optimized_thumbnails[$variant], $this->optimized_thumbnails[$variant]['path'])) {
            return $this->thumb_url;
        }

        $path = $this->optimized_thumbnails[$variant]['path'];

        return asset('storage/' . $path);
    }

    /**
     * Clear all optimized thumbnails
     */
    public function clearOptimizedThumbnails(): void
    {
        if ($this->optimized_thumbnails) {
            foreach ($this->optimized_thumbnails as $variant) {
                if (isset($variant['path'])) {
                    Storage::disk('public')->delete($variant['path']);
                }
            }

            // Clear the thumbnails data
            $this->optimized_thumbnails = null;
            $this->save();
        }
    }

    /**
     * Get the language mappings specific to this game.
     */
    public function languageMappings(): HasMany
    {
        return $this->hasMany(LanguageMapping::class);
    }

    /**
     * Get the game jams this game has participated in.
     */
    public function gameJams(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(GameJam::class, 'game_game_jam')
            ->withPivot('ranking')
            ->withTimestamps();
    }

    /**
     * Get a screenshot URL by variant
     */
    public function getScreenshotUrl(int $index = 0, string $variant = 'default'): ?string
    {
        if (empty($this->screenshots) || ! isset($this->screenshots[$index])) {
            return null;
        }

        if (! isset($this->screenshots[$index]['optimized'][$variant])) {
            return $this->screenshots[$index]['url'] ?? null;
        }

        $path = $this->screenshots[$index]['optimized'][$variant]['path'];

        return asset('storage/' . $path);
    }

    /**
     * Get all screenshots
     *
     * @param  string  $variant  The variant of the screenshot to get (small, default, large)
     * @return array The screenshots with optimized URLs if available
     */
    public function getScreenshots(string $variant = 'default'): array
    {
        if (empty($this->screenshots)) {
            return [];
        }

        $screenshots = [];

        foreach ($this->screenshots as $index => $screenshot) {
            $screenshots[] = [
                'url' => $this->getScreenshotUrl($index, 'large'),
                'thumbnail_url' => $this->getScreenshotUrl($index, $variant),
            ];
        }

        return $screenshots;
    }

    /**
     * Clear all optimized screenshots
     */
    public function clearOptimizedScreenshots(): void
    {
        if (empty($this->screenshots)) {
            return;
        }

        foreach ($this->screenshots as $index => $screenshot) {
            if (isset($screenshot['optimized'])) {
                foreach ($screenshot['optimized'] as $variant) {
                    if (isset($variant['path'])) {
                        Storage::disk('public')->delete($variant['path']);
                    }
                }

                // Remove optimized data but keep original URL
                $this->screenshots[$index]['optimized'] = null;
            }
        }

        $this->save();
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
     * Extract price information from the game page
     */
    private function extractPriceInformation(HTMLDocument $doc): void
    {
        // Check for price information
        $buySection = $doc->querySelector('.buy_game_section');
        if (! $buySection) {
            // Game is free
            $this->min_price = 0;
            $this->suggested_price = 0;
            $this->is_on_sale = false;

            return;
        }

        // Check for sale status
        $saleTag = $buySection->querySelector('.sale_tag');
        $this->is_on_sale = $saleTag !== null;

        // Get minimum price
        $minPriceElement = $buySection->querySelector('.base_price');
        if ($minPriceElement) {
            $priceText = trim($minPriceElement->textContent);
            // Extract numeric value from price text (e.g., "$5.00" -> 5.00)
            preg_match('/\$?(\d+\.?\d*)/', $priceText, $matches);
            $this->min_price = $matches[1] ?? 0;
        } else {
            $this->min_price = 0;
        }

        // Get suggested price if available
        $suggestedPriceElement = $buySection->querySelector('.suggested_price');
        if ($suggestedPriceElement) {
            $priceText = trim($suggestedPriceElement->textContent);
            preg_match('/\$?(\d+\.?\d*)/', $priceText, $matches);
            $this->suggested_price = $matches[1] ?? $this->min_price;
        } else {
            $this->suggested_price = $this->min_price;
        }
    }

    /**
     * Extract full description from the game page
     */
    private function extractFullDescription(HTMLDocument $doc): void
    {
        $descriptionElement = $doc->querySelector('.formatted_description');
        if ($descriptionElement) {
            // Get the HTML content of the description
            $htmlContent = $descriptionElement->innerHTML;

            // Process the HTML content to apply our styling
            $htmlProcessor = app(\App\Services\ItchHtmlProcessor::class);
            $processedHtml = $htmlProcessor->process($htmlContent);

            $this->full_description = $processedHtml;

            // Also update the regular description if it's empty
            if (empty($this->description)) {
                $this->description = strip_tags($processedHtml);
            }
        }
    }

    /**
     * Extract screenshots from the game page
     */
    private function extractScreenshots(HTMLDocument $doc): void
    {
        $screenshots = [];

        // Look for screenshots in the carousel
        $carousel = $doc->querySelector('.screenshot_list');
        if ($carousel) {
            // First try with the screenshot_link class (older format)
            $screenshotElements = $carousel->querySelectorAll('a.screenshot_link');

            // If no elements found, try with data-image_lightbox attribute (newer format)
            if (count($screenshotElements) === 0) {
                $screenshotElements = $carousel->querySelectorAll('a[data-image_lightbox="true"]');
            }

            // If still no elements found, try with all a elements in the carousel
            if (count($screenshotElements) === 0) {
                $screenshotElements = $carousel->querySelectorAll('a');
            }

            foreach ($screenshotElements as $element) {
                $imageUrl = $element->getAttribute('href');
                if ($imageUrl) {
                    $thumbnailElement = $element->querySelector('img');
                    $thumbnailUrl = $thumbnailElement ? $thumbnailElement->getAttribute('src') : $imageUrl;

                    // Skip if the URL doesn't look like an image
                    if (! preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $imageUrl)) {
                        continue;
                    }

                    $screenshots[] = [
                        'url' => $imageUrl,
                        'thumbnail_url' => $thumbnailUrl,
                    ];
                }
            }
        }

        // Only update if we found screenshots and don't already have them
        if (! empty($screenshots) && (empty($this->screenshots) || count($screenshots) > count($this->screenshots))) {
            $this->screenshots = $screenshots;
        }
    }

    /**
     * Extract custom CSS from the game page
     */
    private function extractCustomCss(string $html): void
    {
        $customCss = '';

        // Look for the game theme CSS in the HTML
        if (preg_match('/<style type="text\/css" id="game_theme">([\s\S]*?)<\/style>/i', $html, $matches)) {
            $customCss .= trim($matches[1]) . "\n\n";
        }

        // Look for the custom CSS in the HTML
        if (preg_match('/<style type="text\/css" id="custom_css">([\s\S]*?)<\/style>/i', $html, $matches)) {
            $customCss .= trim($matches[1]);
        }

        // If we have CSS, process it to remove colors and header styling
        if (! empty($customCss)) {
            // Process the CSS using our CSS processor
            $cssProcessor = app(\App\Services\ItchCssProcessor::class);
            $processedCss = $cssProcessor->process($customCss);

            if (! empty($processedCss)) {
                // Add proper scoping to the processed CSS
                $customCss = ".game_description {\n" . $processedCss . "\n}";
            } else {
                // If processing resulted in empty CSS, set to null
                $customCss = null;
            }
        } else {
            $customCss = null;
        }

        $this->custom_css = $customCss;
    }

    /**
     * Extract game jam information from the game page
     */
    private function extractGameJamInfo(HTMLDocument $doc): void
    {
        $jamUrl = null;
        $jamName = null;

        // First, look for the standard game jam info section
        $jamSection = $doc->querySelector('.game_jam_info');
        if ($jamSection) {
            $jamLink = $jamSection->querySelector('a');
            if ($jamLink) {
                $jamUrl = $jamLink->getAttribute('href');
                $jamName = trim($jamLink->textContent);
            }
        }

        // If not found, look for submission links in the navigation area
        if (! $jamUrl) {
            // Look for links containing '/jam/' in their URL
            $jamLinks = $doc->querySelectorAll('a[href*="/jam/"]');
            foreach ($jamLinks as $link) {
                $href = $link->getAttribute('href');
                $text = trim($link->textContent);

                // Check if it's a submission link
                if (strpos($text, 'Submission to') === 0 ||
                    strpos($href, '/rate/') !== false ||
                    strpos($href, '/jam/') !== false) {

                    // Extract jam name
                    if (strpos($text, 'Submission to') === 0) {
                        $jamName = str_replace('Submission to ', '', $text);
                    } else {
                        // Try to find the jam name in the page title
                        $titleElement = $doc->querySelector('title');
                        if ($titleElement) {
                            $pageTitle = trim($titleElement->textContent);
                            // Remove ' - itch.io' from the end if present
                            $jamName = preg_replace('/ - itch\.io$/', '', $pageTitle);
                        } else {
                            // Use the URL slug as a fallback
                            $urlParts = explode('/', $href);
                            $slug = end($urlParts);
                            $jamName = str_replace('-', ' ', $slug);
                            $jamName = ucwords($jamName);
                        }
                    }

                    // Extract the main game jam URL
                    if (preg_match('|(https?://[^/]+/jam/[^/]+)/rate/|', $href, $matches)) {
                        $jamUrl = $matches[1];
                    } elseif (strpos($href, 'http') === 0) {
                        $jamUrl = $href;
                    } else {
                        // Handle relative URLs
                        $jamUrl = 'https://itch.io' . $href;
                    }

                    break;
                }
            }
        }

        if (empty($jamUrl) || empty($jamName)) {
            return;
        }

        // Use the GameJam model to find or create the game jam
        $gameJam = GameJam::findOrCreateFromUrl($jamUrl, $jamName);

        // Associate the game with the game jam if not already associated
        if (! $this->gameJams()->where('game_jam_id', $gameJam->id)->exists()) {
            $this->gameJams()->attach($gameJam->id);
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
     * Copy language support from previous version
     */
    private function copyLanguageSupport(GameVersion $gameVersion): void
    {
        // Find the previous version with language support
        $previousVersion = $this->gameVersions()
            ->where('id', '!=', $gameVersion->id)
            ->whereHas('supportedLanguages')
            ->latest('published_at')
            ->first();

        if ($previousVersion) {
            // Copy all supported languages from previous version
            foreach ($previousVersion->supportedLanguages as $supported) {
                $gameVersion->addSupportedLanguage($supported->iso_code, $supported->is_available);
            }
        } else {
            // If no previous version exists, add only source language
            if ($this->source_language_id) {
                $gameVersion->addSupportedLanguage($this->source_language_id);
            } else {
                // Fallback to English only if no source language is defined
                $gameVersion->addSupportedLanguage('eng');
            }
        }
    }
}
