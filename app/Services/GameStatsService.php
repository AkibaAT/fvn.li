<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
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
            foreach ($stats['languages'] as $langKey => $langData) {
                $isoCode = $langKey === 'default' ? $defaultLanguage : $this->mapLanguageCode($langKey);

                if (! $isoCode) {
                    Log::warning("Skipping language {$langKey} - could not determine ISO code");

                    continue;
                }

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

            // Save file statistics
            if (isset($stats['file_statistics'])) {
                $version->saveFileStats($stats['file_statistics']);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Extract a game archive to the specified directory
     */
    private function extractArchive(string $archivePath, string $extractPath): void
    {
        $ext = strtolower(pathinfo($archivePath, PATHINFO_EXTENSION));

        // Handle tar.gz and tar.bz2 files
        if ($ext === 'gz' || $ext === 'bz2') {
            if (strtolower(pathinfo(basename($archivePath, ".{$ext}"), PATHINFO_EXTENSION)) !== 'tar') {
                throw new RuntimeException("Unsupported archive format: {$ext} (not a tar archive)");
            }

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
