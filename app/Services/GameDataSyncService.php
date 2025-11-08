<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Tag;
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
            $this->refreshBaseInfo($game);
            sleep(10);
            $this->refreshVersion($game);
            sleep(10);
            $this->refreshMetadata($game);
            $game->error = null;
        } catch (Exception $exception) {
            $game->error = $exception->getMessage();
            throw $exception;
        } finally {
            $this->clearHttpCache($game);
        }
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
        if (!$game->isItchioGame()) {
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

    /**
     * Refresh version information for the game
     *
     * @throws GuzzleException|DateMalformedStringException
     * @throws Exception
     * @throws Throwable
     */
    public function refreshVersion(Game $game, bool $force = false): void
    {
        // Only itch.io games can be refreshed from itch.io API
        if (!$game->isItchioGame()) {
            throw new Exception("Cannot refresh versions for non-itch.io game: {$game->name} (platform: {$game->getPlatformName()})");
        }

        DB::beginTransaction();

        try {
            // Check if game has any existing versions - if not, create fallback immediately
            if (! $game->gameVersions()->exists()) {
                // Create fallback version and commit it immediately to ensure it persists
                $fallbackVersion = $game->gameVersions()->create([
                    'version' => 'Unknown',
                    'devlog' => $this->getDevlogLink($game),
                    'is_windows' => false,
                    'is_linux' => false,
                    'is_mac' => false,
                    'is_android' => false,
                    'is_web' => false,
                    'published_at' => $game->initially_published_at ?? now(),
                ]);

                // Set is_latest separately since it's not fillable
                $fallbackVersion->is_latest = true;
                $fallbackVersion->save();

                DB::commit();
                DB::beginTransaction();
                $force = true;
            }

            // Get the ItchHttpClientService
            $itchClient = App::make(ItchHttpClientService::class);

            $url = "https://api.itch.io/games/{$game->itch_id}/uploads";

            $response = $itchClient->get($url);

            if ($response->getStatusCode() === 404 || $response->getStatusCode() === 400) {
                $game->is_visible = false;
                $game->save();
                DB::commit();

                return;
            }

            $uploadsData = json_decode($response->getBody()->getContents(), true);
            if (! isset($uploadsData['uploads'])) {
                DB::rollBack();

                return;
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
                if ($upload['type'] === 'html') {
                    $isWeb = true;
                }
            }

            if (! $hasChanges && ! $force) {
                DB::rollBack();

                return;
            }

            $game->uploads = $seenUploads;

            $bestUpload = Upload::getBest(collect($candidateUploads));
            if ($bestUpload) {
                $versionParserService = app(GameVersionParser::class);
                $newVersion = $versionParserService->extractVersion($seenUploads[$bestUpload->id], true);
                $uploadTimestamp = $bestUpload->updatedAt;

                // Get existing version if any
                $existingVersion = $game->gameVersions()
                    ->where('version', $newVersion)
                    ->first();

                if (! $existingVersion) {
                    // Only create a new version if one doesn't exist with this version number
                    $existingUnknownVersion = $game->gameVersions()
                        ->where('version', 'Unknown')
                        ->first();

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

                    if ($existingUnknownVersion) {
                        $existingUnknownVersion->update($versionValues);
                        $gameVersion = $existingUnknownVersion;
                    } else {
                        $gameVersion = $game->gameVersions()->create($versionValues);
                    }

                    // Set is_latest separately since it's not fillable
                    $gameVersion->is_latest = true;
                    $gameVersion->save();

                    if (! $existingUnknownVersion) {
                        // Find previous version that has any unavailable languages
                        $previousVersion = $game->gameVersions()
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
                    // Process statistics if it's a Ren'Py game (only for new versions)
                    if (! $game->game_engine || $game->game_engine === "Ren'Py" || $game->game_engine === 'unknown') {
                        try {
                            $archiveService = app(GameArchiveService::class);
                            $statsService = app(GameStatsService::class);

                            // Download and process
                            $result = $archiveService->downloadAndProcess(
                                $game->getPrimaryUrl(),
                                $bestUpload->filename,
                                $bestUpload->id,
                                $game->id,
                                $gameVersion->id,
                                deleteAfterProcessing: true
                            );

                            // Check if we have valid stats
                            if (isset($result['stats']) && $result['stats']) {
                                $game->game_engine = "Ren'Py";
                                $game->save();

                                // Save the stats - pass $game as the game object for game-specific language mappings
                                $statsService->saveVersionStats($gameVersion, $result['stats'],
                                    $game->source_language_id, $game);
                            } else {
                                // Log that we couldn't extract stats but don't treat it as an error
                                Log::info('No stats extracted for game, copying language support from previous version',
                                    [
                                        'game_id' => $game->id,
                                        'version' => $newVersion,
                                    ]);

                                // Copy language support from previous version
                                $this->copyLanguageSupport($game, $gameVersion);
                            }
                        } catch (Exception $e) {
                            // Only log as an error if it's not related to stats extraction
                            Log::error('Failed to process game archive', [
                                'game_id' => $game->id,
                                'version' => $newVersion,
                                'error' => $e->getMessage(),
                            ]);

                            // Copy language support from previous version on error
                            $this->copyLanguageSupport($game, $gameVersion);
                        }
                    } else {
                        // For non-Ren'Py games, copy language support from previous version
                        $this->copyLanguageSupport($game, $gameVersion);
                    }
                } elseif ($force) {
                    // Version already exists, but force=true (e.g., devlog update)
                    // Update metadata only, don't reprocess stats
                    $gameVersion = $existingVersion;
                    $gameVersion->devlog = $this->getDevlogLink($game);
                    $gameVersion->is_windows = $isWindows;
                    $gameVersion->is_linux = $isLinux;
                    $gameVersion->is_mac = $isMac;
                    $gameVersion->is_android = $isAndroid;
                    $gameVersion->is_web = $isWeb;
                    $gameVersion->save();

                    Log::info('Version already exists, updating metadata only', [
                        'game_id' => $game->id,
                        'version' => $newVersion,
                        'existing_version_id' => $existingVersion->id,
                    ]);
                } else {
                    // Version already exists and no force - this shouldn't happen
                    // because we check $hasChanges earlier, but log it just in case
                    Log::warning('Version already exists and no changes detected', [
                        'game_id' => $game->id,
                        'version' => $newVersion,
                        'existing_version_id' => $existingVersion->id,
                    ]);
                }
            }

            // Always update platform flags of the latest version if they've changed
            // This ensures that newly detected platforms are updated even when no new version is created
            $latestVersion = $game->gameVersions()->where('is_latest', true)->first();
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

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Refresh all metadata for the game from its itch.io page
     *
     * @throws BindingResolutionException
     * @throws Throwable
     */
    public function refreshMetadata(Game $game): void
    {
        // Start a database transaction to ensure data consistency
        DB::beginTransaction();

        try {
            $response = $this->getCachedResponse($game, $game->getPrimaryUrl(), [], true);
            $html = $response['body'];
            $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);

            $extractor = app(ItchGameMetadataExtractor::class);

            // Check if price was already set from API data (more reliable than HTML scraping)
            $preserveApiPrice = isset($game->priceSetFromApi) && $game->priceSetFromApi === true;

            // Extract price information
            $extractor->extractPriceInformation($game, $doc, $preserveApiPrice);

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

            // Only sync description and screenshots if custom page is not enabled
            if (! $game->has_custom_page) {
                $extractor->extractFullDescription($game, $doc, app(ItchHtmlProcessor::class));
                $extractor->extractScreenshots($game, $doc);
            }

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
                            $this->syncTagsFromString($game, $value);
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

            // Save all metadata changes within the transaction
            Log::info('About to save game metadata', [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'has_custom_css' => isset($game->custom_css),
                'custom_css_length' => strlen($game->custom_css ?? ''),
                'dirty_attributes' => $game->getDirty(),
            ]);

            $game->save();

            Log::info('Game metadata saved', [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'saved_custom_css_length' => strlen($game->custom_css ?? ''),
                'saved_attributes' => $game->getAttributes(),
            ]);

            // Process any pending associations now that the game is saved
            $this->processPendingGameJams($game);
            $this->processPendingTags($game);

            // Commit the transaction
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Sync tags from a comma-separated string
     */
    public function syncTagsFromString(Game $game, string $tags): void
    {
        $tagNames = array_filter(array_map('trim', explode(',', $tags)));
        $tagIds = [];

        foreach ($tagNames as $tagName) {
            $tag = Tag::firstOrCreate(['name' => $tagName]);
            $tagIds[] = $tag->id;
        }

        // If the game is already saved, sync tags immediately
        if ($game->exists && $game->id) {
            $game->tags()->sync($tagIds);
            Log::info('Synced tags for existing game', [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'tag_ids' => $tagIds,
            ]);
        } else {
            // Otherwise, store them for later processing
            $game->pendingTagIds = $tagIds;
            Log::info('Stored pending tags for new game', [
                'game_name' => $game->name,
                'tag_ids' => $tagIds,
            ]);
        }
    }

    /**
     * Clear HTTP cache for a game
     */
    public function clearHttpCache(Game $game): void
    {
        unset(self::$httpCache[$game->id]);
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
}
