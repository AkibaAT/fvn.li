<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\VersionCharacterStats;
use App\Models\VersionLanguageStats;
use App\Models\VersionSupportedLanguage;
use App\Services\Concerns\ReportsProgress;
use App\Support\Stats\ArrayStatsPayload;
use App\Support\Stats\StatsPayload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Service for extracting and processing game script statistics
 */
class GameStatsService
{
    use ReportsProgress;

    private LanguageMappingService $languageMappingService;

    private CharacterStatsCalculationService $characterStatsService;

    private EssentialCharacterService $essentialCharacterService;

    private RenpyStatsSandboxClient $sandboxClient;

    private RenpyStatsLocalExtractor $localExtractor;

    private GameStatsRouteGraphPersister $routeGraphPersister;

    private GameStatsDialoguePersister $dialoguePersister;

    public function __construct(
        ?LanguageMappingService $languageMappingService = null,
        ?CharacterStatsCalculationService $characterStatsService = null,
        ?EssentialCharacterService $essentialCharacterService = null,
        ?RenpyStatsSandboxClient $sandboxClient = null,
        ?RenpyStatsLocalExtractor $localExtractor = null,
        ?GameStatsRouteGraphPersister $routeGraphPersister = null,
        ?GameStatsDialoguePersister $dialoguePersister = null
    ) {
        $this->languageMappingService = $languageMappingService ?? app(LanguageMappingService::class);
        $this->characterStatsService = $characterStatsService ?? app(CharacterStatsCalculationService::class);
        $this->essentialCharacterService = $essentialCharacterService ?? app(EssentialCharacterService::class);
        $this->sandboxClient = $sandboxClient ?? app(RenpyStatsSandboxClient::class);
        $this->localExtractor = $localExtractor ?? app(RenpyStatsLocalExtractor::class);
        $this->routeGraphPersister = $routeGraphPersister ?? app(GameStatsRouteGraphPersister::class);
        $this->dialoguePersister = $dialoguePersister ?? app(GameStatsDialoguePersister::class);
    }

    public function storeProcessedFile(string $tempFile, string $filename, int $gameId, int $versionId): void
    {
        if (! File::exists($tempFile)) {
            throw new RuntimeException('Temporary file no longer exists');
        }

        $filename = $this->sanitizeArchiveFilename($filename);

        try {
            $storagePath = "games/{$gameId}/{$versionId}";
            Storage::makeDirectory($storagePath);
            Storage::putFileAs($storagePath, $tempFile, $filename);
        } finally {
            // Clean up temp file
            File::delete($tempFile);
        }
    }

    public function canExtractStats(string $archivePath): bool
    {
        $payload = $this->extractGameStats($archivePath);

        if ($payload === null) {
            return false;
        }

        try {
            return $payload->languages() !== [];
        } finally {
            $payload->release();
        }
    }

    public function extractGameStats(string $archivePath): ?StatsPayload
    {
        $mode = config('services.renpy.analysis_mode', 'sandbox');
        if ($mode === 'sandbox') {
            Log::info('GameStats: Delegating extraction to sandbox analyzer', [
                'archive_path' => basename($archivePath),
            ]);

            return $this->sandboxClient->extract($archivePath);
        }

        if ($mode !== 'local_trusted') {
            Log::warning('GameStats: Unknown RenPy analysis mode, skipping extraction', [
                'mode' => $mode,
                'archive_path' => basename($archivePath),
            ]);

            return null;
        }

        return $this->localExtractor->extract($archivePath);
    }

    public function getLastExtractionError(): ?string
    {
        return $this->sandboxClient->getLastError() ?? $this->localExtractor->getLastError();
    }

