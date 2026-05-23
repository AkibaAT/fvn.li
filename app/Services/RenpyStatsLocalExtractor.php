<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class RenpyStatsLocalExtractor
{
    private ?string $lastError = null;

    /**
     * Extract statistics locally. This mode is intended only for trusted local
     * fixtures and explicit development fallback, never untrusted production input.
     */
    public function extract(string $archivePath): ?array
    {
        $this->lastError = null;
        Log::info('GameStats: Starting extraction', [
            'archive_path' => basename($archivePath),
        ]);

        $extractPath = storage_path('app/temp/'.uniqid('game_', true));
        File::makeDirectory($extractPath, 0755, true);

        try {
            Log::info('GameStats: Extracting archive', [
                'extract_path' => $extractPath,
            ]);
            $this->extractArchive($archivePath, $extractPath);
            Log::info('GameStats: Archive extracted successfully');

            Log::info('GameStats: Finding game directory');
            $gameDir = $this->findGameDirectory($extractPath);
            if (! $gameDir) {
                $this->lastError = 'Could not find valid game directory';
                Log::warning('Could not find valid game directory', [
                    'archive_path' => $archivePath,
                    'extract_path' => $extractPath,
                ]);

                return null;
            }

            Log::info('GameStats: Game directory found', [
                'game_dir' => basename($gameDir),
            ]);

            Log::info('GameStats: Attempting to use Ren\'Py SDK');
            $sdkPath = config('services.renpy.sdk_path');
            if (! $sdkPath || ! File::exists($sdkPath.'/renpy.sh')) {
                $this->lastError = 'Ren\'Py SDK path not configured or invalid';
                Log::error('Ren\'Py SDK path not configured or invalid', [
                    'sdk_path' => $sdkPath,
                ]);

                return null;
            }

            Log::info('GameStats: Running Ren\'Py SDK analysis', [
                'game_dir' => basename($gameDir),
            ]);
            $stats = $this->extractStatsWithSdk($gameDir, $sdkPath);
            Log::info('GameStats: SDK analysis completed', [
                'has_stats' => $stats !== null,
            ]);

            return $stats;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            Log::warning('Error during game stats extraction', [
                'archive_path' => $archivePath,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return null;
        } finally {
            if (File::exists($extractPath)) {
                File::deleteDirectory($extractPath);
            }
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Extract a game archive to the specified directory.
     */
    public function extractArchive(string $archivePath, string $extractPath): void
    {
        $archiveFormat = $this->detectArchiveFormat($archivePath);

        if ($archiveFormat === 'tar.gz' || $archiveFormat === 'tar.bz2') {
            $process = new Process([
                'tar',
                '-x'.($archiveFormat === 'tar.gz' ? 'z' : 'j'),
                '-f',
                $archivePath,
                '-C',
                $extractPath,
            ]);

            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Failed to extract tar archive: '.$process->getErrorOutput());
            }

            return;
        }

        if ($archiveFormat === 'zip') {
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

        if ($archiveFormat === 'tar') {
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
                throw new RuntimeException('Failed to extract tar archive: '.$process->getErrorOutput());
            }

            return;
        }

        throw new RuntimeException("Unsupported archive format: {$archiveFormat}");
    }

    public function detectArchiveFormat(string $archivePath): string
    {
        $handle = fopen($archivePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Failed to open archive: {$archivePath}");
        }

        try {
            $header = fread($handle, 512);
        } finally {
            fclose($handle);
        }

        if (str_starts_with($header, "PK\x03\x04") || str_starts_with($header, "PK\x05\x06") || str_starts_with($header, "PK\x07\x08")) {
            return 'zip';
        }

        if (str_starts_with($header, "\x1F\x8B")) {
            return 'tar.gz';
        }

        if (str_starts_with($header, 'BZh')) {
            return 'tar.bz2';
        }

        if (substr($header, 257, 5) === 'ustar') {
            return 'tar';
        }

        $ext = strtolower(pathinfo($archivePath, PATHINFO_EXTENSION));

        if ($ext === 'gz' || $ext === 'bz2') {
            $basename = basename($archivePath, ".{$ext}");
            if (strtolower(pathinfo($basename, PATHINFO_EXTENSION)) === 'tar') {
                return "tar.{$ext}";
            }
        }

        return $ext;
    }

    public function findGameDirectory(string $basePath): ?string
    {
        if (File::isDirectory($basePath.'/game')) {
            return $basePath;
        }

        return array_find(File::directories($basePath), fn ($dir) => File::isDirectory($dir.'/game'));
    }

    /**
     * @throws RuntimeException|FileNotFoundException
     */
    public function extractStatsWithSdk(string $gameDir, string $sdkPath): ?array
    {
        try {
            File::copy(
                resource_path('renpy/json_stats.rpy'),
                $gameDir.'/game/json_stats.rpy'
            );
        } catch (Exception $e) {
            $this->lastError = 'Failed to copy analysis script: '.$e->getMessage();
            Log::warning('Failed to copy analysis script', [
                'error' => $e->getMessage(),
                'game_dir' => $gameDir,
            ]);

            return null;
        }

        $process = new Process([$sdkPath.'/renpy.sh', 'game', 'test'], $gameDir);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->lastError = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Ren\'Py SDK analysis failed';
            Log::warning('Script analysis completed with non-zero exit code', [
                'output' => $process->getOutput(),
                'error_output' => $process->getErrorOutput(),
                'exit_code' => $process->getExitCode(),
                'sdk_path' => $sdkPath,
                'game_dir' => $gameDir,
            ]);
        }

        $statsFile = $gameDir.'/stats.json';
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
