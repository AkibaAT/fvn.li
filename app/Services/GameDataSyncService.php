<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\VersionSupportedLanguage;
use App\ValueObjects\Upload;
use DateMalformedStringException;
use DateTime;
use Dom\HTMLDocument;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class GameDataSyncService
{
    private static array $httpCache = [];

    /**
     * Load full game details - delegates to platform-specific service
     *
     * @throws BindingResolutionException
     * @throws DateMalformedStringException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function loadFullDetails(Game $game): void
    {
        // Delegate to platform-specific service
        if ($game->isSteamGame()) {
            $steamService = App::make(SteamDataSyncService::class);
            $steamService->loadFullDetails($game);
            $game->save();

            return;
        }

        if ($game->isItchioGame()) {
            $this->loadFullDetailsItchio($game);

            return;
        }

        throw new Exception("Unsupported platform for game: {$game->name} (platform: {$game->platform})");
    }

    /**
     * Refresh the base game information from itch.io
     *
     * @throws DateMalformedStringException
     * @throws BindingResolutionException
     * @throws GuzzleException
     * @throws Exception
     */
    public function refreshBaseInfo(Game $game): void
    {
        // Only itch.io games can be refreshed from itch.io API
        if (! $game->isItchioGame()) {
            throw new Exception("Cannot refresh base info for non-itch.io game: {$game->name} (platform: {$game->getPlatformName()})");
        }

        // Get the ItchHttpClientService
        $itchClient = App::make(ItchHttpClientService::class);

        $url = "https://api.itch.io/games/{$game->itch_id}";

        $response = $itchClient->get($url);
        $gameData = json_decode($response->getBody()->getContents(), true);

        if (isset($gameData['game'])) {
            $game->initially_published_at = new DateTime($gameData['game']['published_at']);
            $game->thumb_url = $gameData['game']['cover_url'] ?? null;
        }
    }

    public function refreshVersion(Game $game, bool $force = false): void
    {
        echo "  [Version] Starting version refresh for game: {$game->name}\n";

        // Track if game had no versions at start
        $hadNoVersions = ! $game->gameVersions()->exists();

        // ========================================
        // PHASE 1: Fetch all external data (NO TRANSACTION - no locks held)
        // ========================================
        echo "    [Version] Fetching uploads data from itch.io\n";

        // Get the ItchHttpClientService
        $itchClient = App::make(ItchHttpClientService::class);

        $url = "https://api.itch.io/games/{$game->itch_id}/uploads";

        $response = $itchClient->get($url);

        // Handle game not found - mark as invisible
        if ($response->getStatusCode() === 404 || $response->getStatusCode() === 400) {
            $game->is_visible = false;
            $game->save();

            return;
        }

        $uploadsData = json_decode($response->getBody()->getContents(), true);
        if (! isset($uploadsData['uploads'])) {
            // No uploads data - if game has no versions, we need to create a fallback in the transaction
            if ($hadNoVersions) {
                echo "    [Version] No uploads found, but game has no versions - will create fallback\n";
                // Don't return yet - we'll create fallback in Phase 3
            } else {
                echo "    [Version] No uploads found\n";

                return;
            }
        }

        $seenUploads = $game->uploads ?: [];
        $hasChanges = false;
        $candidateUploads = [];
        $processableUploads = [];
        $platforms = [
            'windows' => false,
            'linux' => false,
            'mac' => false,
            'android' => false,
            'web' => false,
        ];

        // Process uploads data to detect changes
        if (isset($uploadsData['uploads'])) {
            echo '    [Version] Processing ' . count($uploadsData['uploads']) . " uploads\n";
            $uploadAnalysis = app(GameUploadAnalyzer::class)->analyze($uploadsData['uploads'], $seenUploads, $force);
            $seenUploads = $uploadAnalysis['seenUploads'];
            $hasChanges = $uploadAnalysis['hasChanges'];
            $candidateUploads = $uploadAnalysis['candidateUploads'];
            $processableUploads = $uploadAnalysis['processableUploads'];
            $platforms = $uploadAnalysis['platforms'];
        }

        $isWindows = $platforms['windows'];
        $isLinux = $platforms['linux'];
        $isMac = $platforms['mac'];
        $isAndroid = $platforms['android'];
        $isWeb = $platforms['web'];

        // Select best upload from candidates using Upload model's sorting logic
        $bestUpload = Upload::getBest(collect($candidateUploads));
        if ($bestUpload) {
            echo "    [Version] Selected best upload: {$bestUpload->filename}\n";
        } else {
            echo "    [Version] No processable uploads found\n";
        }

        if ($game->is_paid) {
            echo "    [Version] Paid game; skipping stats extraction\n";
        } elseif ($this->hasOnlyDemoProcessableUploads($processableUploads)) {
            $game->is_stats_extraction_disabled = true;
            echo "    [Version] Only demo archives are processable; skipping stats extraction\n";
        } elseif ($processableUploads !== [] && $game->is_stats_extraction_disabled) {
            $game->is_stats_extraction_disabled = false;
            echo "    [Version] Full processable archive is available; enabling stats extraction\n";
        }

        // Exit early if no changes detected and game already has versions
        if (! $hasChanges && ! $force && ! $hadNoVersions) {
            $this->updateLatestVersionPlatformFlags($game, $isWindows, $isLinux, $isMac, $isAndroid, $isWeb);
            echo "    [Version] No changes detected\n";

            return;
        }

        // Prepare version data
        $newVersion = null;
        $uploadTimestamp = null;
        $shouldCreateVersion = false;

        if ($bestUpload) {
            $versionParserService = app(GameVersionParser::class);
            $newVersion = $versionParserService->extractVersion($seenUploads[$bestUpload->id], true);
            $uploadTimestamp = $bestUpload->updatedAt;

            echo '    [Version] Extracted version: ' . ($newVersion ?: '(empty)') . "\n";

            // Check if this is a new version
            $existingVersion = $game->gameVersions()
                ->where('version', $newVersion)
                ->first();

            // Only create version if it doesn't exist
            $shouldCreateVersion = ! $existingVersion;

            // When force is enabled and version exists, we should reprocess stats for that version
            $shouldReprocessExistingVersion = $force && $existingVersion;

            echo '    [Version] Should create version: ' . ($shouldCreateVersion ? 'yes' : 'no') . ' (existing: ' . ($existingVersion ? 'yes' : 'no') . ', force: ' . ($force ? 'yes' : 'no') . ")\n";
            if ($shouldReprocessExistingVersion) {
                echo "    [Version] Force mode: will reprocess stats for existing version\n";
            }
        }

        // ========================================
        // PHASE 2: Locate and process archive (NO TRANSACTION - no locks held)
        // New versions are downloaded from itch.io. Existing versions are reprocessed from local storage or DenKit Stash.
        // Wrapped in try-finally to ensure temp directory cleanup
        // ========================================
        $archiveResult = null;
        $versionStats = null;
        $tempDirPath = null;
        $shouldReprocessExistingVersion = $shouldReprocessExistingVersion ?? false;
        $shouldProcessRenPy = $bestUpload &&
            $this->isStatsExtractionAllowed($game) &&
            ($shouldCreateVersion || $shouldReprocessExistingVersion) &&
            (! $game->game_engine || $game->game_engine === "Ren'Py" || $game->game_engine === 'unknown');

        echo "    [Version] Should process Ren'Py: " . ($shouldProcessRenPy ? 'yes' : 'no') .
             ' (bestUpload: ' . ($bestUpload ? 'yes' : 'no') .
             ', shouldCreate: ' . ($shouldCreateVersion ? 'yes' : 'no') .
             ', shouldReprocess: ' . ($shouldReprocessExistingVersion ? 'yes' : 'no') .
             ', paid: ' . ($game->is_paid ? 'yes' : 'no') .
             ', statsDisabled: ' . ($game->is_stats_extraction_disabled ? 'yes' : 'no') .
             ', engine: ' . ($game->game_engine ?: 'null') . ")\n";

        if ($shouldProcessRenPy) {
            try {
                $archiveService = app(GameArchiveService::class);

                if ($shouldReprocessExistingVersion) {
                    echo "    [Version] Reprocessing from stored archive repository...\n";
                    $storedArchivePath = $archiveService->getStoredArchive($game->id, $existingVersion->id);
                    if ($storedArchivePath === null) {
                        echo "    [Version] No stored archive found for existing version\n";
                    } else {
                        $versionStats = $archiveService->processArchive($storedArchivePath);
                    }
                } else {
                    echo "    [Version] Downloading and processing new game archive to temp location...\n";

                    // Download and process to TEMP - no version ID needed yet
                    $archiveResult = $archiveService->downloadAndProcessToTemp(
                        $game->getPrimaryUrl(),
                        $bestUpload->filename,
                        $bestUpload->id,
                        $game->id
                    );

                    // Store temp directory path for cleanup
                    $tempDirPath = $archiveResult['temp_dir'] ?? null;

                    if (isset($archiveResult['stats']) && $archiveResult['stats']) {
                        $versionStats = $archiveResult['stats'];
                    }
                }

                // Extract stats if available
                if ($versionStats) {
                    echo "    [Version] Stats extracted successfully from archive\n";
                } else {
                    echo "    [Version] No stats extracted from archive\n";
                    if ($archiveService->getLastProcessingError()) {
                        echo "    [Version] Stats extraction reason: {$archiveService->getLastProcessingError()}\n";
                    }
                }
            } catch (Exception $e) {
                Log::error('Failed to process game archive', [
                    'game_id' => $game->id,
                    'version' => $newVersion,
                    'error' => $e->getMessage(),
                ]);
                echo "    [Version] Error processing archive: {$e->getMessage()}\n";
                // Continue without stats - cleanup will happen in finally block
            }
        }

        // ========================================
        // PHASE 3: Save all data (NO TRANSACTION - caller will handle)
        // All database writes happen here - caller must wrap in transaction
        // Wrapped in try-finally to ensure temp file cleanup
        // ========================================
        try {
            echo "    [Version] Saving all data (caller's transaction)\n";

            // Update game uploads (in-memory only - caller will save)
            $game->uploads = $seenUploads;
            echo "    [Version] Game uploads updated in memory\n";

            // Determine what version to create
            $gameVersion = null;

            // Case 1: Creating a new version with actual data
            // We never update existing versions, only create new ones
            if ($shouldCreateVersion && $newVersion) {
                $versionValues = [
                    'version' => $newVersion,
                    'devlog' => $this->getDevlogLink($game),
                    'is_windows' => $isWindows,
                    'is_linux' => $isLinux,
                    'is_mac' => $isMac,
                    'is_android' => $isAndroid,
                    'is_web' => $isWeb,
                    'published_at' => $uploadTimestamp,
                ];

                $gameVersion = $game->gameVersions()->create($versionValues);
                echo "    [Version] Created new version record with ID: {$gameVersion->id}\n";

                // Copy language availability from previous version if needed
                $previousVersion = $game->gameVersions()
                    ->where('id', '!=', $gameVersion->id)
                    ->whereHas('supportedLanguages', function ($query) {
                        $query->where('is_available', false);
                    })
                    ->orderBy('published_at', 'desc')
                    ->first();

                if ($previousVersion) {
                    VersionSupportedLanguage::copyAvailabilitySettings($previousVersion->id, $gameVersion->id);
                }

                // Save stats if we have them
                if ($versionStats) {
                    echo "    [Version] Setting game engine in memory\n";
                    $game->game_engine = "Ren'Py";
                    echo "    [Version] Game engine set (will be saved by caller)\n";

                    echo "    [Version] Saving version stats...\n";
                    $statsService = app(GameStatsService::class);
                    $statsService->saveVersionStats($gameVersion, $versionStats,
                        $game->source_language_id, $game);
                    echo "    [Version] Version stats saved\n";
                } else {
                    // No stats - copy language support from previous version
                    echo "    [Version] No stats, copying language support\n";
                    $this->copyLanguageSupport($game, $gameVersion);
                }

                $this->persistArchiveResultToRepository($game, $gameVersion, $archiveResult, false);

                // Now set is_latest = true AFTER dialogue lines exist
                // This triggers the GameVersion observer to index the dialogue lines
                echo "    [Version] Setting version as latest\n";
                $gameVersion->is_latest = true;
                $gameVersion->save();
                echo "    [Version] Version marked as latest\n";
            }
            // Case 2: Force reprocessing stats for an existing version
            elseif ($shouldReprocessExistingVersion) {
                echo "    [Version] Reprocessing stats for existing version: {$existingVersion->version} (ID: {$existingVersion->id})\n";

                if ($versionStats) {
                    $game->game_engine = "Ren'Py";
                    echo "    [Version] Game engine set (will be saved by caller)\n";

                    echo "    [Version] Saving version stats to existing version...\n";
                    $statsService = app(GameStatsService::class);
                    $statsService->saveVersionStats($existingVersion, $versionStats,
                        $game->source_language_id, $game);
                    echo "    [Version] Version stats saved to existing version\n";
                } else {
                    echo "    [Version] No stats extracted for existing version\n";
                }

                $this->persistArchiveResultToRepository($game, $existingVersion, $archiveResult, true);

                $gameVersion = $existingVersion;
            }
            // Case 3: Game had no versions at start and we couldn't create a real version
            // Create a fallback "Unknown" version so the game has at least one version
            elseif ($hadNoVersions) {
                echo "    [Version] Creating fallback Unknown version\n";
                $gameVersion = $game->gameVersions()->create([
                    'version' => 'Unknown',
                    'devlog' => $this->getDevlogLink($game),
                    'is_windows' => $isWindows,
                    'is_linux' => $isLinux,
                    'is_mac' => $isMac,
                    'is_android' => $isAndroid,
                    'is_web' => $isWeb,
                    'published_at' => $game->initially_published_at ?? now(),
                ]);

                // Set is_latest separately since it's not fillable
                $gameVersion->is_latest = true;
                $gameVersion->save();
                echo "    [Version] Fallback version created with ID: {$gameVersion->id}\n";
            }

            // Update platform flags on latest version if no new version was created
            // Platform flags can change over time (e.g., Android build added later)
            // But we only update if we DIDN'T just create a new version
            if (! $shouldCreateVersion && ! $hadNoVersions) {
                $this->updateLatestVersionPlatformFlags($game, $isWindows, $isLinux, $isMac, $isAndroid, $isWeb);
            }

            echo "    [Version] All version data saved\n";
        } finally {
            // Always clean up temp directory if it still exists
            // (if moveFromTempToStorage succeeded, $tempDirPath will be null)
            if ($tempDirPath && File::exists($tempDirPath)) {
                try {
                    File::deleteDirectory($tempDirPath);
                    echo "    [Version] Cleaned up temp directory: {$tempDirPath}\n";
                } catch (Exception $e) {
                    Log::warning('Failed to clean up temp directory', [
                        'temp_dir' => $tempDirPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function updateLatestVersionPlatformFlags(
        Game $game,
        bool $isWindows,
        bool $isLinux,
        bool $isMac,
        bool $isAndroid,
        bool $isWeb
    ): void {
        $latestVersion = $game->gameVersions()->where('is_latest', true)->first();
        if (! $latestVersion) {
            return;
        }

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

        if ($platformsChanged) {
            echo "    [Version] Platform flags changed on existing version, saving\n";
            $latestVersion->save();
            echo "    [Version] Platform flags updated\n";
        }
    }

    /**
     * Refresh all metadata for the game from its itch.io page
     *
     * @throws BindingResolutionException
     * @throws Throwable
     */
    public function refreshMetadata(Game $game, ?string $originalThumbUrl = null, ?array $originalScreenshots = null): void
    {
        $response = $this->getCachedResponse($game, $game->getPrimaryUrl(), [], true);
        app(ItchGameMetadataRefresher::class)->refresh($game, $response['body'], $originalThumbUrl, $originalScreenshots);
    }

    /**
     * Clear HTTP cache for a game
     */
    public function clearHttpCache(Game $game): void
    {
        unset(self::$httpCache[$game->id]);
    }

    /**
     * Load full game details from itch.io
     *
     * @throws BindingResolutionException
     * @throws DateMalformedStringException
     * @throws GuzzleException
     * @throws Throwable
     */
    private function loadFullDetailsItchio(Game $game): void
    {
        try {
            $originalThumbUrl = $game->thumb_url;
            $originalScreenshots = $game->screenshots;

            Log::info('GameDataSync: Refreshing base info', ['game_id' => $game->id]);
            echo "    [Sync] Refreshing base info...\n";
            $this->refreshBaseInfo($game);
            Log::info('GameDataSync: Base info refreshed', ['game_id' => $game->id]);
            echo "    [Sync] Base info refreshed\n";

            sleep(10);

            Log::info('GameDataSync: Refreshing version', ['game_id' => $game->id]);
            echo "    [Sync] Refreshing version...\n";
            $this->refreshVersion($game);
            Log::info('GameDataSync: Version refreshed', ['game_id' => $game->id]);
            echo "    [Sync] Version refreshed\n";

            sleep(10);

            Log::info('GameDataSync: Refreshing metadata', ['game_id' => $game->id]);
            echo "    [Sync] Refreshing metadata...\n";
            $this->refreshMetadata($game, $originalThumbUrl, $originalScreenshots);
            Log::info('GameDataSync: Metadata refreshed', ['game_id' => $game->id]);
            echo "    [Sync] Metadata refreshed\n";

            $game->error = null;
        } catch (Exception $exception) {
            $game->error = $exception->getMessage();
            echo "    [Sync] ERROR: {$exception->getMessage()}\n";
            throw $exception;
        } finally {
            echo "    [Sync] Clearing HTTP cache...\n";
            $this->clearHttpCache($game);
            echo "    [Sync] Done\n";
        }
    }

    /**
     * Get the devlog link from the game's itch.io page
     */
    private function getDevlogLink(Game $game): ?string
    {
        try {
            // Use cached HTML to avoid duplicate requests
            $response = $this->getCachedResponse($game, $game->getPrimaryUrl(), [], true);
            $html = $response['body'];
            $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);

            $devlog = $doc->querySelector('section#devlog');
            if ($devlog) {
                $devlogLinks = $devlog->querySelectorAll('a');
                foreach ($devlogLinks as $index => $link) {
                    if ($index === 0) {
                        return $link->getAttribute('href');
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to get devlog link', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Get cached HTTP response
     */
    private function getCachedResponse(Game $game, string $url, array $options = [], bool $anonymous = false): array
    {
        $urlKey = md5($url . serialize($options) . ($anonymous ? 'anon' : 'auth'));

        if (! isset(self::$httpCache[$game->id][$urlKey])) {
            $itchClient = App::make(ItchHttpClientService::class);
            $response = $itchClient->get($url, $options, $anonymous);
            self::$httpCache[$game->id][$urlKey] = [
                'body' => $response->getBody()->getContents(),
                'status_code' => $response->getStatusCode(),
            ];
        }

        return self::$httpCache[$game->id][$urlKey];
    }

    /**
     * Copy language support from previous version
     */
    private function copyLanguageSupport(Game $game, GameVersion $gameVersion): void
    {
        app(GameVersionLanguageSupportCopier::class)->copy($game, $gameVersion);
    }

    /**
     * @param  array{temp_path?: string, filename?: string}|null  $archiveResult
     */
    private function persistArchiveResultToRepository(Game $game, GameVersion $version, ?array $archiveResult, bool $force): void
    {
        if (
            ! $archiveResult ||
            ! isset($archiveResult['temp_path'], $archiveResult['filename']) ||
            ! File::exists($archiveResult['temp_path'])
        ) {
            return;
        }

        try {
            $archiveService = app(GameArchiveService::class);
            $archiveService->moveFromTempToStorage(
                $archiveResult['temp_path'],
                $archiveResult['filename'],
                $game->id,
                $version->id,
                false
            );
            echo "    [Version] Archive staged for version {$version->id}\n";

            $result = app(GameVersionArchiveRepositoryService::class)->persistStoredArchive($game, $version, $force);
            if ($result['status'] === 'persisted') {
                $build = isset($result['build_id']) ? " build #{$result['build_id']}" : '';
                echo "    [Version] Archive persisted to DenKit Stash {$result['target']}{$build}\n";

                return;
            }

            if ($result['status'] === 'skipped') {
                echo '    [Version] DenKit Stash persistence skipped: ' . ($result['reason'] ?? 'already persisted') . "\n";
            }
        } catch (Throwable $throwable) {
            Log::error('Failed to persist game version archive to DenKit Stash', [
                'game_id' => $game->id,
                'version_id' => $version->id,
                'error' => $throwable->getMessage(),
                'exception' => $throwable,
            ]);
            echo "    [Version] Error persisting archive to DenKit Stash: {$throwable->getMessage()}\n";
        }
    }

    /**
     * @param  array<int, Upload>  $candidateUploads
     */
    private function hasOnlyDemoProcessableUploads(array $candidateUploads): bool
    {
        return $candidateUploads !== [] && collect($candidateUploads)->every(fn (Upload $upload) => $upload->isDemo());
    }

    private function isStatsExtractionAllowed(Game $game): bool
    {
        return ! $game->is_paid && ! $game->is_stats_extraction_disabled;
    }

    private function checkForNoindexTag(HTMLDocument $doc): bool
    {
        return app(ItchGameMetadataRefresher::class)->hasNoindexTag($doc);
    }

    private function processPendingGameJams(Game $game): void
    {
        app(GamePendingAssociationProcessor::class)->processGameJams($game);
    }

    private function processPendingTags(Game $game): void
    {
        app(GamePendingAssociationProcessor::class)->processTags($game);
    }

    /**
     * Compare screenshot arrays by their source URLs only.
     * This ignores optimized variants when determining if screenshots have actually changed.
     *
     * @param  array|null  $screenshots1  First screenshots array
     * @param  array|null  $screenshots2  Second screenshots array
     * @return bool True if the screenshot source URLs are different
     */
    private function screenshotUrlsChanged(?array $screenshots1, ?array $screenshots2): bool
    {
        return app(GameMetadataImageProcessor::class)->screenshotUrlsChanged($screenshots1, $screenshots2);
    }

    private function needsScreenshotProcessing(?array $screenshots, ?array $originalScreenshots): bool
    {
        return app(GameMetadataImageProcessor::class)->needsScreenshotProcessing($screenshots, $originalScreenshots);
    }

    private function extractScreenshotUrls(?array $screenshots): array
    {
        return app(GameMetadataImageProcessor::class)->extractScreenshotUrls($screenshots);
    }
}
