<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\VersionCharacterStats;
use App\Models\VersionLanguageStats;
use App\Models\VersionSupportedLanguage;
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
readonly class GameStatsService
{
    private const MAX_DIALOGUE_TEXT_BYTES = 65536;

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

    /**
     * Store a processed game file permanently
     */
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

    /**
     * Check whether an archive yields usable stats, without materializing them.
     *
     * Validates derived archives. Only the aggregate records at the head of the
     * document are read, so this costs the same for a huge game as a tiny one.
     */
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

    /**
     * Extract statistics from a game archive
     */
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

        return $this->extractGameStatsLocally($archivePath);
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
        echo "    [Stats] Starting saveVersionStats\n";
        $payload = $stats instanceof StatsPayload ? $stats : new ArrayStatsPayload($stats);

        // If game is not explicitly provided, get it from the version
        $game = $game ?? $version->game;

        $this->clearVersionAggregateStats($version);

        // Track all languages found in the stats to update supported languages
        echo "    [Stats] Processing language stats\n";
        $foundLanguages = $this->saveLanguageAndCharacterStats($version, $payload, $defaultLanguage, $game);

        // Create essential characters with all found languages before processing dialogue lines
        echo "    [Stats] Creating essential characters\n";
        $this->essentialCharacterService->createEssentialCharactersWithLanguages(
            $version->game_id,
            $foundLanguages,
            $defaultLanguage
        );
        echo "    [Stats] Essential characters created\n";

        // Update supported languages for this version
        // First, add all languages found in the stats
        echo "    [Stats] Adding supported languages\n";
        foreach ($foundLanguages as $isoCode) {
            if (! LanguageMappingService::isPlaceholderLanguageCode($isoCode)) {
                $version->addSupportedLanguage($isoCode);
            }
        }
        echo "    [Stats] Supported languages added\n";

        // Find the previous version to copy language availability settings from
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

        // Save file statistics
        $fileStatistics = $payload->fileStatistics();
        if ($fileStatistics !== null) {
            echo "    [Stats] Saving file statistics\n";
            $version->saveFileStats($fileStatistics);
            echo "    [Stats] File statistics saved\n";
        }

        // Save route graph data
        echo "    [Stats] Saving route graph data\n";
        $hasRouteData = $this->saveRouteGraph($version, $payload) > 0;
        echo '    [Stats] Route graph data saved (present: ' . ($hasRouteData ? 'yes' : 'no') . ")\n";

        // Process dialogue lines
        echo "    [Stats] Saving dialogue lines\n";
        $dialogueLineCount = $this->saveDialogueLines($version, $payload, $defaultLanguage, $game, $foundLanguages);
        echo "    [Stats] Dialogue lines saved ({$dialogueLineCount} lines)\n";

        if ($dialogueLineCount > 0) {
            // Apply special character assignment fixes after importing dialogue lines
            echo "    [Stats] Applying character assignment fixes\n";
            $this->applySpecialCharacterAssignments($version);
            echo "    [Stats] Character assignments fixed\n";

            // Calculate character stats from the imported dialogue lines and compare with the payload
            echo "    [Stats] Calculating stats and checking discrepancies\n";
            $this->calculateStatsAndReportDiscrepancies($version, $payload, $defaultLanguage, $game);
            echo "    [Stats] Stats calculated\n";

            // Queue word frequency calculation for all languages in this version
            echo "    [Stats] Queueing word frequency calculations\n";
            $this->queueWordFrequencyCalculations($version->id);
            echo "    [Stats] Word frequency calculations queued\n";
        }

        // Calculate route paths and pre-build graph after both route graph and dialogue lines are saved
        if ($hasRouteData) {
            // The precomputed graph and paths are derived views of data that is
            // already stored. They are rebuildable, so a failure here is
            // reported and left for a later pass rather than discarding the
            // extraction that produced them.
            try {
                echo "    [Stats] Pre-computing route graph\n";
                app(RouteGraphService::class)->computeAndStore($version);
                echo "    [Stats] Route graph computed and stored\n";
            } catch (Throwable $throwable) {
                echo "    [Stats] Route graph precompute skipped: {$throwable->getMessage()}\n";
                Log::warning('Route graph precompute failed', [
                    'game_version_id' => $version->id,
                    'error' => $throwable->getMessage(),
                ]);
            }

            try {
                echo "    [Stats] Calculating route paths\n";
                app(RoutePathCalculator::class)->calculateAndStore($version);
                echo "    [Stats] Route paths calculated and stored\n";
            } catch (Throwable $throwable) {
                echo "    [Stats] Route path calculation skipped: {$throwable->getMessage()}\n";
                Log::warning('Route path calculation failed', [
                    'game_version_id' => $version->id,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        Cache::forget('dialogue.games_list');
        GameSearchRefreshService::refreshForLatestVersion($version, 'version_stats_saved');
        echo "    [Stats] Version stats processing complete\n";
    }

    /**
     * Save route graph data (labels, edges, menu choices) for a game version
     *
     * @return int the number of route entries written
     */
    protected function saveRouteGraph(GameVersion $version, StatsPayload|array $stats): int
    {
        return $this->routeGraphPersister->save(
            $version,
            $stats instanceof StatsPayload ? $stats : new ArrayStatsPayload($stats)
        );
    }

    /**
     * Save dialogue lines for a game version with text de-duplication
     * Updated to handle character display_names properly and ensure menu_choice character exists
     */
    protected function saveDialogueLines(
        GameVersion $version,
        StatsPayload|array $dialogueLines,
        string $defaultLanguage = 'eng',
        ?Game $game = null,
        array $foundLanguages = []
    ): int {
        $payload = $dialogueLines instanceof StatsPayload
            ? $dialogueLines
            : new ArrayStatsPayload(['dialogue_lines' => $dialogueLines]);

        return $this->dialoguePersister->save($version, $payload, $defaultLanguage, $game, $foundLanguages);
    }

    /**
     * Process the text:
     * - If it's Zalgo text (excessive diacritics), strip diacritical marks.
     * - Otherwise, normalize to NFC to preserve diacritical marks.
     *
     * @param  string  $text  The input text.
     * @return string The processed text.
     */
    protected function processText(string $text): string
    {
        return $this->dialoguePersister->processText($text);
    }

    /**
     * Check if the given text is likely Zalgo text.
     *
     * @param  string  $text  The input text.
     * @param  float  $threshold  The ratio of diacritics to total characters to trigger stripping.
     * @return bool Returns true if the text is considered Zalgo.
     */
    protected function isZalgo(string $text, float $threshold = 0.9): bool
    {
        return $this->dialoguePersister->isZalgo($text, $threshold);
    }

    /**
     * Strip all diacritical marks from text.
     *
     * @param  string  $text  The input text.
     * @return string The text with diacritical marks removed.
     */
    protected function stripDiacritics(string $text): string
    {
        return $this->dialoguePersister->stripDiacritics($text);
    }

    /**
     * Create or get a character with proper multi-language support
     * This is the single function that handles all character creation logic
     */
    protected function createCharacter(
        int $gameId,
        string $characterId,
        array $foundLanguages,
        string $defaultLanguage
    ): Character {
        return $this->dialoguePersister->createCharacter($gameId, $characterId, $foundLanguages, $defaultLanguage);
    }

    /**
     * Apply special character assignment fixes after importing dialogue lines
     */
    protected function applySpecialCharacterAssignments(GameVersion $version): void
    {
        $this->dialoguePersister->applySpecialCharacterAssignments($version);
    }

    /**
     * Calculate character stats and report discrepancies with JSON stats
     */
    protected function calculateStatsAndReportDiscrepancies(
        GameVersion $version,
        StatsPayload|array $stats,
        string $defaultLanguage = 'eng',
        ?Game $game = null
    ): void {
        $this->dialoguePersister->calculateStatsAndReportDiscrepancies(
            $version,
            $stats instanceof StatsPayload ? $stats : new ArrayStatsPayload($stats),
            $defaultLanguage,
            $game
        );
    }

    /**
     * Queue word frequency calculations for all languages in a game version.
     * This is called after dialogue import to pre-calculate word frequencies.
     */
    protected function queueWordFrequencyCalculations(int $versionId): void
    {
        $this->dialoguePersister->queueWordFrequencyCalculations($versionId);
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
        StatsPayload $payload,
        string $defaultLanguage,
        ?Game $game
    ): array {
        $foundLanguages = [];
        $languageRows = [];
        $characterStatRows = [];

        $characters = Character::where('game_id', $version->game_id)->get()->keyBy('character_id');
        $publishedAt = $this->publishedAtByVersionId($characters);
        $now = now();

        foreach ($payload->languages() as $langKey => $langData) {
            $isoCode = $langKey === 'default'
                ? $defaultLanguage
                : $this->languageMappingService->resolveLanguageCode((string) $langKey, $game);

            if (! $isoCode) {
                Log::warning("Skipping language {$langKey} - could not determine ISO code");

                continue;
            }

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

    /**
     * Extract statistics locally. This mode is intended only for trusted local
     * fixtures and explicit development fallback, never untrusted production input.
     */
    private function extractGameStatsLocally(string $archivePath): ?StatsPayload
    {
        return $this->localExtractor->extract($archivePath);
    }

    private function clearVersionAggregateStats(GameVersion $version): void
    {
        echo "    [Stats] Clearing previous aggregate stats\n";
        $version->languageStats()->delete();
        $version->characterStats()->delete();
        $version->supportedLanguages()->delete();
    }

    /**
     * Extract a game archive to the specified directory
     *
     * @throws RuntimeException If extraction fails
     */
    private function extractArchive(string $archivePath, string $extractPath): void
    {
        $this->localExtractor->extractArchive($archivePath, $extractPath);
    }

    private function detectArchiveFormat(string $archivePath): string
    {
        return $this->localExtractor->detectArchiveFormat($archivePath);
    }

    /**
     * Find the game directory containing the Ren'Py game files
     */
    private function findGameDirectory(string $basePath): ?string
    {
        return $this->localExtractor->findGameDirectory($basePath);
    }

    private function hasTranslationTree(string $gameDir): bool
    {
        return $this->localExtractor->hasTranslationTree($gameDir);
    }

    /**
     * Extract statistics using the Ren'Py SDK
     *
     * @return array|null Stats array or null if extraction failed but shouldn't be treated as an error
     *
     * @throws RuntimeException Only if stats file doesn't exist or is invalid after successful process execution
     */
    private function extractStatsWithSdk(string $gameDir, string $sdkPath): ?StatsPayload
    {
        return $this->localExtractor->extractStatsWithSdk($gameDir, $sdkPath);
    }
}
