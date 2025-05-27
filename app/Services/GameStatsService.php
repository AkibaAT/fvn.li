<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
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

    public function __construct(
        ?LanguageMappingService $languageMappingService = null
    ) {
        $this->languageMappingService = $languageMappingService ?? app(LanguageMappingService::class);
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
        // Create temporary directory for extraction
        $extractPath = storage_path('app/temp/' . uniqid('game_', true));
        File::makeDirectory($extractPath, 0755, true);

        try {
            // Extract archive
            $this->extractArchive($archivePath, $extractPath);

            // Find the game directory (it might be in a subdirectory)
            $gameDir = $this->findGameDirectory($extractPath);
            if (! $gameDir) {
                Log::warning('Could not find valid game directory', [
                    'archive_path' => $archivePath,
                    'extract_path' => $extractPath,
                ]);

                return null;
            }

            // First try to find and run a native Linux executable
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
            $sdkPath = config('services.renpy.sdk_path');
            if (! $sdkPath || ! File::exists($sdkPath . '/renpy.sh')) {
                Log::error('Ren\'Py SDK path not configured or invalid', [
                    'sdk_path' => $sdkPath,
                ]);

                return null;
            }

            // Use the Ren'Py SDK to analyze the game
            return $this->extractStatsWithSdk($gameDir, $sdkPath);
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
        // If game is not explicitly provided, get it from the version
        $game = $game ?? $version->game;

        DB::beginTransaction();

        try {
            // Track all languages found in the stats to update supported languages
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
                        if (! $character->first_seen_in_version_id ||
                            $version->published_at < $character->firstSeenVersion->published_at) {
                            $character->first_seen_in_version_id = $version->id;
                            $character->save();
                        }
                        if (! $character->last_seen_in_version_id ||
                            $version->published_at > $character->lastSeenVersion->published_at) {
                            $character->last_seen_in_version_id = $version->id;
                            $character->save();
                        }

                        // Create character version stats
                        VersionCharacterStats::updateOrCreate(
                            [
                                'game_version_id' => $version->id,
                                'character_id' => $character->id,
                                'iso_code' => $isoCode,
                            ],
                            [
                                'blocks' => $charData['blocks'] ?? 0,
                                'words' => $charData['words'] ?? 0,
                            ]
                        );
                    }
                }
            }

            // Update supported languages for this version
            // First, add all languages found in the stats
            foreach ($foundLanguages as $isoCode) {
                if (! str_starts_with($isoCode, 'q')) {
                    $version->addSupportedLanguage($isoCode);
                }
            }

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
                $version->saveFileStats($stats['file_statistics']);
            }

            // Process dialogue lines
            if (isset($stats['dialogue_lines'])) {
                $this->saveDialogueLines($version, $stats['dialogue_lines'], $defaultLanguage, $game);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Save dialogue lines for a game version with text de-duplication
     * Updated to handle character display_names properly and ensure menu_choice character exists
     */
    protected function saveDialogueLines(
        GameVersion $version,
        array $dialogueLines,
        string $defaultLanguage = 'eng',
        ?Game $game = null
    ): void {
        // First, delete any existing dialogue lines for this version
        DialogueLine::where('game_version_id', $version->id)->delete();

        // Ensure the special menu_choice character exists for this game
        $menuChoiceCharacter = Character::firstOrCreate(
            [
                'game_id' => $version->game_id,
                'character_id' => 'menu_choice',
            ],
            [
                'display_names' => ['eng' => 'Menu Choice', $defaultLanguage => 'Menu Choice'],
            ]
        );

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
            foreach (array_chunk($lines, $batchSize) as $chunkIndex => $chunk) {
                // First, collect unique texts for this chunk
                $uniqueTexts = [];

                // Normalize text to remove diacritical marks
                foreach ($chunk as $id => $line) {
                    $text = $line['text'] ?? '';
                    if (empty($text)) {
                        continue;
                    }

                    $chunk[$id]['text'] = $this->processText($text);
                }

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

                // Bulk insert unique texts for this chunk (ignoring duplicates)
                if (! empty($uniqueTexts)) {
                    DB::table('unique_dialogue_texts')->insertOrIgnore(array_values($uniqueTexts));
                }

                // Create a mapping of text hashes to IDs for this chunk
                $textHashes = array_keys($uniqueTexts);
                $textIdMapping = DB::table('unique_dialogue_texts')
                    ->whereIn('text_hash', $textHashes)
                    ->pluck('id', 'text_hash')
                    ->toArray();

                // Now process dialogue lines for this chunk
                $dialogueBatch = [];

                foreach ($chunk as $line) {
                    // Skip empty text
                    $text = $line['text'] ?? '';
                    if (empty($text)) {
                        continue;
                    }

                    // Find character ID
                    $characterId = null;

                    // Special handling for menu_choice
                    if (! empty($line['character']) && $line['character'] === 'menu_choice') {
                        $characterId = $menuChoiceCharacter->id;
                    } // Normal character handling
                    elseif (! empty($line['character']) && $line['character'] !== 'narrator') {
                        // Fixed: Provide default values for character creation to avoid NOT NULL violation
                        $character = Character::firstOrCreate(
                            [
                                'game_id' => $version->game_id,
                                'character_id' => $line['character'],
                            ],
                            [
                                // Set a default display_names value to satisfy NOT NULL constraint
                                'display_names' => [$isoCode => $line['character']],
                            ]
                        );

                        // Ensure display_names has the current language if it's a new key
                        if (! isset($character->display_names[$isoCode])) {
                            $displayNames = $character->display_names;
                            $displayNames[$isoCode] = $line['character'];
                            $character->display_names = $displayNames;
                            $character->save();
                        }

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

                // Insert dialogue lines for this chunk
                if (! empty($dialogueBatch)) {
                    // Process in smaller sub-batches to avoid parameter limits
                    foreach (array_chunk($dialogueBatch, 1000) as $subBatch) {
                        DialogueLine::insert($subBatch);
                    }
                }

                // Log progress for large datasets
                Log::info("Processed {$processedLines}/{$totalLines} dialogue lines for language {$isoCode} ({$chunkIndex} of " .
                    ceil($totalLines / $batchSize) . ' chunks)');
            }
        }

        // Log completion
        Log::info("Finished saving dialogue lines for game version {$version->id}");
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
}
