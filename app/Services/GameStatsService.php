<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\GameVersion;
use App\Models\Language;
use App\Models\LanguageMapping;
use App\Models\VersionCharacterStats;
use App\Models\VersionLanguageStats;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Service for extracting and processing game script statistics
 */
readonly class GameStatsService
{
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
                throw new RuntimeException('Could not find valid game directory');
            }

            // Copy Ren'Py analysis files
            $this->copyRenpyFiles($gameDir);

            // Find and execute the game launcher
            $launcher = $this->findGameLauncher($gameDir);
            if (! $launcher) {
                throw new RuntimeException('Could not find game launcher');
            }

            // Make files executable
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($extractPath),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if (! is_executable($file->getPathname())) {
                    chmod($file->getPathname(), 0755);
                }
            }

            // Execute the script analysis
            $process = new Process([$launcher, 'game', 'test'], $gameDir);
            $process->setTimeout(300); // 5 minute timeout
            $process->run();

            // Check for successful execution
            if (! $process->isSuccessful()) {
                throw new RuntimeException(
                    'Script analysis failed: ' . $process->getOutput()
                );
            }

            // Read and parse the stats file
            $statsFile = $gameDir . '/stats.json';
            if (! File::exists($statsFile)) {
                throw new RuntimeException('Stats file not generated');
            }

            $stats = json_decode(File::get($statsFile), true);
            if (! $stats || ! isset($stats['languages'])) {
                throw new RuntimeException('Invalid stats file format');
            }

            return $stats;

        } finally {
            // Cleanup
            if (File::exists($extractPath)) {
                File::deleteDirectory($extractPath);
            }
        }
    }

    /**
     * Save or update language and character statistics for a game version
     */
    public function saveVersionStats(GameVersion $version, array $stats, string $defaultLanguage = 'eng'): void
    {
        DB::beginTransaction();

        try {
            // Track all languages found in the stats to update supported languages
            $foundLanguages = [];

            foreach ($stats['languages'] as $langKey => $langData) {
                $isoCode = $langKey === 'default' ? $defaultLanguage : $this->mapLanguageCode($langKey);

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
                $version->addSupportedLanguage($isoCode);
            }

            // Save file statistics
            if (isset($stats['file_statistics'])) {
                $version->saveFileStats($stats['file_statistics']);
            }

            // Process dialogue lines
            if (isset($stats['dialogue_lines'])) {
                $this->saveDialogueLines($version, $stats['dialogue_lines'], $defaultLanguage);
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
        string $defaultLanguage = 'eng'
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
            $isoCode = $langKey === 'default' ? $defaultLanguage : $this->mapLanguageCode($langKey);

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
                    foreach (array_chunk($dialogueBatch, 1000) as $subBatchIndex => $subBatch) {
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
     * Extract a game archive to the specified directory
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
     * Copy required Ren'Py analysis files to the game directory
     */
    private function copyRenpyFiles(string $gameDir): void
    {
        // Copy json_stats script
        File::copy(
            resource_path('renpy/json_stats.rpy'),
            $gameDir . '/game/json_stats.rpy'
        );

        // Check if we need to copy Ren'Py runtime files
        $hasRenpyRuntime = false;
        foreach (['py2-linux-x86_64', 'py3-linux-x86_64', 'linux-x86_64'] as $dir) {
            if (File::isDirectory($gameDir . '/lib/' . $dir)) {
                $hasRenpyRuntime = true;
                break;
            }
        }

        if (! $hasRenpyRuntime) {
            File::copy(
                resource_path('renpy/renpy.py'),
                $gameDir . '/renpy.py'
            );
            File::copy(
                resource_path('renpy/renpy.sh'),
                $gameDir . '/renpy.sh'
            );
            File::copyDirectory(
                resource_path('renpy/py3-linux-x86_64'),
                $gameDir . '/lib/py3-linux-x86_64'
            );
        }
    }

    /**
     * Find the game launcher script
     */
    private function findGameLauncher(string $gameDir): ?string
    {
        $files = File::files($gameDir);
        foreach ($files as $file) {
            if ($file->getExtension() === 'sh') {
                return $file->getPathname();
            }
        }

        return null;
    }

    /**
     * Map a game language code to a standardized ISO code
     */
    private function mapLanguageCode(string $gameLanguage): ?string
    {
        // If this is the default language, use the game's source language
        if ($gameLanguage === 'default') {
            return null;
        }

        // First try exact matches
        $mapping = LanguageMapping::where('game_language_key', 'ilike', $gameLanguage)->first();
        if ($mapping) {
            return $mapping->iso_code;
        }

        // Try to find a matching language
        $language = Language::where('id', 'ilike', $gameLanguage)
            ->orWhere('part1', 'ilike', $gameLanguage)
            ->orWhere('part2b', 'ilike', $gameLanguage)
            ->orWhere('part2t', 'ilike', $gameLanguage)
            ->first();

        if ($language) {
            // Create mapping for future use
            LanguageMapping::create([
                'game_language_key' => $gameLanguage,
                'iso_code' => $language->id,
            ]);

            return $language->id;
        }

        // Generate a new placeholder code in the qaa-qtz range
        $highestPlaceholder = LanguageMapping::where('iso_code', 'like', 'q%')
            ->orderByDesc('iso_code')
            ->value('iso_code');

        $newCode = $highestPlaceholder
            ? $this->generateNextPlaceholderCode($highestPlaceholder)
            : 'qaa';

        // Create mapping with placeholder code
        LanguageMapping::create([
            'game_language_key' => $gameLanguage,
            'iso_code' => $newCode,
        ]);

        Log::info("Created placeholder mapping for {$gameLanguage}: {$newCode}");

        return $newCode;
    }

    /**
     * Generate the next placeholder language code
     */
    private function generateNextPlaceholderCode(string $current): string
    {
        $lastChar = substr($current, -1);
        if ($lastChar === 'z') {
            $middleChar = substr($current, -2, 1);
            if ($middleChar === 'z') {
                throw new RuntimeException('No more placeholder codes available');
            }

            return 'q' . chr(ord($middleChar) + 1) . 'a';
        }

        return substr($current, 0, -1) . chr(ord($lastChar) + 1);
    }
}