    /**
     * Save or update language and character statistics for a game version
     *
     * @param  GameVersion  $version  The game version to save stats for
     * @param  array  $stats  The extracted stats data
     * @param  string  $defaultLanguage  The default language code (usually source language)
     * @param  Game|null  $game  The game object for game-specific language mappings
     *
     * @throws Throwable
     */
    public function saveVersionStats(
        GameVersion $version,
        StatsPayload|array $stats,
        string $defaultLanguage = 'eng',
        ?Game $game = null
    ): void {
        $this->progress("    [Stats] Starting saveVersionStats\n");
        $payload = $stats instanceof StatsPayload ? $stats : new ArrayStatsPayload($stats);

        // If game is not explicitly provided, get it from the version
        $game = $game ?? $version->game;

        $this->clearVersionAggregateStats($version);

        // Track all languages found in the stats to update supported languages
        $this->progress("    [Stats] Processing language stats\n");
        $payloadLanguages = $payload->languages();
        $languageCodes = $this->languageMappingService->resolvePayloadLanguageCodes(
            array_keys($payloadLanguages),
            $defaultLanguage,
            $game
        );
        $ignoredLanguageKeys = array_diff(array_keys($payloadLanguages), array_keys($languageCodes));
        if ($ignoredLanguageKeys !== []) {
            $this->progress('    [Stats] Ignoring duplicate source-language aliases: '
                . implode(', ', $ignoredLanguageKeys) . "\n");
        }
        $foundLanguages = $this->saveLanguageAndCharacterStats($version, $payloadLanguages, $languageCodes);

        $this->progress("    [Stats] Creating essential characters\n");
        $this->essentialCharacterService->createEssentialCharactersWithLanguages(
            $version->game_id,
            $foundLanguages,
            $defaultLanguage
        );
        $this->progress("    [Stats] Essential characters created\n");

        $this->progress("    [Stats] Adding supported languages\n");
        foreach ($foundLanguages as $isoCode) {
            if (! LanguageMappingService::isPlaceholderLanguageCode($isoCode)) {
                $version->addSupportedLanguage($isoCode);
            }
        }
        $this->progress("    [Stats] Supported languages added\n");

        $previousVersion = GameVersion::where('game_id', $version->game_id)
            ->where('id', '!=', $version->id)
            ->whereHas('supportedLanguages', function ($query) {
                $query->where('is_available', false);
            })
            ->orderBy('published_at', 'desc')
            ->first();

        // Copy language availability settings from previous version if it exists
        if ($previousVersion) {
            VersionSupportedLanguage::copyAvailabilitySettings($previousVersion->id, $version->id);
        }

        $fileStatistics = $payload->fileStatistics();
        if ($fileStatistics !== null) {
            $this->progress("    [Stats] Saving file statistics\n");
            $version->saveFileStats($fileStatistics);
            $this->progress("    [Stats] File statistics saved\n");
        }

        $this->progress("    [Stats] Saving route graph data\n");
        $hasRouteData = $this->routeGraphPersister->save($version, $payload) > 0;
        $this->progress('    [Stats] Route graph data saved (present: ' . ($hasRouteData ? 'yes' : 'no') . ")\n");

        $this->progress("    [Stats] Saving dialogue lines\n");
        $dialogueLineCount = $this->dialoguePersister->save(
            $version,
            $payload,
            $defaultLanguage,
            $game,
            $foundLanguages,
            $languageCodes
        );
        $this->progress("    [Stats] Dialogue lines saved ({$dialogueLineCount} lines)\n");

        if ($dialogueLineCount > 0) {
            $this->progress("    [Stats] Applying character assignment fixes\n");
            $this->dialoguePersister->applySpecialCharacterAssignments($version);
            $this->progress("    [Stats] Character assignments fixed\n");

            $this->progress("    [Stats] Calculating stats and checking discrepancies\n");
            $this->dialoguePersister->calculateStatsAndReportDiscrepancies(
                $version,
                $payload,
                $defaultLanguage,
                $game,
                $languageCodes
            );
            $this->progress("    [Stats] Stats calculated\n");

            // Queue word frequency calculation for all languages in this version
            $this->progress("    [Stats] Queueing word frequency calculations\n");
            $this->dialoguePersister->queueWordFrequencyCalculations($version->id);
            $this->progress("    [Stats] Word frequency calculations queued\n");
        }

        if ($hasRouteData) {
            // The precomputed graph and paths are derived views of data that is
            // already stored. They are rebuildable, so a failure here is
            // reported and left for a later pass rather than discarding the
            // extraction that produced them.
            try {
                $this->progress("    [Stats] Pre-computing route graph\n");
                app(RouteGraphService::class)->computeAndStore($version);
                $this->progress("    [Stats] Route graph computed and stored\n");
            } catch (Throwable $throwable) {
                $this->progress("    [Stats] Route graph precompute skipped: {$throwable->getMessage()}\n");
                Log::warning('Route graph precompute failed', [
                    'game_version_id' => $version->id,
                    'error' => $throwable->getMessage(),
                ]);
            }

            try {
                $this->progress("    [Stats] Calculating route paths\n");
                app(RoutePathCalculator::class)->calculateAndStore($version);
                $this->progress("    [Stats] Route paths calculated and stored\n");
            } catch (Throwable $throwable) {
                $this->progress("    [Stats] Route path calculation skipped: {$throwable->getMessage()}\n");
                Log::warning('Route path calculation failed', [
                    'game_version_id' => $version->id,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        Cache::forget('dialogue.games_list');
        GameSearchRefreshService::refreshForLatestVersion($version, 'version_stats_saved');
        $this->progress("    [Stats] Version stats processing complete\n");
    }

    /**
     * Persist per-language and per-character aggregate stats.
     *
     * The game's characters and the versions they were first and last seen in
     * are loaded up front, and the resulting rows are written in bulk, so the
     * query count is independent of how many characters or languages there are.
     *
     * @return array<int, string> the ISO codes found in the payload
     */
    private function saveLanguageAndCharacterStats(
        GameVersion $version,
        array $payloadLanguages,
        array $languageCodes
    ): array {
        $foundLanguages = [];
        $languageRows = [];
        $characterStatRows = [];

        $characters = Character::where('game_id', $version->game_id)->get()->keyBy('character_id');
        $publishedAt = $this->publishedAtByVersionId($characters);
        $now = now();

        foreach ($payloadLanguages as $langKey => $langData) {
            if (! isset($languageCodes[$langKey])) {
                continue;
            }
            $isoCode = $languageCodes[$langKey];

            if (! in_array($isoCode, $foundLanguages, true)) {
                $foundLanguages[] = $isoCode;
            }

            // Several extractor language keys can resolve to one ISO code, so
            // the later entry replaces the earlier one.
            $languageRows[$isoCode] = [
                'game_version_id' => $version->id,
                'iso_code' => $isoCode,
                'blocks' => $langData['blocks'] ?? null,
                'words' => $langData['words'] ?? null,
                'menus' => $langData['menus'] ?? null,
                'options' => $langData['options'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach (($langData['characters'] ?? []) as $charId => $charData) {
                $charId = (string) $charId;
                $character = $characters->get($charId);

                if (! $character) {
                    $character = new Character([
                        'game_id' => $version->game_id,
                        'character_id' => $charId,
                    ]);
                    $characters->put($charId, $character);
                }

                $displayNames = $character->exists ? ($character->display_names ?? []) : [];
                $displayNames[$isoCode] = $charData['display_name'] ?? $charId;
                $character->display_names = $displayNames;

                // Species only comes from English data, and only if not already known.
                if ($isoCode === 'eng' && isset($charData['species']) && ! $character->species) {
                    $character->species = $charData['species'];
                }

                $firstSeenAt = $publishedAt[$character->first_seen_in_version_id] ?? null;
                if (! $character->first_seen_in_version_id || $version->published_at < $firstSeenAt) {
                    $character->first_seen_in_version_id = $version->id;
                }

                $lastSeenAt = $publishedAt[$character->last_seen_in_version_id] ?? null;
                if (! $character->last_seen_in_version_id || $version->published_at > $lastSeenAt) {
                    $character->last_seen_in_version_id = $version->id;
                }

                if ($character->isDirty() || ! $character->exists) {
                    $character->save();
                }

                $characterStatRows[$character->id . '|' . $isoCode] = [
                    'game_version_id' => $version->id,
                    'character_id' => $character->id,
                    'iso_code' => $isoCode,
                    'blocks' => $charData['blocks'] ?? 0,
                    'words' => $charData['words'] ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Safe as plain inserts: clearVersionAggregateStats() removed this
        // version's rows from both tables immediately beforehand.
        foreach (array_chunk(array_values($languageRows), 500) as $chunk) {
            DB::table((new VersionLanguageStats)->getTable())->insert($chunk);
        }

        foreach (array_chunk(array_values($characterStatRows), 500) as $chunk) {
            DB::table((new VersionCharacterStats)->getTable())->insert($chunk);
        }

        return $foundLanguages;
    }

    /**
     * @param  Collection<string, Character>  $characters
     * @return array<int, mixed>
     */
    private function publishedAtByVersionId($characters): array
    {
        $versionIds = $characters
            ->flatMap(fn (Character $character): array => [
                $character->first_seen_in_version_id,
                $character->last_seen_in_version_id,
            ])
            ->filter()
            ->unique()
            ->values();

        if ($versionIds->isEmpty()) {
            return [];
        }

        return GameVersion::whereIn('id', $versionIds)
            ->pluck('published_at', 'id')
            ->all();
    }

    private function sanitizeArchiveFilename(string $filename): string
    {
        $filename = trim($filename);

        if ($filename === '') {
            return 'archive';
        }

        if (
            str_contains($filename, "\0") ||
            str_contains($filename, '/') ||
            str_contains($filename, '\\') ||
            $filename === '.' ||
            $filename === '..'
        ) {
            throw new RuntimeException('Archive filenames must not contain path separators or traversal segments.');
        }

        return $filename;
    }

    private function clearVersionAggregateStats(GameVersion $version): void
    {
        $this->progress("    [Stats] Clearing previous aggregate stats\n");
        $version->languageStats()->delete();
        $version->characterStats()->delete();
        $version->supportedLanguages()->delete();
    }
}
