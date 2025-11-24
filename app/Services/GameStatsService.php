<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\UniqueDialogueText;
use App\Models\VersionCharacterStats;
use App\Models\VersionLanguageStats;
use App\Models\VersionSupportedLanguage;
use Exception;
use FilesystemIterator;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Normalizer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Service for extracting and processing game script statistics
 */
readonly class GameStatsService
{
    private LanguageMappingService $languageMappingService;

    private CharacterStatsCalculationService $characterStatsService;

    private EssentialCharacterService $essentialCharacterService;

    public function __construct(
        ?LanguageMappingService $languageMappingService = null,
        ?CharacterStatsCalculationService $characterStatsService = null,
        ?EssentialCharacterService $essentialCharacterService = null
    ) {
        $this->languageMappingService = $languageMappingService ?? app(LanguageMappingService::class);
        $this->characterStatsService = $characterStatsService ?? app(CharacterStatsCalculationService::class);
        $this->essentialCharacterService = $essentialCharacterService ?? app(EssentialCharacterService::class);
    }

    /**
     * Store a processed game file permanently
     */
    public function storeProcessedFile(string $tempFile, string $filename, int $gameId, int $versionId): void
    {
        if (! File::exists($tempFile)) {
            throw new RuntimeException('Temporary file no longer exists');
        }

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
     * Extract statistics from a game archive
     */
    public function extractGameStats(string $archivePath): ?array
    {
        Log::info('GameStats: Starting extraction', [
            'archive_path' => basename($archivePath),
        ]);

        // Create temporary directory for extraction
        $extractPath = storage_path('app/temp/' . uniqid('game_', true));
        File::makeDirectory($extractPath, 0755, true);

        try {
            // Extract archive
            Log::info('GameStats: Extracting archive', [
                'extract_path' => $extractPath,
            ]);
            $this->extractArchive($archivePath, $extractPath);
            Log::info('GameStats: Archive extracted successfully');

            // Find the game directory (it might be in a subdirectory)
            Log::info('GameStats: Finding game directory');
            $gameDir = $this->findGameDirectory($extractPath);
            if (! $gameDir) {
                Log::warning('Could not find valid game directory', [
                    'archive_path' => $archivePath,
                    'extract_path' => $extractPath,
                ]);

                return null;
            }

            Log::info('GameStats: Game directory found', [
                'game_dir' => basename($gameDir),
            ]);

            // First try to find and run a native Linux executable
            Log::info('GameStats: Looking for Linux executable');
            $linuxExecutable = $this->findLinuxExecutable($gameDir);
            if ($linuxExecutable) {
                Log::info('Found Linux executable, attempting to run it', [
                    'executable' => $linuxExecutable,
                ]);

                $stats = $this->extractStatsWithNativeExecutable($gameDir, $linuxExecutable);
                if ($stats) {
                    Log::info('Successfully extracted stats using native Linux executable');

                    return $stats;
                }

                Log::info('Failed to extract stats with native executable, falling back to Ren\'Py SDK');
            }

            // Fall back to Ren'Py SDK
            Log::info('GameStats: Attempting to use Ren\'Py SDK');
            $sdkPath = config('services.renpy.sdk_path');
            if (! $sdkPath || ! File::exists($sdkPath . '/renpy.sh')) {
                Log::error('Ren\'Py SDK path not configured or invalid', [
                    'sdk_path' => $sdkPath,
                ]);

                return null;
            }

            // Use the Ren'Py SDK to analyze the game
            Log::info('GameStats: Running Ren\'Py SDK analysis', [
                'game_dir' => basename($gameDir),
            ]);
            $stats = $this->extractStatsWithSdk($gameDir, $sdkPath);
            Log::info('GameStats: SDK analysis completed', [
                'has_stats' => $stats !== null,
            ]);

            return $stats;
        } catch (Exception $e) {
            // Log the exception but don't treat it as an error
            Log::warning('Error during game stats extraction', [
                'archive_path' => $archivePath,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return null;
        } finally {
            // Cleanup
            if (File::exists($extractPath)) {
                File::deleteDirectory($extractPath);
            }
        }
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
        array $stats,
        string $defaultLanguage = 'eng',
        ?Game $game = null
    ): void {
        echo "    [Stats] Starting saveVersionStats\n";
        // If game is not explicitly provided, get it from the version
        $game = $game ?? $version->game;

        // Track all languages found in the stats to update supported languages
        echo "    [Stats] Processing language stats\n";
        $foundLanguages = [];

        foreach ($stats['languages'] as $langKey => $langData) {
            $isoCode = $langKey === 'default'
                ? $defaultLanguage
                    : $this->languageMappingService->resolveLanguageCode($langKey, $game);

            if (! $isoCode) {
                Log::warning("Skipping language {$langKey} - could not determine ISO code");

                continue;
            }

            // Add to the list of found languages
            $foundLanguages[] = $isoCode;

            // Upsert language stats record
            VersionLanguageStats::updateOrCreate(
                [
                    'game_version_id' => $version->id,
                    'iso_code' => $isoCode,
                ],
                [
                    'blocks' => $langData['blocks'] ?? null,
                    'words' => $langData['words'] ?? null,
                    'menus' => $langData['menus'] ?? null,
                    'options' => $langData['options'] ?? null,
                ]
            );

            // Process character stats if available
            if (isset($langData['characters'])) {
                foreach ($langData['characters'] as $charId => $charData) {
                    // Get or create character record
                    $character = Character::firstOrNew([
                        'game_id' => $version->game_id,
                        'character_id' => $charId,
                    ]);

                    // Update display names
                    $displayNames = $character->exists ? $character->display_names : [];
                    $displayNames[$isoCode] = $charData['display_name'] ?? $charId;
                    $character->display_names = $displayNames;
                    $character->save();

                    // Update version tracking
                    echo "    [Stats] Checking first_seen for {$charId}\n";
                    if (! $character->first_seen_in_version_id ||
                        $version->published_at < $character->firstSeenVersion->published_at) {
                        $character->first_seen_in_version_id = $version->id;
                        $character->save();
                    }
                    echo "    [Stats] Checking last_seen for {$charId}\n";
                    if (! $character->last_seen_in_version_id ||
                        $version->published_at > $character->lastSeenVersion->published_at) {
                        $character->last_seen_in_version_id = $version->id;
                        $character->save();
                    }
                    echo "    [Stats] Character {$charId} processed\n";

                    // Store JSON stats for later comparison with calculated stats
                    // We'll use our centralized calculation after dialogue import
                    // but want to compare with JSON values for discrepancy reporting
                }
            }
        }

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
            if (! str_starts_with($isoCode, 'q')) {
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
            ->latest('published_at')
            ->first();

        // Copy language availability settings from previous version if it exists
        if ($previousVersion) {
            VersionSupportedLanguage::copyAvailabilitySettings($previousVersion->id, $version->id);
        }

        // Save file statistics
        if (isset($stats['file_statistics'])) {
            echo "    [Stats] Saving file statistics\n";
            $version->saveFileStats($stats['file_statistics']);
            echo "    [Stats] File statistics saved\n";
        }

        // Process dialogue lines
        if (isset($stats['dialogue_lines'])) {
            echo "    [Stats] Saving dialogue lines\n";
            $this->saveDialogueLines($version, $stats['dialogue_lines'], $defaultLanguage, $game, $foundLanguages);
            echo "    [Stats] Dialogue lines saved\n";

            // Apply special character assignment fixes after importing dialogue lines
            echo "    [Stats] Applying character assignment fixes\n";
            $this->applySpecialCharacterAssignments($version);
            echo "    [Stats] Character assignments fixed\n";

            // Calculate character stats from the imported dialogue lines and compare with JSON
            echo "    [Stats] Calculating stats and checking discrepancies\n";
            $this->calculateStatsAndReportDiscrepancies($version, $stats, $defaultLanguage, $game);
            echo "    [Stats] Stats calculated\n";
        }

        echo "    [Stats] Version stats processing complete\n";
    }

    /**
     * Save dialogue lines for a game version with text de-duplication
     * Updated to handle character display_names properly and ensure menu_choice character exists
     */
    protected function saveDialogueLines(
        GameVersion $version,
        array $dialogueLines,
        string $defaultLanguage = 'eng',
        ?Game $game = null,
        array $foundLanguages = []
    ): void {
        echo "    [Dialogue] Deleting existing dialogue lines\n";
        // First, delete any existing dialogue lines for this version
        DialogueLine::where('game_version_id', $version->id)->delete();
        echo "    [Dialogue] Existing lines deleted\n";

        // Get the essential characters that were already created with all languages
        $menuChoiceCharacter = $this->essentialCharacterService->getOrCreateMenuChoiceCharacter($version->game_id);
        $narratorCharacter = $this->essentialCharacterService->getOrCreateNarratorCharacter($version->game_id);

        // Create an in-memory cache for characters to avoid repeated database lookups
        $characterCache = [
            'menu_choice' => $menuChoiceCharacter->id,
            'narrator' => $narratorCharacter->id,
        ];

        // Process each language
        foreach ($dialogueLines as $langKey => $lines) {
            $isoCode = $langKey === 'default'
                ? $defaultLanguage
                : $this->languageMappingService->resolveLanguageCode($langKey, $game);

            if (! $isoCode) {
                Log::warning("Skipping dialogue lines for language {$langKey} - could not determine ISO code");

                continue;
            }

            // Process unique texts in smaller batches
            $now = now();
            $batchSize = 1000; // Reduced batch size to stay well under PostgreSQL's parameter limit
            $processedLines = 0;
            $totalLines = count($lines);

            // Process in chunks to avoid hitting PostgreSQL's parameter limit
            echo "    [Dialogue] Processing {$totalLines} lines for language {$isoCode}\n";
            foreach (array_chunk($lines, $batchSize) as $chunkIndex => $chunk) {
                echo '    [Dialogue] Processing chunk ' . ($chunkIndex + 1) . "\n";
                // First, collect unique texts for this chunk
                $uniqueTexts = [];

                // Normalize text to remove diacritical marks
                echo "    [Dialogue] Normalizing text...\n";
                foreach ($chunk as $id => $line) {
                    $text = $line['text'] ?? '';
                    if (empty($text)) {
                        continue;
                    }

                    $chunk[$id]['text'] = $this->processText($text);
                }
                echo "    [Dialogue] Text normalized\n";

                echo "    [Dialogue] Collecting unique texts...\n";
                foreach ($chunk as $line) {
                    $text = $line['text'] ?? '';
                    if (empty($text)) {
                        continue;
                    }

                    $textHash = md5($text);
                    $uniqueTexts[$textHash] = [
                        'text_hash' => $textHash,
                        'text_content' => $text,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                echo '    [Dialogue] Collected ' . count($uniqueTexts) . " unique texts\n";

                // Create unique texts (no search indexing needed - UniqueDialogueText is not searchable)
                // Use bulk upsert for performance instead of individual firstOrCreate() calls
                echo "    [Dialogue] Bulk inserting unique dialogue texts...\n";

                // PostgreSQL upsert - insert all at once, ignore conflicts
                if (! empty($uniqueTexts)) {
                    DB::table('unique_dialogue_texts')->insertOrIgnore(array_values($uniqueTexts));
                }
                echo "    [Dialogue] Bulk insert completed\n";

                // Now fetch all the IDs in a single query
                echo "    [Dialogue] Fetching text IDs...\n";
                $textIdMapping = [];
                $hashes = array_keys($uniqueTexts);
                $texts = DB::table('unique_dialogue_texts')
                    ->whereIn('text_hash', $hashes)
                    ->get(['id', 'text_hash']);

                foreach ($texts as $text) {
                    $textIdMapping[$text->text_hash] = $text->id;
                }
                echo '    [Dialogue] Mapped ' . count($textIdMapping) . " text IDs\n";

                // Now process dialogue lines for this chunk
                echo "    [Dialogue] Building dialogue batch...\n";
                $dialogueBatch = [];

                foreach ($chunk as $line) {
                    // Skip empty text
                    $text = $line['text'] ?? '';
                    if (empty($text)) {
                        continue;
                    }

                    // Get character ID with caching to avoid repeated database lookups
                    $characterName = empty($line['character']) ? 'narrator' : $line['character'];

                    if (isset($characterCache[$characterName])) {
                        // Use cached character ID
                        $characterId = $characterCache[$characterName];
                    } else {
                        // Create character and cache it
                        $character = $this->createCharacter(
                            $version->game_id,
                            $characterName,
                            $foundLanguages,
                            $defaultLanguage
                        );
                        $characterCache[$characterName] = $character->id;
                        $characterId = $character->id;
                    }

                    // Get the text ID from our mapping
                    $textHash = md5($text);
                    $textId = $textIdMapping[$textHash] ?? null;

                    if (! $textId) {
                        // If not found in our chunk, try to find it directly
                        $textId = DB::table('unique_dialogue_texts')
                            ->where('text_hash', $textHash)
                            ->value('id');

                        if (! $textId) {
                            Log::warning("Could not find text ID for hash {$textHash}");

                            continue;
                        }
                    }

                    // Add to batch
                    $dialogueBatch[] = [
                        'game_version_id' => $version->id,
                        'character_id' => $characterId,
                        'iso_code' => $isoCode,
                        'file_path' => $line['file'] ?? '',
                        'line_number' => $line['line'] ?? 0,
                        'text_id' => $textId,
                        'context' => $line['context'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $processedLines++;
                }

                echo '    [Dialogue] Dialogue batch built (' . count($dialogueBatch) . " lines)\n";

                // Bulk insert dialogue lines for performance (skip observers during import)
                // We'll update the search index once at the end instead of per-line
                if (! empty($dialogueBatch)) {
                    echo "    [Dialogue] Inserting batch into database...\n";
                    DB::table('version_dialogue_lines')->insert($dialogueBatch);
                    echo "    [Dialogue] Batch inserted\n";
                }
            }
        }

        echo "    [Dialogue] All dialogue lines inserted\n";
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
        if ($this->isZalgo($text)) {
            // Zalgo text: remove all diacritical marks.
            return $this->stripDiacritics($text);
        } else {
            // Normal text: normalize to NFC to ensure canonical form (preserving diacritics).
            return Normalizer::normalize($text, Normalizer::FORM_C);
        }
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
        // Normalize to decomposed form so that diacritical marks are separate characters
        $decomposed = Normalizer::normalize($text, Normalizer::FORM_D);
        // Total number of characters in decomposed string
        $totalLength = mb_strlen($decomposed, 'UTF-8');
        // Count all combining diacritical marks (Unicode category Mn)
        preg_match_all('/\p{Mn}/u', $decomposed, $matches);
        $diacriticCount = count($matches[0]);

        // Avoid division by zero, and check if ratio exceeds threshold
        return $totalLength > 0 && ($diacriticCount / $totalLength) > $threshold;
    }

    /**
     * Strip all diacritical marks from text.
     *
     * @param  string  $text  The input text.
     * @return string The text with diacritical marks removed.
     */
    protected function stripDiacritics(string $text): string
    {
        // Normalize to decomposed form (so diacritics are separate)
        $decomposed = Normalizer::normalize($text, Normalizer::FORM_D);

        // Remove all combining diacritical marks
        return preg_replace('/\p{Mn}/u', '', $decomposed);
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
        $character = Character::firstOrNew([
            'game_id' => $gameId,
            'character_id' => $characterId,
        ]);

        if (! $character->exists) {
            // New character: create with all languages
            $displayNames = [];

            // Add display name for all found languages
            foreach ($foundLanguages as $langCode) {
                $displayNames[$langCode] = $characterId;
            }

            // Ensure English is always included
            if (! in_array('eng', $foundLanguages)) {
                $displayNames['eng'] = $characterId;
            }

            // Ensure default language is always included
            if ($defaultLanguage !== 'eng' && ! in_array($defaultLanguage, $foundLanguages)) {
                $displayNames[$defaultLanguage] = $characterId;
            }

            $character->display_names = $displayNames;
            $character->save();
        } else {
            // Existing character: only add missing languages (preserves JSON display names)
            $displayNames = $character->display_names ?? [];
            $needsUpdate = false;

            // Add display name for all found languages if not already set
            foreach ($foundLanguages as $langCode) {
                if (! isset($displayNames[$langCode])) {
                    $displayNames[$langCode] = $characterId;
                    $needsUpdate = true;
                }
            }

            // Ensure English is always included
            if (! isset($displayNames['eng']) && ! in_array('eng', $foundLanguages)) {
                $displayNames['eng'] = $characterId;
                $needsUpdate = true;
            }

            // Ensure default language is always included
            if (! isset($displayNames[$defaultLanguage]) && $defaultLanguage !== 'eng' && ! in_array($defaultLanguage,
                $foundLanguages)) {
                $displayNames[$defaultLanguage] = $characterId;
                $needsUpdate = true;
            }

            // Only save if we made changes
            if ($needsUpdate) {
                $character->display_names = $displayNames;
                $character->save();
            }
        }

        return $character;
    }

    /**
     * Apply special character assignment fixes after importing dialogue lines
     */
    protected function applySpecialCharacterAssignments(GameVersion $version): void
    {
        Log::info("Applying special character assignments for game version {$version->id}");

        // Use the special character assignment service to fix assignments
        $specialCharacterService = app(CharacterSpecialAssignmentService::class);

        // Apply fixes for this specific game (not dry run)
        $result = $specialCharacterService->fixSpecialCharacterAssignments($version->game_id, null, false);

        if ($result['lines_reassigned'] > 0) {
            Log::info("Reassigned {$result['lines_reassigned']} special character lines for game {$version->game_id}");

            // Clean up any orphaned special characters
            $versionReferenceService = app(CharacterVersionReferenceService::class);
            $cleanupResult = $versionReferenceService->fixVersionReferences($version->game_id, false);

            if ($cleanupResult['characters_deleted'] > 0) {
                Log::info("Deleted {$cleanupResult['characters_deleted']} orphaned special characters for game {$version->game_id}");
            }
        }
    }

    /**
     * Calculate character stats and report discrepancies with JSON stats
     */
    protected function calculateStatsAndReportDiscrepancies(
        GameVersion $version,
        array $stats,
        string $defaultLanguage = 'eng',
        ?Game $game = null
    ): void {
        // Calculate stats using our centralized service
        $this->characterStatsService->calculateAndSaveStatsForVersion($version->id);

        // Compare with JSON stats and report discrepancies
        if (! isset($stats['languages'])) {
            return;
        }

        $discrepanciesFound = false;

        foreach ($stats['languages'] as $langKey => $langData) {
            $isoCode = $langKey === 'default'
                ? $defaultLanguage
                : $this->languageMappingService->resolveLanguageCode($langKey, $game);

            if (! $isoCode || ! isset($langData['characters'])) {
                continue;
            }

            foreach ($langData['characters'] as $charId => $charData) {
                // Find the character in our database
                $character = Character::where('game_id', $version->game_id)
                    ->where('character_id', $charId)
                    ->first();

                if (! $character) {
                    continue;
                }

                // Get our calculated stats
                $calculatedStats = VersionCharacterStats::where('game_version_id', $version->id)
                    ->where('character_id', $character->id)
                    ->where('iso_code', $isoCode)
                    ->first();

                if (! $calculatedStats) {
                    continue;
                }

                // Compare JSON vs calculated stats
                $jsonBlocks = $charData['blocks'] ?? 0;
                $jsonWords = $charData['words'] ?? 0;
                $calculatedBlocks = $calculatedStats->blocks;
                $calculatedWords = $calculatedStats->words;

                if ($jsonBlocks !== $calculatedBlocks || $jsonWords !== $calculatedWords) {
                    if (! $discrepanciesFound) {
                        Log::warning("Character stats discrepancies found for game version {$version->id}:");
                        $discrepanciesFound = true;
                    }

                    Log::warning("Character '{$charId}' ({$isoCode}): JSON={$jsonBlocks} blocks, {$jsonWords} words | Calculated={$calculatedBlocks} blocks, {$calculatedWords} words");
                }
            }
        }

        if (! $discrepanciesFound) {
            Log::info("Character stats validation passed for game version {$version->id} - no discrepancies found");
        }
    }

    /**
     * Extract a game archive to the specified directory
     *
     * @throws RuntimeException If extraction fails
     */
    private function extractArchive(string $archivePath, string $extractPath): void
    {
        $ext = strtolower(pathinfo($archivePath, PATHINFO_EXTENSION));

        // Handle tar.gz and tar.bz2 files (even if they're missing the tar part)
        if ($ext === 'gz' || $ext === 'bz2') {
            $process = new Process([
                'tar',
                '-x' . ($ext === 'gz' ? 'z' : 'j'), // Add z for gzip, j for bzip2
                '-f',
                $archivePath,
                '-C',
                $extractPath,
            ]);

            $process->setTimeout(300); // 5 minute timeout
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Failed to extract tar archive: ' . $process->getErrorOutput());
            }

            return;
        }

        // Handle zip files
        if ($ext === 'zip') {
            $zip = new ZipArchive;
            $result = $zip->open($archivePath);

            if ($result !== true) {
                throw new RuntimeException("Failed to open zip archive: {$result}");
            }

            try {
                if (! $zip->extractTo($extractPath)) {
                    throw new RuntimeException('Failed to extract zip archive');
                }
            } finally {
                $zip->close();
            }

            return;
        }

        // Handle plain tar files
        if ($ext === 'tar') {
            $process = new Process([
                'tar',
                '-xf',
                $archivePath,
                '-C',
                $extractPath,
            ]);

            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Failed to extract tar archive: ' . $process->getErrorOutput());
            }

            return;
        }

        throw new RuntimeException("Unsupported archive format: {$ext}");
    }

    /**
     * Find the game directory containing the Ren'Py game files
     */
    private function findGameDirectory(string $basePath): ?string
    {
        // Check if the game directory is directly in the extracted path
        if (File::isDirectory($basePath . '/game')) {
            return $basePath;
        }

        // Check first-level subdirectories
        return array_find(File::directories($basePath), fn ($dir) => File::isDirectory($dir . '/game'));
    }

    /**
     * Find a Linux executable in the game directory
     *
     * @param  string  $gameDir  The game directory to search in
     * @return string|null Path to the executable or null if none found
     */
    private function findLinuxExecutable(string $gameDir): ?string
    {
        $this->makeExecutables($gameDir);

        // Get all files in the game directory and its subdirectories
        $allFiles = $this->findAllFiles($gameDir);

        // Filter to only include executable files
        $executableFiles = array_filter($allFiles, function ($file) {
            // Check if the file is executable
            return is_file($file) && is_executable($file);
        });

        if (empty($executableFiles)) {
            Log::info('No executable files found in game directory');

            return null;
        }

        // First, try to find executables matching our priority patterns
        foreach ($executableFiles as $file) {
            $filename = basename($file);
            if (preg_match('/\.sh$/i', $filename)) {
                Log::info("Found bash script: {$filename}");

                return $file;
            }
        }

        // If no priority match, return the first executable found
        $firstExecutable = reset($executableFiles);
        $filename = basename($firstExecutable);
        Log::info("Using first available executable: {$filename}");

        return $firstExecutable;
    }

    private function makeExecutables(string $dir): void
    {
        // root
        foreach (File::files($dir) as $file) {
            chmod($file->getPathname(), 0755);
        }

        // lib/
        $lib = $dir . DIRECTORY_SEPARATOR . 'lib';
        if (! File::isDirectory($lib)) {
            return;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($lib, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($it as $file) {
            if ($file->isFile()) {
                chmod($file->getPathname(), 0755);
            }
        }
    }

    /**
     * Find all files in a directory and its subdirectories
     *
     * @param  string  $dir  The directory to search in
     * @return array Array of file paths
     */
    private function findAllFiles(string $dir): array
    {
        $files = [];

        // Get all files in the current directory
        foreach (File::files($dir) as $file) {
            $files[] = $file->getPathname();
        }

        // Recursively search subdirectories
        foreach (File::directories($dir) as $subdir) {
            if (basename($subdir) === 'game') {
                continue;
            }

            $files = array_merge($files, $this->findAllFiles($subdir));
        }

        return $files;
    }

    /**
     * Extract statistics using a native Linux executable
     *
     * @param  string  $gameDir  The game directory
     * @param  string  $executablePath  Path to the Linux executable
     * @return array|null Stats array or null if extraction failed
     */
    private function extractStatsWithNativeExecutable(string $gameDir, string $executablePath): ?array
    {
        try {
            // Copy our analysis script to the game directory
            File::copy(
                resource_path('renpy/json_stats.rpy'),
                $gameDir . '/game/json_stats.rpy'
            );
        } catch (Exception $e) {
            Log::warning('Failed to copy analysis script', [
                'error' => $e->getMessage(),
                'game_dir' => $gameDir,
            ]);

            return null;
        }

        // Execute the game with the native executable
        $process = new Process([$executablePath, 'game', 'test'], dirname($executablePath));
        $process->setTimeout(300); // 5 minute timeout
        $process->run();

        // Check for successful execution, but don't treat it as an error
        if (! $process->isSuccessful()) {
            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();
            Log::warning('Native executable completed with non-zero exit code', [
                'output' => $output,
                'error_output' => $errorOutput,
                'exit_code' => $process->getExitCode(),
                'executable' => $executablePath,
            ]);
        }

        // Check if the stats file was generated
        $statsFile = $gameDir . '/stats.json';
        if (! File::exists($statsFile)) {
            Log::info('Stats file not generated by native executable');

            return null;
        }

        // Read and parse the stats file
        try {
            $stats = json_decode(File::get($statsFile), true);
            if (! $stats || ! isset($stats['languages'])) {
                Log::warning('Invalid stats file format from native executable');

                return null;
            }

            return $stats;
        } catch (Exception $e) {
            Log::warning('Error reading stats file from native executable', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract statistics using the Ren'Py SDK
     *
     * @return array|null Stats array or null if extraction failed but shouldn't be treated as an error
     *
     * @throws RuntimeException|FileNotFoundException Only if stats file doesn't exist or is invalid after successful process execution
     */
    private function extractStatsWithSdk(string $gameDir, string $sdkPath): ?array
    {
        try {
            // Copy our analysis script to the game directory
            File::copy(
                resource_path('renpy/json_stats.rpy'),
                $gameDir . '/game/json_stats.rpy'
            );
        } catch (Exception $e) {
            Log::warning('Failed to copy analysis script', [
                'error' => $e->getMessage(),
                'game_dir' => $gameDir,
            ]);

            return null;
        }

        // Execute the script analysis using the SDK
        $process = new Process([$sdkPath . '/renpy.sh', 'game', 'test'], $gameDir);
        $process->setTimeout(300); // 5 minute timeout
        $process->run();

        // Check for successful execution, but don't treat it as an error
        if (! $process->isSuccessful()) {
            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();
            Log::warning('Script analysis completed with non-zero exit code', [
                'output' => $output,
                'error_output' => $errorOutput,
                'exit_code' => $process->getExitCode(),
                'sdk_path' => $sdkPath,
                'game_dir' => $gameDir,
            ]);
        }

        // Read and parse the stats file - this is the only real error condition
        $statsFile = $gameDir . '/stats.json';
        if (! File::exists($statsFile)) {
            throw new RuntimeException('Stats file not generated');
        }

        $stats = json_decode(File::get($statsFile), true);
        if (! $stats || ! isset($stats['languages'])) {
            throw new RuntimeException('Invalid stats file format');
        }

        return $stats;
    }
}
