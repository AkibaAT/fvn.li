<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\LanguageMapping;
use App\Models\VersionCharacterStats;
use App\Models\VersionLanguageStats;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class GameVersionAnalyzer
{
    private const string TEMP_PATH = 'temp/game-analysis/';
    private const string WORDCOUNTER_SCRIPT = 'wordcounter.rpy';

    public function __construct(
        private readonly string $itchApiKey,
        private readonly ProxyRotator $proxy
    ) {}

    public function refreshVersion(Game $game, bool $force = false): void
    {
        // Get all uploads for the game
        $response = $this->proxy->request()
            ->withToken($this->itchApiKey)
            ->get("https://api.itch.io/games/{$game->game_id}/uploads");

        if ($response->status() === 404) {
            $game->is_visible = false;
            $game->save();

            return;
        }

        $response->throw();
        $uploadsData = $response->json();

        if (! isset($uploadsData['uploads']) || empty($uploadsData['uploads'])) {
            return;
        }

        // Reset platform flags
        $game->is_windows = false;
        $game->is_linux = false;
        $game->is_mac = false;
        $game->is_android = false;
        $game->is_web = false;

        $seenUploads = $game->uploads ?? [];
        $hasChanges = false;
        $candidateUploads = [];

        foreach ($uploadsData['uploads'] as $upload) {
            $fileId = (string) $upload['id'];
            $currentFilename = $upload['filename'];
            $currentDisplayName = $upload['display_name'] ?? null;
            $currentMd5 = $upload['md5_hash'] ?? null;
            $currentUpdatedAt = $upload['updated_at'];
            $currentBuildId = $upload['build']['id'] ?? null;
            $currentBuildUpdatedAt = $upload['build']['updated_at'] ?? null;
            $currentUserVersion = $upload['build']['user_version'] ?? null;

            // Update platform flags
            if (isset($upload['traits'])) {
                if (in_array('p_windows', $upload['traits'], true)) {
                    $game->is_windows = true;
                }
                if (in_array('p_linux', $upload['traits'], true)) {
                    $game->is_linux = true;
                }
                if (in_array('p_osx', $upload['traits'], true)) {
                    $game->is_mac = true;
                }
                if (in_array('p_android', $upload['traits'], true)) {
                    $game->is_android = true;
                }
            }
            if ($upload['type'] === 'html') {
                $game->is_web = true;
            }

            // Check if upload is new or changed
            $isNewOrChanged =
                ! isset($seenUploads[$fileId]) ||
                $seenUploads[$fileId]['md5_hash'] !== $currentMd5 ||
                $seenUploads[$fileId]['updated_at'] !== $currentUpdatedAt ||
                $seenUploads[$fileId]['build_id'] !== $currentBuildId ||
                $seenUploads[$fileId]['build_updated_at'] !== $currentBuildUpdatedAt;

            if ($isNewOrChanged) {
                $hasChanges = true;
                $seenUploads[$fileId] = [
                    'display_name' => $currentDisplayName,
                    'md5_hash' => $currentMd5,
                    'updated_at' => $currentUpdatedAt,
                    'build_id' => $currentBuildId,
                    'build_updated_at' => $currentBuildUpdatedAt,
                    'user_version' => $currentUserVersion,
                    'filename' => $currentFilename,
                ];
                $candidateUploads[] = $upload;
            }
        }

        $game->uploads = $seenUploads;
        $game->save();

        if (! $hasChanges && ! $force) {
            return;
        }

        // Sort uploads by priority
        usort($candidateUploads, function ($a, $b) {
            $scoreA = $this->getUploadPriorityScore($a);
            $scoreB = $this->getUploadPriorityScore($b);

            return $scoreB <=> $scoreA;
        });

        $uploadToProcess = $candidateUploads[0] ?? null;
        if (! $uploadToProcess) {
            return;
        }

        $newVersion = $this->extractVersion($uploadToProcess);
        $uploadTimestamp = Carbon::parse($uploadToProcess['updated_at']);

        if ($game->version !== $newVersion || $force) {
            // Create new version
            $gameVersion = new GameVersion([
                'published_at' => $uploadTimestamp,
                'version' => $newVersion,
                'is_windows' => $game->is_windows,
                'is_linux' => $game->is_linux,
                'is_mac' => $game->is_mac,
                'is_android' => $game->is_android,
                'is_web' => $game->is_web,
            ]);

            $game->gameVersions()->save($gameVersion);

            // Get script stats if it's a Ren'Py game
            if ($this->isRenpyGame($uploadToProcess)) {
                $this->analyzeGameStats($game, $gameVersion, $uploadToProcess);
            }
        }
    }

    private function getUploadPriorityScore(array $upload): int
    {
        $score = 0;

        // Prefer Linux builds for analysis
        if (isset($upload['traits']) && in_array('p_linux', $upload['traits'], true)) {
            $score += 100;
        }

        // Then Windows builds
        if (isset($upload['traits']) && in_array('p_windows', $upload['traits'], true)) {
            $score += 50;
        }

        // Prefer ZIP files
        if (Str::endsWith(strtolower($upload['filename']), '.zip')) {
            $score += 25;
        }

        // Add points for recency
        $updatedAt = Carbon::parse($upload['updated_at']);
        $score += $updatedAt->diffInDays(Carbon::now());

        return $score;
    }

    private function extractVersion(array $upload): string
    {
        $version = null;
        $versionRegex = '/(?:[vV](?:ersion)?)?(\d+(?:\.\d+){0,3}[a-zA-Z]?)(?=[-\s._)]|$)/';

        // Try build.user_version first
        if (isset($upload['build']['user_version'])) {
            $version = $this->normalizeVersion($upload['build']['user_version']);
        }

        // Try display name
        if (! $version && isset($upload['display_name'])) {
            if (preg_match($versionRegex, $upload['display_name'], $matches)) {
                $version = $this->normalizeVersion($matches[1]);
            }
        }

        // Try filename
        if (! $version) {
            $filename = preg_replace('/\.(zip|tar\.bz2|tar\.gz)$/i', '', $upload['filename']);
            if (preg_match($versionRegex, $filename, $matches)) {
                $version = $this->normalizeVersion($matches[1]);
            }
        }

        // Fallback to timestamp
        if (! $version) {
            return Carbon::parse($upload['updated_at'])->format('Y.m.d');
        }

        return $version;
    }

    private function normalizeVersion(string $version): ?string
    {
        // Remove leading 'v' or 'version'
        $version = preg_replace('/^[vV]ersion\s*/', '', $version);
        $version = preg_replace('/^[vV]\s*/', '', $version);

        // Parse semantic version parts
        if (preg_match('/^(\d+(?:\.\d+)*)([a-zA-Z])?$/', $version, $matches)) {
            $numbers = explode('.', $matches[1]);

            // Validate number ranges
            $firstNumber = (int) $numbers[0];
            if ($firstNumber > 100 && ! ($firstNumber >= 1990 && $firstNumber <= 2100)) {
                return null;
            }

            if (array_filter($numbers, fn ($n) => (int) $n > 10000)) {
                return null;
            }

            // Reconstruct version
            $version = implode('.', $numbers);
            if (isset($matches[2])) {
                $version .= $matches[2];
            }

            return $version;
        }

        return null;
    }

    private function isRenpyGame(array $upload): bool
    {
        $filename = strtolower($upload['filename']);

        return Str::endsWith($filename, '.zip') &&
            (Str::contains($filename, 'renpy') ||
                Str::contains($upload['display_name'] ?? '', 'Ren\'Py'));
    }

    private function analyzeGameStats(Game $game, GameVersion $gameVersion, array $upload): void
    {
        $downloadUrl = $this->getDownloadUrl($game->game_id, $upload['id']);
        $tempPath = storage_path('app/' . self::TEMP_PATH);

        try {
            // Prepare temp directory
            if (! file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $zipPath = $tempPath . $upload['filename'];

            // Download file
            $response = Http::withToken($this->itchApiKey)->get($downloadUrl);
            $response->throw();
            file_put_contents($zipPath, $response->body());

            // Extract and analyze
            $extractPath = $tempPath . $upload['id'];
            $this->extractAndAnalyze($zipPath, $extractPath, $gameVersion);

        } finally {
            // Cleanup
            @unlink($zipPath);
            if (file_exists($extractPath)) {
                $this->removeDirectory($extractPath);
            }
        }
    }

    private function getDownloadUrl(int $gameId, int $uploadId): string
    {
        $response = $this->proxy->request()
            ->withToken($this->itchApiKey)
            ->post("https://itch.io/api/1/{$gameId}/download/{$uploadId}");

        $response->throw();
        $data = $response->json();

        return $data['url'] ?? throw new RuntimeException('No download URL provided');
    }

    private function extractAndAnalyze(string $zipPath, string $extractPath, GameVersion $gameVersion): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Failed to open ZIP file');
        }

        $zip->extractTo($extractPath);
        $zip->close();

        // Copy wordcounter script
        $gamePath = $this->findGameDirectory($extractPath);
        if (! $gamePath || ! is_dir($gamePath . '/game')) {
            throw new RuntimeException('Invalid game directory structure');
        }

        copy(
            resource_path('scripts/' . self::WORDCOUNTER_SCRIPT),
            $gamePath . '/game/' . self::WORDCOUNTER_SCRIPT
        );

        // Run analysis
        $this->runRenpyAnalysis($gamePath);

        // Process results
        $statsFile = $gamePath . '/stats.json';
        if (! file_exists($statsFile)) {
            throw new RuntimeException('Analysis failed to produce stats file');
        }

        $stats = json_decode(file_get_contents($statsFile), true);
        $this->processStats($gameVersion, $stats);
    }

    private function findGameDirectory(string $extractPath): ?string
    {
        $items = scandir($extractPath);
        $items = array_diff($items, ['.', '..']);

        if (count($items) === 1 && is_dir($extractPath . '/' . $items[0])) {
            return $extractPath . '/' . reset($items);
        }

        return $extractPath;
    }

    private function runRenpyAnalysis(string $gamePath): void
    {
        // Copy Ren'Py runtime if needed
        if (! is_dir($gamePath . '/lib/py3-linux-x86_64')) {
            copy(
                resource_path('scripts/renpy.py'),
                $gamePath . '/renpy.py'
            );
            copy(
                resource_path('scripts/renpy.sh'),
                $gamePath . '/renpy.sh'
            );

            $runtimePath = resource_path('scripts/py3-linux-x86_64');
            $targetPath = $gamePath . '/lib/py3-linux-x86_64';

            mkdir($targetPath, 0755, true);
            $this->copyDirectory($runtimePath, $targetPath);
        }

        // Make scripts executable
        $files = glob($gamePath . '/*.sh');
        foreach ($files as $file) {
            chmod($file, 0755);
        }

        // Run analysis
        $scriptFile = basename($files[0]);
        exec("cd {$gamePath} && ./{$scriptFile} game test");
    }

    private function processStats(GameVersion $gameVersion, array $stats): void
    {
        foreach ($stats['languages'] as $langKey => $langStats) {
            // Map language key to ISO code
            $isoCode = $this->mapLanguageKey($langKey);
            if (! $isoCode) {
                continue;
            }

            // Create language stats
            $languageStats = new VersionLanguageStats([
                'iso_code' => $isoCode,
                'blocks' => $langStats['blocks'],
                'words' => $langStats['words'],
                'menus' => $langStats['menus'],
                'options' => $langStats['options'],
            ]);

            $gameVersion->languageStats()->save($languageStats);

            // Process character stats
            foreach ($langStats['characters'] as $charId => $charStats) {
                if ($charId === 'narrator') {
                    $displayName = 'Narrator';
                } else {
                    $displayName = $charStats['display_name'] ?? $charId;
                }

                $characterStats = new VersionCharacterStats([
                    'iso_code' => $isoCode,
                    'character_id' => $charId,
                    'display_name' => $displayName,
                    'blocks' => $charStats['blocks'],
                    'words' => $charStats['words'],
                ]);

                $gameVersion->characterStats()->save($characterStats);
            }
        }
    }

    private function mapLanguageKey(string $key): ?string
    {
        // Map common language keys to ISO codes
        return match (strtolower($key)) {
            'default', 'english' => 'eng',
            'french', 'francais', 'français' => 'fra',
            'chinese', 'chinese (simplified)' => 'zho',
            'japanese' => 'jpn',
            'korean' => 'kor',
            'german', 'deutsch' => 'deu',
            'spanish', 'español', 'espanol' => 'spa',
            'russian', 'русский' => 'rus',
            'italian', 'italiano' => 'ita',
            'portuguese' => 'por',
            'brazilian portuguese', 'português do brasil' => 'pob',
            'vietnamese' => 'vie',
            'thai' => 'tha',
            'arabic', 'العربية' => 'ara',
            'indonesian' => 'ind',
            'polish', 'polski' => 'pol',
            'turkish', 'türkçe' => 'tur',
            default => LanguageMapping::where('game_language_key', $key)->value('iso_code'),
        };
    }

    private function copyDirectory(string $source, string $dest): void
    {
        $dir = opendir($source);
        @mkdir($dest);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourcePath = $source . '/' . $file;
            $destPath = $dest . '/' . $file;

            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destPath);
            } else {
                copy($sourcePath, $destPath);
            }
        }

        closedir($dir);
    }

    private function removeDirectory(string $path): void
    {
        if (! file_exists($path)) {
            return;
        }

        if (is_file($path)) {
            unlink($path);

            return;
        }

        $files = array_diff(scandir($path), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $path . '/' . $file;
            if (is_dir($filePath)) {
                $this->removeDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }

        rmdir($path);
    }
}
