<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class DenKitStashPersistenceService
{
    private const OPTIMIZATION_METADATA_SCHEMA = 'fvn.archive_optimization.v1';

    public function __construct(
        private readonly GameArchiveService $archiveService,
        private readonly ?Client $httpClient = null
    ) {}

    /**
     * @return array{status: string, target: string, channel: string, build_id?: int|null, output?: string}
     */
    public function persistOptimizedArchive(
        Game $game,
        GameVersion $version,
        string $archivePath,
        string $channel = 'main',
        bool $force = false
    ): array {
        $this->assertOptimizedArchive($archivePath);

        $username = $this->username();
        $targetName = $this->targetName($game);
        $target = "{$username}/{$targetName}:{$channel}";

        $apiKey = $this->apiKey();
        if (! $force && $this->versionAlreadyPersisted($username, $targetName, $channel, (string) $version->version)) {
            return [
                'status' => 'skipped',
                'target' => $target,
                'channel' => $channel,
            ];
        }

        $workDir = storage_path('app/temp/' . uniqid('butler_push_', true));
        try {
            File::makeDirectory($workDir, 0755, true);
            $this->extractArchive($archivePath, $workDir);

            $process = new Process([
                $this->butlerBinary(),
                '--address=' . $this->serverUrl(),
                '--assume-yes',
                'push',
                $this->pushDirectory($workDir),
                $target,
                '--userversion',
                (string) $version->version,
            ]);
            $process->setTimeout(null);
            $process->setEnv([
                'BUTLER_API_KEY' => $apiKey,
                'HOME' => getenv('HOME') ?: '/tmp',
            ]);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'butler push failed');
            }

            $buildId = $this->latestBuildId($username, $targetName, $channel, (string) $version->version);
            if ($buildId === null) {
                throw new RuntimeException("butler push finished, but no completed build was recorded for {$target}");
            }

            return [
                'status' => 'persisted',
                'target' => $target,
                'channel' => $channel,
                'build_id' => $buildId,
                'output' => trim($process->getOutput()),
            ];
        } finally {
            if (File::exists($workDir)) {
                File::deleteDirectory($workDir);
            }
        }
    }

    public function isEnabled(): bool
    {
        return (bool) Config::get('services.denkit_stash.enabled', true)
            && is_string(Config::get('services.denkit_stash.api_key'))
            && Config::get('services.denkit_stash.api_key') !== '';
    }

    public function isAutoPersistEnabled(): bool
    {
        return $this->isEnabled() && (bool) Config::get('services.denkit_stash.auto_persist', false);
    }

    public function shouldDeleteLocalAfterPush(): bool
    {
        return (bool) Config::get('services.denkit_stash.delete_local_after_push', false);
    }

    /**
     * @return array{status: string, target: string, channel: string, archive_path?: string, build_id?: int|null}|null
     */
    public function restorePersistedArchive(Game $game, GameVersion $version, string $storagePath, string $channel = 'main'): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $username = $this->username();
        $targetName = $this->targetName($game);
        $buildId = $this->latestBuildId($username, $targetName, $channel, (string) $version->version);
        if ($buildId === null) {
            return null;
        }

        $target = "{$username}/{$targetName}:{$channel}";
        $workDir = storage_path('app/temp/' . uniqid('butler_restore_', true));
        $archivePath = "{$workDir}/archive-{$buildId}.zip";

        try {
            File::makeDirectory($workDir, 0755, true);
            $this->downloadBuildArchive($buildId, $archivePath);

            $filename = "denkit-stash-{$buildId}.zip";
            Storage::makeDirectory($storagePath);
            Storage::putFileAs($storagePath, $archivePath, $filename);

            return [
                'status' => 'restored',
                'target' => $target,
                'channel' => $channel,
                'archive_path' => Storage::path("{$storagePath}/{$filename}"),
                'build_id' => $buildId,
            ];
        } finally {
            if (File::exists($workDir)) {
                File::deleteDirectory($workDir);
            }
        }
    }

    public function defaultChannel(): string
    {
        return 'main';
    }

    private function downloadBuildArchive(int $buildId, string $archivePath): void
    {
        $client = new Client([
            'timeout' => 600,
            'connect_timeout' => 30,
            'allow_redirects' => false,
        ]);
        $response = $client->get($this->serverUrl() . "/builds/{$buildId}/download/archive/default", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey(),
                'Accept' => 'application/json',
            ],
        ]);

        if ($response->getStatusCode() !== 307) {
            throw new RuntimeException("Expected DenKit Stash archive redirect for build {$buildId}, got HTTP {$response->getStatusCode()}");
        }

        $location = $response->getHeaderLine('Location');
        if ($location === '') {
            throw new RuntimeException("DenKit Stash archive redirect for build {$buildId} did not include a Location header");
        }

        $download = (new Client([
            'timeout' => 600,
            'connect_timeout' => 30,
            'allow_redirects' => true,
        ]))->get($location, [
            'sink' => $archivePath,
        ]);

        if ($download->getStatusCode() < 200 || $download->getStatusCode() >= 300) {
            throw new RuntimeException("Failed to download DenKit Stash archive for build {$buildId}: HTTP {$download->getStatusCode()}");
        }
    }

    private function assertOptimizedArchive(string $archivePath): void
    {
        $metadata = $this->archiveService->readArchiveMetadata($archivePath);
        if (($metadata['schema'] ?? null) !== self::OPTIMIZATION_METADATA_SCHEMA) {
            throw new RuntimeException("Archive is not an optimized fvn.li archive: {$archivePath}");
        }
    }

    private function username(): string
    {
        return (string) Config::get('services.denkit_stash.username', 'fvn-li');
    }

    private function serverUrl(): string
    {
        return rtrim((string) Config::get('services.denkit_stash.url', 'http://denkit-stash:8081'), '/');
    }

    private function targetName(Game $game): string
    {
        $slug = (string) ($game->slug ?: Str::slug((string) $game->name));

        return $slug !== '' ? $slug : 'game-' . $game->id;
    }

    private function butlerBinary(): string
    {
        $clientPath = rtrim((string) Config::get('services.denkit_stash.client_path', '/var/www/butler-client'), '/');
        $binary = "{$clientPath}/butler";

        if (is_executable($binary)) {
            return $binary;
        }

        if (! File::isDirectory($clientPath)) {
            throw new RuntimeException("butler-client checkout is not mounted at {$clientPath}");
        }

        $builtBinary = storage_path('app/temp/fvn-butler-client');
        File::ensureDirectoryExists(dirname($builtBinary));

        $process = new Process(['go', 'build', '-buildvcs=false', '-o', $builtBinary, '.'], $clientPath);
        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Failed to build butler client');
        }

        return $builtBinary;
    }

    private function apiKey(): string
    {
        $apiKey = Config::get('services.denkit_stash.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('DENKIT_STASH_API_KEY is required to use DenKit Stash');
        }

        return $apiKey;
    }

    private function versionAlreadyPersisted(string $username, string $targetName, string $channel, string $userVersion): bool
    {
        return $this->latestBuildId($username, $targetName, $channel, $userVersion) !== null;
    }

    private function latestBuildId(string $username, string $targetName, string $channel, string $userVersion): ?int
    {
        $response = $this->httpClient([
            'timeout' => 30,
            'connect_timeout' => 10,
        ])->get($this->serverUrl() . '/wharf/builds/latest', [
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'target' => "{$username}/{$targetName}",
                'channel' => $channel,
                'user_version' => $userVersion,
            ],
        ]);

        if ($response->getStatusCode() === 404) {
            return null;
        }

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException("Failed to query DenKit Stash build lookup: HTTP {$response->getStatusCode()}");
        }

        $data = json_decode((string) $response->getBody(), true);
        $id = $data['build']['id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function httpClient(array $options = []): Client
    {
        return $this->httpClient ?? new Client($options);
    }

    private function extractArchive(string $archivePath, string $targetPath): void
    {
        $extension = $this->archiveExtension($archivePath);
        if ($extension === 'zip') {
            $zip = new ZipArchive;
            $result = $zip->open($archivePath);
            if ($result !== true) {
                throw new RuntimeException("Failed to open optimized archive: {$result}");
            }

            try {
                if (! $zip->extractTo($targetPath)) {
                    throw new RuntimeException('Failed to extract optimized archive');
                }
            } finally {
                $zip->close();
            }

            return;
        }

        $args = match ($extension) {
            'tar' => ['tar', '-xf', $archivePath, '-C', $targetPath],
            'tar.gz', 'tgz' => ['tar', '-xzf', $archivePath, '-C', $targetPath],
            'tar.bz2', 'tbz2' => ['tar', '-xjf', $archivePath, '-C', $targetPath],
            default => throw new RuntimeException("Unsupported optimized archive format: {$extension}"),
        };

        $process = new Process($args);
        $process->setTimeout(600);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Failed to extract optimized archive: ' . $process->getErrorOutput());
        }
    }

    private function pushDirectory(string $extractPath): string
    {
        if (File::isDirectory($extractPath . '/game')) {
            return $extractPath;
        }

        $directories = File::directories($extractPath);
        if (count($directories) === 1 && File::isDirectory($directories[0] . '/game')) {
            return $directories[0];
        }

        return $extractPath;
    }

    private function archiveExtension(string $archivePath): string
    {
        $basename = strtolower(basename($archivePath));

        return match (true) {
            str_ends_with($basename, '.tar.gz') => 'tar.gz',
            str_ends_with($basename, '.tgz') => 'tgz',
            str_ends_with($basename, '.tar.bz2') => 'tar.bz2',
            str_ends_with($basename, '.tbz2') => 'tbz2',
            str_ends_with($basename, '.tar') => 'tar',
            str_ends_with($basename, '.zip') => 'zip',
            default => strtolower(pathinfo($archivePath, PATHINFO_EXTENSION)),
        };
    }
}
