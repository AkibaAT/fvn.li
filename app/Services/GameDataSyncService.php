<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DenKitStashUnavailableException;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\VersionSupportedLanguage;
use App\Services\Concerns\ReportsProgress;
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
    use ReportsProgress;

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
        $this->progress("  [Version] Starting version refresh for game: {$game->name}\n");

        // Track if game had no versions at start
        $hadNoVersions = ! $game->gameVersions()->exists();

        // External requests complete before database writes begin.
        $this->progress("    [Version] Fetching uploads data from itch.io\n");

        $itchClient = App::make(ItchHttpClientService::class);

        $url = "https://api.itch.io/games/{$game->itch_id}/uploads";

        $response = $itchClient->get($url);

        if ($response->getStatusCode() === 404 || $response->getStatusCode() === 400) {
            $game->is_visible = false;
            $game->save();

            return;
        }

        $uploadsData = json_decode($response->getBody()->getContents(), true);
        if (! isset($uploadsData['uploads'])) {
            // No uploads data - if game has no versions, we need to create a fallback in the transaction
            if ($hadNoVersions) {
                $this->progress("    [Version] No uploads found, but game has no versions - will create fallback\n");
                // Don't return yet - we'll create fallback in Phase 3
            } else {
                $this->progress("    [Version] No uploads found\n");

                return;
            }
        }

        $seenUploads = $game->uploads ?: [];
        $hasChanges = false;
        $candidateUploads = [];
        $platforms = [
            'windows' => false,
            'linux' => false,
            'mac' => false,
            'android' => false,
            'web' => false,
        ];

        if (isset($uploadsData['uploads'])) {
            $this->progress('    [Version] Processing ' . count($uploadsData['uploads']) . " uploads\n");
            $uploadAnalysis = app(GameUploadAnalyzer::class)->analyze($uploadsData['uploads'], $seenUploads, $force);
            $seenUploads = $uploadAnalysis['seenUploads'];
            $hasChanges = $uploadAnalysis['hasChanges'];
            $candidateUploads = $uploadAnalysis['candidateUploads'];
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
            $this->progress("    [Version] Selected best upload: {$bestUpload->filename}\n");
        } else {
            $this->progress("    [Version] No processable uploads found\n");
        }

        if ($game->is_paid) {
            $this->progress("    [Version] Paid game; skipping stats extraction\n");
        }

        // Exit early if no changes detected and game already has versions
        if (! $hasChanges && ! $force && ! $hadNoVersions) {
            if (! empty($uploadsData['uploads'])) {
                $this->updateLatestVersionPlatformFlags($game, $isWindows, $isLinux, $isMac, $isAndroid, $isWeb);
            }
            $this->progress("    [Version] No changes detected\n");

            return;
        }

        $newVersion = null;
        $uploadTimestamp = null;
        $shouldCreateVersion = false;

        if ($bestUpload) {
            $versionParserService = app(GameVersionParser::class);
            $newVersion = $versionParserService->extractVersion($seenUploads[$bestUpload->id], true);
            $uploadTimestamp = $bestUpload->updatedAt;

            $this->progress('    [Version] Extracted version: ' . ($newVersion ?: '(empty)') . "\n");

            $existingVersion = $game->gameVersions()
                ->where('version', $newVersion)
                ->first();

            // Only create version if it doesn't exist
            $shouldCreateVersion = ! $existingVersion;

            // When force is enabled and version exists, we should reprocess stats for that version
            $shouldReprocessExistingVersion = $force && $existingVersion;

            $this->progress('    [Version] Should create version: ' . ($shouldCreateVersion ? 'yes' : 'no') . ' (existing: ' . ($existingVersion ? 'yes' : 'no') . ', force: ' . ($force ? 'yes' : 'no') . ")\n");
            if ($shouldReprocessExistingVersion) {
                $this->progress("    [Version] Force mode: will reprocess stats for existing version\n");
            }
        }

        // Archive work stays outside the caller's transaction and always cleans up temporary files.
        $archiveResult = null;
        $versionStats = null;
        $tempDirPath = null;
        $shouldReprocessExistingVersion = $shouldReprocessExistingVersion ?? false;
        $shouldProcessRenPy = $bestUpload &&
            $this->isStatsExtractionAllowed($game) &&
            ($shouldCreateVersion || $shouldReprocessExistingVersion) &&
            (! $game->game_engine || $game->game_engine === "Ren'Py" || $game->game_engine === 'unknown');

        $this->progress("    [Version] Should process Ren'Py: " . ($shouldProcessRenPy ? 'yes' : 'no') .
             ' (bestUpload: ' . ($bestUpload ? 'yes' : 'no') .
             ', shouldCreate: ' . ($shouldCreateVersion ? 'yes' : 'no') .
             ', shouldReprocess: ' . ($shouldReprocessExistingVersion ? 'yes' : 'no') .
             ', paid: ' . ($game->is_paid ? 'yes' : 'no') .
             ', statsDisabled: ' . ($game->is_stats_extraction_disabled ? 'yes' : 'no') .
             ', engine: ' . ($game->game_engine ?: 'null') . ")\n");

        if ($shouldProcessRenPy) {
            try {
                $archiveService = app(GameArchiveService::class);

                if ($shouldReprocessExistingVersion) {
                    $this->progress("    [Version] Reprocessing from stored archive repository...\n");
                    // The stash is the source of truth for reprocessing. Drop any
                    // archive a previous run left staged locally so it cannot shadow
                    // the stash copy and skip the stash lookup entirely.
                    app(GameVersionArchiveRepositoryService::class)->discardLocalArchive($game, $existingVersion);
                    $storedArchivePath = $archiveService->getStoredArchive($game->id, $existingVersion->id);
                    if ($storedArchivePath === null) {
                        // A stash lookup that errored aborts the refresh; only a
                        // genuine miss falls through to seed from the source.
                        $lookupFailure = $archiveService->getLastArchiveLookupFailure();
                        if ($lookupFailure !== null) {
                            throw new DenKitStashUnavailableException(
                                "DenKit Stash is unavailable: {$lookupFailure->getMessage()}",
                                0,
                                $lookupFailure
                            );
                        }

                        $this->progress("    [Version] No DenKit archive found for existing version; downloading itch.io archive to seed DenKit\n");
                        $archiveLookupError = $archiveService->getLastArchiveLookupError();
                        if ($archiveLookupError) {
                            $this->progress("    [Version] Archive lookup reason: {$archiveLookupError}\n");
                        }

                        $archiveResult = $archiveService->downloadAndProcessToTemp(
                            $game->getPrimaryUrl(),
                            $bestUpload->filename,
                            $bestUpload->id,
                            $game->id
                        );

                        $tempDirPath = $archiveResult['temp_dir'] ?? null;

                        if (isset($archiveResult['stats']) && $archiveResult['stats']) {
                            $versionStats = $archiveResult['stats'];
                        }
                    } else {
                        $versionStats = $archiveService->processArchive($storedArchivePath);
                    }
                } else {
                    $this->progress("    [Version] Downloading and processing new game archive to temp location...\n");

                    // Download and process to TEMP - no version ID needed yet
                    $archiveResult = $archiveService->downloadAndProcessToTemp(
                        $game->getPrimaryUrl(),
                        $bestUpload->filename,
                        $bestUpload->id,
                        $game->id
                    );

                    $tempDirPath = $archiveResult['temp_dir'] ?? null;

                    if (isset($archiveResult['stats']) && $archiveResult['stats']) {
                        $versionStats = $archiveResult['stats'];
                    }
                }

                if ($versionStats) {
                    $this->progress("    [Version] Stats extracted successfully from archive\n");
                } else {
                    $this->progress("    [Version] No stats extracted from archive\n");
                    if ($archiveService->getLastProcessingError()) {
                        $this->progress("    [Version] Stats extraction reason: {$archiveService->getLastProcessingError()}\n");
                    }
                }
            } catch (Exception $e) {
                if ($e instanceof DenKitStashUnavailableException) {
                    throw $e;
                }

                Log::error('Failed to process game archive', [
                    'game_id' => $game->id,
                    'version' => $newVersion,
                    'error' => $e->getMessage(),
                ]);
                $this->progress("    [Version] Error processing archive: {$e->getMessage()}\n");
                // Continue without stats - cleanup will happen in finally block
            }
        }

        // The caller owns the transaction around all database writes in this block.
        try {
            $this->progress("    [Version] Saving all data (caller's transaction)\n");

            $game->uploads = $seenUploads;
            $this->progress("    [Version] Game uploads updated in memory\n");

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
                $this->progress("    [Version] Created new version record with ID: {$gameVersion->id}\n");

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

                if ($versionStats) {
                    $this->progress("    [Version] Setting game engine in memory\n");
                    $game->game_engine = "Ren'Py";
                    $this->progress("    [Version] Game engine set (will be saved by caller)\n");

                    $this->progress("    [Version] Saving version stats...\n");
                    $statsService = app(GameStatsService::class);
                    $statsService->saveVersionStats($gameVersion, $versionStats,
                        $game->source_language_id, $game);
                    $this->progress("    [Version] Version stats saved\n");
                } else {
                    // No stats - copy language support from previous version
                    $this->progress("    [Version] No stats, copying language support\n");
                    $this->copyLanguageSupport($game, $gameVersion);
                }

                $this->persistArchiveResultToRepository($game, $gameVersion, $archiveResult, false);

                // This triggers the GameVersion observer to index the dialogue lines
                $this->progress("    [Version] Setting version as latest\n");
                $gameVersion->is_latest = true;
                $gameVersion->save();
                $this->progress("    [Version] Version marked as latest\n");
            }
            // Case 2: Force reprocessing stats for an existing version
            elseif ($shouldReprocessExistingVersion) {
                $this->progress("    [Version] Reprocessing stats for existing version: {$existingVersion->version} (ID: {$existingVersion->id})\n");

                if ($versionStats) {
                    $game->game_engine = "Ren'Py";
                    $this->progress("    [Version] Game engine set (will be saved by caller)\n");

                    $this->progress("    [Version] Saving version stats to existing version...\n");
                    $statsService = app(GameStatsService::class);
                    $statsService->saveVersionStats($existingVersion, $versionStats,
                        $game->source_language_id, $game);
                    $this->progress("    [Version] Version stats saved to existing version\n");
                } else {
                    $this->progress("    [Version] No stats extracted for existing version\n");
                }

                $this->persistArchiveResultToRepository($game, $existingVersion, $archiveResult, true);

                if ($archiveResult === null) {
                    // Reprocessed from an archive that was already in storage or
                    // restored from the stash, so nothing was staged for a push.
                    // The copy is still only needed for the duration of this run.
                    app(GameVersionArchiveRepositoryService::class)
                        ->discardLocalArchive($game, $existingVersion);
                }

                $gameVersion = $existingVersion;
            }
            // Case 3: Game had no versions at start and we couldn't create a real version
            elseif ($hadNoVersions) {
                $this->progress("    [Version] Creating fallback Unknown version\n");
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

                $gameVersion->is_latest = true;
                $gameVersion->save();
                $this->progress("    [Version] Fallback version created with ID: {$gameVersion->id}\n");
            }

            // Platform flags can change over time (e.g., Android build added later)
            // But we only update if we DIDN'T just create a new version
            if (! $shouldCreateVersion && ! $hadNoVersions) {
                $this->updateLatestVersionPlatformFlags($game, $isWindows, $isLinux, $isMac, $isAndroid, $isWeb);
            }

            $this->progress("    [Version] All version data saved\n");
        } finally {
            // Drop the extracted stats document; it is a temp file on disk.
            $versionStats?->release();

            // Always clean up temp directory if it still exists
            // (if moveFromTempToStorage succeeded, $tempDirPath will be null)
            if ($tempDirPath && File::exists($tempDirPath)) {
                try {
                    File::deleteDirectory($tempDirPath);
                    $this->progress("    [Version] Cleaned up temp directory: {$tempDirPath}\n");
                } catch (Exception $e) {
                    Log::warning('Failed to clean up temp directory', [
                        'temp_dir' => $tempDirPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
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
            $this->progress("    [Version] Platform flags changed on existing version, saving\n");
            $latestVersion->save();
            $this->progress("    [Version] Platform flags updated\n");
        }
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
            $this->progress("    [Sync] Refreshing base info...\n");
            $this->refreshBaseInfo($game);
            Log::info('GameDataSync: Base info refreshed', ['game_id' => $game->id]);
            $this->progress("    [Sync] Base info refreshed\n");

            sleep(10);

            Log::info('GameDataSync: Refreshing version', ['game_id' => $game->id]);
            $this->progress("    [Sync] Refreshing version...\n");
            $this->refreshVersion($game);
            Log::info('GameDataSync: Version refreshed', ['game_id' => $game->id]);
            $this->progress("    [Sync] Version refreshed\n");

            sleep(10);

            Log::info('GameDataSync: Refreshing metadata', ['game_id' => $game->id]);
            $this->progress("    [Sync] Refreshing metadata...\n");
            $this->refreshMetadata($game, $originalThumbUrl, $originalScreenshots);
            Log::info('GameDataSync: Metadata refreshed', ['game_id' => $game->id]);
            $this->progress("    [Sync] Metadata refreshed\n");

            $game->error = null;
        } catch (Exception $exception) {
            $game->error = $exception->getMessage();
            $this->progress("    [Sync] ERROR: {$exception->getMessage()}\n");
            throw $exception;
        } finally {
            $this->progress("    [Sync] Clearing HTTP cache...\n");
            $this->clearHttpCache($game);
            $this->progress("    [Sync] Done\n");
        }
    }

    private function getDevlogLink(Game $game): ?string
    {
        try {
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
            $this->progress("    [Version] Archive staged for version {$version->id}\n");
        } catch (Throwable $throwable) {
            Log::error('Failed to stage game version archive', [
                'game_id' => $game->id,
                'version_id' => $version->id,
                'error' => $throwable->getMessage(),
                'exception' => $throwable,
            ]);
            $this->progress("    [Version] Error staging archive: {$throwable->getMessage()}\n");

            return;
        }

        $repository = app(GameVersionArchiveRepositoryService::class);
        DB::afterCommit(function () use ($game, $version, $force, $repository): void {
            try {
                $result = $repository->persistStoredArchive($game, $version, $force);
                if ($result['status'] === 'persisted') {
                    $build = isset($result['build_id']) ? " build #{$result['build_id']}" : '';
                    $this->progress("    [Version] Archive persisted to DenKit Stash {$result['target']}{$build}\n");

                    return;
                }

                if ($result['status'] === 'skipped') {
                    $this->progress('    [Version] DenKit Stash persistence skipped: ' . ($result['reason'] ?? 'already persisted') . "\n");
                }
            } catch (Throwable $throwable) {
                Log::error('Failed to persist game version archive to DenKit Stash', [
                    'game_id' => $game->id,
                    'version_id' => $version->id,
                    'error' => $throwable->getMessage(),
                    'exception' => $throwable,
                ]);
                $this->progress("    [Version] Error persisting archive to DenKit Stash: {$throwable->getMessage()}\n");
            }
        });
    }

    private function isStatsExtractionAllowed(Game $game): bool
    {
        return ! $game->is_paid && ! $game->is_stats_extraction_disabled;
    }

    /**
     * Compare screenshot arrays by their source URLs only.
     * This ignores optimized variants when determining if screenshots have actually changed.
     *
     * @param  array|null  $screenshots1  First screenshots array
     * @param  array|null  $screenshots2  Second screenshots array
     * @return bool True if the screenshot source URLs are different
     */
}
