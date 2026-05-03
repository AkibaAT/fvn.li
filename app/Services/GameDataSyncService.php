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
use Illuminate\Support\Facades\DB;
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

        // Platform flags for the latest version
        $isWindows = false;
        $isLinux = false;
        $isMac = false;
        $isAndroid = false;
        $isWeb = false;

        // Process uploads data to detect changes
        if (isset($uploadsData['uploads'])) {
            echo '    [Version] Processing ' . count($uploadsData['uploads']) . " uploads\n";
            foreach ($uploadsData['uploads'] as $upload) {
                $fileId = (int) $upload['id'];
                $currentFilename = $upload['filename'] ?? '';
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
                    ($seenUploads[$fileId]['filename'] ?? '') !== $currentFilename ||
                    ($seenUploads[$fileId]['md5_hash'] ?? null) !== $currentMd5 ||
                    ($seenUploads[$fileId]['updated_at'] ?? null) !== $currentUpdatedAt ||
                    ($seenUploads[$fileId]['build_id'] ?? null) !== $currentBuildId ||
                    ($seenUploads[$fileId]['build_updated_at'] ?? null) !== $currentBuildUpdatedAt
                );

                if ($isNewOrChanged || $force) {
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
                if (($upload['type'] ?? '') === 'html') {
                    $isWeb = true;
                }
            }
        }

        // Select best upload from candidates using Upload model's sorting logic
        $bestUpload = Upload::getBest(collect($candidateUploads));
        if ($bestUpload) {
            echo "    [Version] Selected best upload: {$bestUpload->filename}\n";
        } else {
            echo "    [Version] No processable uploads found\n";
        }

        if ($this->hasOnlyDemoProcessableUploads($candidateUploads)) {
            $game->is_stats_extraction_disabled = true;
            echo "    [Version] Only demo archives are processable; skipping stats extraction\n";
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
        // PHASE 2: Download and process archive to TEMP (NO TRANSACTION - no locks held)
        // This can take 10+ seconds, so we do it BEFORE the transaction
        // Wrapped in try-finally to ensure temp directory cleanup
        // ========================================
        $archiveResult = null;
        $versionStats = null;
        $tempDirPath = null;
        $shouldReprocessExistingVersion = $shouldReprocessExistingVersion ?? false;
        $shouldProcessRenPy = $bestUpload &&
            ! $game->is_stats_extraction_disabled &&
            ($shouldCreateVersion || $shouldReprocessExistingVersion) &&
            (! $game->game_engine || $game->game_engine === "Ren'Py" || $game->game_engine === 'unknown');

        echo "    [Version] Should process Ren'Py: " . ($shouldProcessRenPy ? 'yes' : 'no') .
             ' (bestUpload: ' . ($bestUpload ? 'yes' : 'no') .
             ', shouldCreate: ' . ($shouldCreateVersion ? 'yes' : 'no') .
             ', shouldReprocess: ' . ($shouldReprocessExistingVersion ? 'yes' : 'no') .
             ', statsDisabled: ' . ($game->is_stats_extraction_disabled ? 'yes' : 'no') .
             ', engine: ' . ($game->game_engine ?: 'null') . ")\n";

        if ($shouldProcessRenPy) {
            try {
                echo "    [Version] Downloading and processing game archive to temp location...\n";
                $archiveService = app(GameArchiveService::class);

                // Download and process to TEMP - no version ID needed yet
                $archiveResult = $archiveService->downloadAndProcessToTemp(
                    $game->getPrimaryUrl(),
                    $bestUpload->filename,
                    $bestUpload->id,
                    $game->id
                );

                // Store temp directory path for cleanup
                $tempDirPath = $archiveResult['temp_dir'] ?? null;

                // Extract stats if available
                if (isset($archiveResult['stats']) && $archiveResult['stats']) {
                    $versionStats = $archiveResult['stats'];
                    echo "    [Version] Stats extracted successfully from temp archive\n";
                } else {
                    echo "    [Version] No stats extracted from archive\n";
                }
            } catch (Exception $e) {
                Log::error('Failed to download/process game archive to temp', [
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
                    ->latest('published_at')
                    ->first();

                if ($previousVersion) {
                    VersionSupportedLanguage::copyAvailabilitySettings($previousVersion->id, $gameVersion->id);
                }

                // Note: We no longer store archives permanently - they're processed and deleted

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

                // Now set is_latest = true AFTER dialogue lines exist
                // This triggers the GameVersion observer to index the dialogue lines
                echo "    [Version] Setting version as latest\n";
                $gameVersion->is_latest = true;
                $gameVersion->save();
                echo "    [Version] Version marked as latest\n";
            }
            // Case 2: Force reprocessing stats for an existing version
            elseif ($shouldReprocessExistingVersion && $versionStats) {
                echo "    [Version] Reprocessing stats for existing version: {$existingVersion->version} (ID: {$existingVersion->id})\n";

                $game->game_engine = "Ren'Py";
                echo "    [Version] Game engine set (will be saved by caller)\n";

                echo "    [Version] Saving version stats to existing version...\n";
                $statsService = app(GameStatsService::class);
                $statsService->saveVersionStats($existingVersion, $versionStats,
                    $game->source_language_id, $game);
                echo "    [Version] Version stats saved to existing version\n";

                // Store the archive permanently so reimport-version can reuse it
                if ($archiveResult && isset($archiveResult['temp_path']) && File::exists($archiveResult['temp_path'])) {
                    $archiveService = app(GameArchiveService::class);
                    $archiveService->moveFromTempToStorage(
                        $archiveResult['temp_path'],
                        $archiveResult['filename'],
                        $game->id,
                        $existingVersion->id,
                        false // don't delete temp - the finally block handles cleanup
                    );
                    echo "    [Version] Archive stored for version {$existingVersion->id}\n";
                }

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
        // ========================================
        // PHASE 1: Fetch metadata (NO TRANSACTION - no locks held)
        // ========================================
        $response = $this->getCachedResponse($game, $game->getPrimaryUrl(), [], true);
        $html = $response['body'];
        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        $extractor = app(ItchGameMetadataExtractor::class);

        // Store original values to detect changes. Full sync callers pass the
        // pre-sync media state because base info can update thumb_url first.
        $originalThumbUrl ??= $game->thumb_url;
        $originalScreenshots ??= $game->screenshots;

        // Check for demo availability
        $extractor->checkForDemo($game, $doc);

        // Update status if not abandoned/canceled
        if (! in_array($game->status, ['Abandoned', 'Canceled'])) {
            $gameInfo = $doc->querySelector('div.game_info_panel_widget');
            if ($gameInfo) {
                $statusLinks = $gameInfo->querySelectorAll('a');
                foreach ($statusLinks as $index => $link) {
                    if ($index === 0) {
                        $game->status = $link->textContent;
                        break;
                    }
                }
            }
        }

        $extractor->extractFullDescription($game, $doc, app(ItchHtmlProcessor::class));
        $extractor->extractScreenshots($game, $doc);

        // Always sync custom CSS (styling should be updated regardless of custom page status)
        $extractor->extractCustomCss($game, $html, app(ItchCssProcessor::class));

        // Get game jam information
        $extractor->extractGameJamInfo($game, $doc);

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
                        $game->syncTagsFromString($value);
                        break;
                    case 'Author':
                    case 'Authors':
                        $game->authors = '';
                        foreach ($cells[1]->querySelectorAll('a') as $author) {
                            if ($game->authors !== '') {
                                $game->authors .= ',<br>';
                            }
                            $game->authors .= sprintf(
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
        $game->is_nsfw = $nsfw !== null;

        // Check delisted status (robots noindex meta tag)
        $game->is_delisted = $this->checkForNoindexTag($doc);

        // ========================================
        // PHASE 2: Process images (NO TRANSACTION - downloads can take seconds!)
        // ========================================
        $imageService = app(ImageProcessingService::class);

        $screenshotsChanged = $this->screenshotUrlsChanged($game->screenshots, $originalScreenshots);

        // Process screenshots if source URLs changed, or if existing rows never got optimized.
        if (! empty($game->screenshots) && ($screenshotsChanged || $this->hasUnoptimizedScreenshots($game->screenshots))) {
            try {
                echo "    [Metadata] Screenshots need processing before save...\n";
                $imageService->processGameScreenshots($game);
                echo "    [Metadata] Screenshots processed successfully\n";
            } catch (Exception $e) {
                Log::error('Failed to process screenshots during metadata refresh', [
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);
                // Continue anyway - we'll save the URLs at least
            }
        }

        // Process thumbnail if it changed OR if we have a URL but no processed thumbnails
        $needsThumbnailProcessing = (
            ($game->thumb_url !== $originalThumbUrl && $game->thumb_url) ||
            ($game->thumb_url && empty($game->optimized_thumbnails))
        );

        if ($needsThumbnailProcessing) {
            try {
                echo "    [Metadata] Thumbnail needs processing...\n";
                // Clear old thumbnails first
                if ($game->optimized_thumbnails) {
                    $game->clearOptimizedThumbnails();
                }
                $imageService->processGameThumbnail($game);
                echo "    [Metadata] Thumbnail processed successfully\n";
            } catch (Exception $e) {
                Log::error('Failed to process thumbnail during metadata refresh', [
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);
                // Continue anyway
            }
        } elseif (! $game->thumb_url && ! empty($game->screenshots) && $screenshotsChanged) {
            // No thumbnail but have screenshots - process first screenshot as thumbnail
            try {
                echo "    [Metadata] No thumbnail, processing first screenshot as fallback...\n";
                if ($game->optimized_thumbnails) {
                    $game->clearOptimizedThumbnails();
                }
                $imageService->processGameThumbnail($game);
                echo "    [Metadata] Thumbnail fallback processed successfully\n";
            } catch (Exception $e) {
                Log::error('Failed to process thumbnail fallback during metadata refresh', [
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ========================================
        // PHASE 3: Prepare data for saving (NO TRANSACTION - caller will handle)
        // ========================================
        // NOTE: We do NOT save here - the caller will save in their transaction
        // This keeps all DB writes in ONE transaction at the top level
        Log::info('Game metadata prepared for saving', [
            'game_id' => $game->id,
            'game_name' => $game->name,
            'has_custom_css' => isset($game->custom_css),
            'custom_css_length' => strlen($game->custom_css ?? ''),
            'dirty_attributes' => $game->getDirty(),
        ]);
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
        // Find the previous version with language support
        $previousVersion = $game->gameVersions()
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
            if ($game->source_language_id) {
                $gameVersion->addSupportedLanguage($game->source_language_id);
            } else {
                // Fallback to English only if no source language is defined
                $gameVersion->addSupportedLanguage('eng');
            }
        }
    }

    /**
     * @param  array<int, Upload>  $candidateUploads
     */
    private function hasOnlyDemoProcessableUploads(array $candidateUploads): bool
    {
        return $candidateUploads !== [] && collect($candidateUploads)->every(fn (Upload $upload) => $upload->isDemo());
    }

    /**
     * Check if the page has a robots noindex meta tag, indicating the game is delisted
     */
    private function checkForNoindexTag(HTMLDocument $doc): bool
    {
        // Look for meta robots tag with noindex
        $metaTags = $doc->querySelectorAll('meta[name="robots"]');
        foreach ($metaTags as $meta) {
            $content = strtolower($meta->getAttribute('content') ?? '');
            if (str_contains($content, 'noindex')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process any pending game jam associations
     * This should be called after the game is saved
     */
    private function processPendingGameJams(Game $game): void
    {
        // Check if we have any pending game jam associations
        if (empty($game->pendingGameJamId)) {
            return;
        }

        // Make sure the game has been saved and has an ID
        if (! $game->exists || ! $game->id) {
            Log::warning('Cannot process pending game jams - game not saved', [
                'game_name' => $game->name,
                'game_id' => $game->id,
                'exists' => $game->exists,
            ]);

            return;
        }

        // Process each pending game jam
        foreach ($game->pendingGameJamId as $jamId) {
            // Check if the association already exists
            if (! $game->gameJams()->where('game_jam_id', $jamId)->exists()) {
                // Create the association
                $game->gameJams()->attach($jamId);

                Log::info('Associated game with game jam', [
                    'game_id' => $game->id,
                    'game_name' => $game->name,
                    'jam_id' => $jamId,
                ]);

                GameFilterService::clearCache();

                if ($game->is_visible) {
                    $game->loadMissing(['tags', 'gameJams', 'gameVersions']);
                    $game->searchable();
                }
            }
        }

        // Clear the pending list
        $game->pendingGameJamId = [];
    }

    /**
     * Process any pending tag associations
     * This should be called after the game is saved
     */
    private function processPendingTags(Game $game): void
    {
        // Check if we have any pending tag associations
        if (empty($game->pendingTagIds)) {
            return;
        }

        // Make sure the game has been saved and has an ID
        if (! $game->exists || ! $game->id) {
            Log::warning('Cannot process pending tags - game not saved', [
                'game_name' => $game->name,
                'game_id' => $game->id,
                'exists' => $game->exists,
            ]);

            return;
        }

        // Sync the tags
        $game->tags()->sync($game->pendingTagIds);

        Log::info('Synced pending tags for game', [
            'game_id' => $game->id,
            'game_name' => $game->name,
            'tag_ids' => $game->pendingTagIds,
        ]);

        // Clear the pending list
        $game->pendingTagIds = [];
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
        // Extract just the source URLs from each array
        $urls1 = $this->extractScreenshotUrls($screenshots1);
        $urls2 = $this->extractScreenshotUrls($screenshots2);

        return $urls1 !== $urls2;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $screenshots
     */
    private function hasUnoptimizedScreenshots(?array $screenshots): bool
    {
        if (empty($screenshots)) {
            return false;
        }

        foreach ($screenshots as $screenshot) {
            if (empty($screenshot['optimized'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract source URLs from a screenshots array, ignoring optimized data.
     *
     * @param  array|null  $screenshots  Screenshots array
     * @return array Sorted array of source URLs
     */
    private function extractScreenshotUrls(?array $screenshots): array
    {
        if (empty($screenshots)) {
            return [];
        }

        $urls = [];
        foreach ($screenshots as $screenshot) {
            if (isset($screenshot['url'])) {
                $urls[] = $screenshot['url'];
            }
        }

        return $urls;
    }
}
