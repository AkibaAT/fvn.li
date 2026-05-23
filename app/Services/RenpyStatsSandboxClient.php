<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RenpyStatsSandboxClient
{
    private ?string $lastError = null;

    public function extract(string $archivePath): ?array
    {
        $this->lastError = null;
        $url = config('services.renpy.analyzer_url');
        $token = config('services.renpy.analyzer_token');

        if (! is_string($url) || $url === '' || ! is_string($token) || $token === '') {
            $this->lastError = 'Sandbox analyzer is not configured';
            Log::warning('GameStats: sandbox analyzer is not configured', [
                'has_url' => is_string($url) && $url !== '',
                'has_token' => is_string($token) && $token !== '',
            ]);

            return null;
        }

        $requestDir = $this->createRequestDirectory();
        $requestArchive = $requestDir.'/'.basename($archivePath);

        try {
            File::copy($archivePath, $requestArchive);

            $response = Http::timeout((int) config('services.renpy.analyzer_timeout', 300) + 30)
                ->withToken($token)
                ->acceptJson()
                ->post($url, [
                    'archive_path' => $requestArchive,
                ]);

            if (! $response->successful()) {
                $message = $response->json('message');
                $this->lastError = $response->status() === 422
                    ? 'No stats could be extracted'
                    : "Sandbox analyzer request failed with HTTP {$response->status()}";
                Log::warning('GameStats: sandbox analyzer request failed', [
                    'status' => $response->status(),
                    'message' => is_string($message) ? $this->sanitizeDiagnosticOutput($message) : null,
                ]);

                return null;
            }

            $stats = $response->json('stats');
            if (! is_array($stats) || ! isset($stats['languages']) || ! is_array($stats['languages'])) {
                $this->lastError = 'Sandbox analyzer returned invalid stats payload';
                Log::warning('GameStats: sandbox analyzer returned invalid stats payload');

                return null;
            }

            return $stats;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('GameStats: sandbox analyzer request errored', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return null;
        } finally {
            File::deleteDirectory($requestDir);
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    private function sanitizeDiagnosticOutput(string $output): string
    {
        $output = str_replace("\0", '', $output);
        $limit = 1024;

        if (strlen($output) <= $limit) {
            return $output;
        }

        return substr($output, 0, $limit)."\n[truncated]";
    }

    private function createRequestDirectory(): string
    {
        $basePath = config('services.renpy.analyzer_shared_path');
        if (! is_string($basePath) || $basePath === '') {
            throw new RuntimeException('RenPy analyzer shared path is not configured');
        }

        File::ensureDirectoryExists($basePath, 0700);
        $this->cleanupStaleDirectories($basePath, 'request-');

        $requestDir = rtrim($basePath, '/').'/request-'.bin2hex(random_bytes(12));
        File::makeDirectory($requestDir, 0700, true);

        return $requestDir;
    }

    private function cleanupStaleDirectories(string $basePath, string $prefix): void
    {
        $maxAge = (int) config('services.renpy.analyzer_stale_cleanup_seconds', 7200);
        $expiresBefore = time() - max(60, $maxAge);

        foreach (File::directories($basePath) as $directory) {
            if (! str_starts_with(basename($directory), $prefix)) {
                continue;
            }

            $modifiedAt = @filemtime($directory);
            if ($modifiedAt !== false && $modifiedAt < $expiresBefore) {
                File::deleteDirectory($directory);
            }
        }
    }
}
