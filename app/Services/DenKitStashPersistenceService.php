<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class DenKitStashPersistenceService
{
    private const OPTIMIZATION_METADATA_SCHEMA = 'fvn.archive_optimization.v1';

    private const DEFAULT_MAX_ARCHIVE_ENTRIES = 20000;

    private const DEFAULT_MAX_EXTRACTED_BYTES = 2147483648;

    private ?string $lastRestoreDiagnostic = null;

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

        if (! $force && $this->versionAlreadyPersisted($target, $username, $targetName, $channel, (string) $version->version)) {
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

            $output = $this->runButlerPush($this->pushDirectory($workDir), $target, (string) $version->version);
            $buildId = $this->buildIdFromPushOutput($output);
            if ($buildId === null) {
                throw new RuntimeException("butler push finished, but did not report a completed build for {$target}");
            }

            return [
                'status' => 'persisted',
                'target' => $target,
                'channel' => $channel,
                'build_id' => $buildId,
                'output' => $output,
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

    /**
     * @return array{status: string, target: string, channel: string, archive_path?: string, build_id?: int|null}|null
     */
    public function restorePersistedArchive(Game $game, GameVersion $version, string $storagePath, string $channel = 'main'): ?array
    {
        $this->lastRestoreDiagnostic = null;

        if (! $this->isEnabled()) {
            $this->lastRestoreDiagnostic = 'DenKit Stash is not configured';

            return null;
        }

        $username = $this->username();
        $targetName = $this->targetName($game);
        $target = "{$username}/{$targetName}:{$channel}";
        $buildId = $this->latestBuildId($username, $targetName, $channel, (string) $version->version);
        if ($buildId === null) {
            $this->lastRestoreDiagnostic = "No completed build found for {$target} version {$version->version}";

            return null;
        }

        $workDir = storage_path('app/temp/' . uniqid('butler_restore_', true));
        $archivePath = "{$workDir}/archive-{$buildId}.zip";

        try {
            File::makeDirectory($workDir, 0755, true);
            $this->downloadBuildArchive($buildId, $archivePath);

            $filename = $this->restoredArchiveFilename($archivePath, $buildId);
            $restoredPath = $this->archiveService->storeFileAtomically($storagePath, $archivePath, $filename);

            return [
                'status' => 'restored',
                'target' => $target,
                'channel' => $channel,
                'archive_path' => $restoredPath,
                'build_id' => $buildId,
            ];
        } catch (Throwable $throwable) {
            $this->lastRestoreDiagnostic = "Failed to restore {$target} build #{$buildId}: {$throwable->getMessage()}";

            throw $throwable;
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

    /**
     * Resolve a browser-usable (presigned) download URL for the latest
     * persisted build of a game version. Authorization is the caller's
     * responsibility; the returned URL is self-authenticating and short-lived.
     *
     * @return array{build_id: int, url: string}|null null when no build exists
     *                                                or DenKit Stash did not provide an external URL
     */
    public function archiveDownloadUrl(Game $game, GameVersion $version, string $channel = 'main'): ?array
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

        $url = $this->buildDownloadUrl($buildId);
        if ($url === null) {
            return null;
        }

        return [
            'build_id' => $buildId,
            'url' => $url,
        ];
    }

    /**
     * Resolve persisted optimized-archive availability for a loaded set of versions.
     *
     * @param  iterable<GameVersion>  $versions
     * @return array<int, bool>
     */
    public function persistedArchiveAvailability(Game $game, iterable $versions, string $channel = 'main'): array
    {
        $versionsById = [];
        $availability = [];
        foreach ($versions as $version) {
            if (! $version instanceof GameVersion || $version->game_id !== $game->id) {
                continue;
            }

            $versionsById[$version->id] = (string) $version->version;
            $availability[$version->id] = false;
        }

        if ($versionsById === [] || ! $this->isEnabled()) {
            return $availability;
        }

        $username = $this->username();
        $targetName = $this->targetName($game);
        $cacheKey = 'denkit-stash.archive-availability.' . hash('sha256', "{$username}/{$targetName}:{$channel}");
        try {
            $persistedVersions = Cache::remember($cacheKey, 60, function () use ($username, $targetName, $channel): array {
                $response = $this->httpClient([
                    'timeout' => 5,
                    'connect_timeout' => 2,
                ])->get($this->serverUrl() . '/wharf/builds', [
                    'http_errors' => false,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey(),
                        'Accept' => 'application/json',
                    ],
                    'query' => [
                        'target' => "{$username}/{$targetName}",
                        'channel' => $channel,
                    ],
                ]);

                if ($response->getStatusCode() === 404) {
                    return [];
                }

                if ($response->getStatusCode() !== 200) {
                    throw new RuntimeException("Failed to query DenKit Stash builds: HTTP {$response->getStatusCode()}");
                }

                $data = json_decode((string) $response->getBody(), true);
                $persisted = [];
                foreach (is_array($data['builds'] ?? null) ? $data['builds'] : [] as $build) {
                    if (is_array($build) && ($build['state'] ?? null) === 'completed' && is_string($build['user_version'] ?? null)) {
                        $persisted[$build['user_version']] = true;
                    }
                }

                return $persisted;
            });
        } catch (Throwable $throwable) {
            Cache::put($cacheKey, [], 15);

            throw $throwable;
        }

        foreach ($versionsById as $versionId => $userVersion) {
            $availability[$versionId] = isset($persistedVersions[$userVersion]);
        }

        return $availability;
    }

    public function getLastRestoreDiagnostic(): ?string
    {
        return $this->lastRestoreDiagnostic;
    }

    protected function downloadArchiveUrl(string $url, string $archivePath): void
    {
        $download = (new Client([
            'timeout' => 600,
            'connect_timeout' => 30,
            'allow_redirects' => true,
        ]))->get($url, [
            'sink' => $archivePath,
        ]);

        if ($download->getStatusCode() < 200 || $download->getStatusCode() >= 300) {
            throw new RuntimeException("Failed to download DenKit Stash archive: HTTP {$download->getStatusCode()}");
        }
    }

    protected function runButlerPush(string $pushPath, string $target, string $userVersion): string
    {
        $process = new Process([
            $this->butlerBinary(),
            '--address=' . $this->serverUrl(),
            '--assume-yes',
            'push',
            $pushPath,
            $target,
            '--userversion',
            $userVersion,
            '--json',
        ]);
        $process->setTimeout(null);
        $process->setEnv([
            'BUTLER_API_KEY' => $this->apiKey(),
            'HOME' => getenv('HOME') ?: '/tmp',
        ]);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'butler push failed');
        }

        return trim($process->getOutput());
    }

    private function downloadBuildArchive(int $buildId, string $archivePath): void
    {
        $response = $this->fetchBuildDownloadResponse($buildId);

        if ($response->getStatusCode() === 200) {
            $payload = json_decode((string) $response->getBody(), true);
            $url = is_array($payload) ? ($payload['url'] ?? null) : null;
            if (is_string($url) && $url !== '') {
                $this->downloadArchiveUrl($url, $archivePath);

                return;
            }

            File::put($archivePath, (string) $response->getBody());

            return;
        }

        if ($response->getStatusCode() !== 307) {
            throw new RuntimeException("Expected DenKit Stash archive redirect for build {$buildId}, got HTTP {$response->getStatusCode()}");
        }

        $location = $response->getHeaderLine('Location');
        if ($location === '') {
            throw new RuntimeException("DenKit Stash archive redirect for build {$buildId} did not include a Location header");
        }

        $this->downloadArchiveUrl($location, $archivePath);
    }

    /**
     * Resolve the externally reachable download URL for a build, i.e. the
     * presigned object-storage URL that stash signs against S3_PUBLIC_ENDPOINT.
     * Returns null when stash inlines the archive bytes instead of a URL.
     */
    private function buildDownloadUrl(int $buildId): ?string
    {
        $response = $this->fetchBuildDownloadResponse($buildId);

        if ($response->getStatusCode() === 200) {
            $payload = json_decode((string) $response->getBody(), true);
            $url = is_array($payload) ? ($payload['url'] ?? null) : null;

            return is_string($url) && $url !== '' ? $url : null;
        }

        if ($response->getStatusCode() !== 307) {
            throw new RuntimeException("Expected DenKit Stash archive redirect for build {$buildId}, got HTTP {$response->getStatusCode()}");
        }

        $location = $response->getHeaderLine('Location');

        return $location !== '' ? $location : null;
    }

    private function fetchBuildDownloadResponse(int $buildId): ResponseInterface
    {
        return $this->httpClient([
            'timeout' => 600,
            'connect_timeout' => 30,
            'allow_redirects' => false,
        ])->get($this->serverUrl() . "/builds/{$buildId}/download/archive/default", [
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey(),
                'Accept' => 'application/json',
            ],
        ]);
    }

    private function restoredArchiveFilename(string $archivePath, int $buildId): string
    {
        $metadata = $this->archiveService->readArchiveMetadata($archivePath);
        $format = is_array($metadata) ? ($metadata['original_archive']['format'] ?? null) : null;
        $originalFilename = is_array($metadata) ? ($metadata['original_archive']['filename'] ?? null) : null;

        if (! is_string($format) || $format === '' || ! is_string($originalFilename) || $originalFilename === '') {
            return "denkit-stash-{$buildId}." . $this->archiveExtension($archivePath);
        }

        $suffix = '.' . $format;
        $base = basename($originalFilename);
        if (str_ends_with(strtolower($base), strtolower($suffix))) {
            return substr($base, 0, -strlen($suffix)) . '.optimized.' . $format;
        }

        return "denkit-stash-{$buildId}.optimized.{$format}";
    }

    private function assertOptimizedArchive(string $archivePath): void
    {
        $metadata = $this->archiveService->readArchiveMetadata($archivePath);
        if (($metadata['schema'] ?? null) !== self::OPTIMIZATION_METADATA_SCHEMA) {
            throw new RuntimeException("Archive is not an optimized fvn.li archive: {$archivePath}");
        }
    }

    private function validateOptimizedArchiveMembers(string $archivePath): void
    {
        match ($this->archiveExtension($archivePath)) {
            'zip' => $this->validateZipArchiveMembers($archivePath),
            'tar', 'tar.gz', 'tgz', 'tar.bz2', 'tbz2' => $this->validateTarArchiveMembers($archivePath),
            default => throw new RuntimeException("Unsupported optimized archive format: {$this->archiveExtension($archivePath)}"),
        };
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
            throw new RuntimeException("butler binary was not found at {$binary}");
        }

        if (! File::isFile("{$clientPath}/go.mod")) {
            throw new RuntimeException("butler binary was not found at {$binary}");
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

    private function versionAlreadyPersisted(string $target, string $username, string $targetName, string $channel, string $userVersion): bool
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

    private function buildIdFromPushOutput(string $output): ?int
    {
        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $data = json_decode($line, true);
            if (! is_array($data) || ($data['type'] ?? null) !== 'result') {
                continue;
            }

            $buildId = $data['value']['buildId'] ?? null;

            return is_numeric($buildId) ? (int) $buildId : null;
        }

        return null;
    }

    private function validateZipArchiveMembers(string $archivePath): void
    {
        $zip = new ZipArchive;
        $result = $zip->open($archivePath);
        if ($result !== true) {
            throw new RuntimeException("Failed to open optimized archive: {$result}");
        }

        $totalBytes = 0;
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (! is_array($stat)) {
                    throw new RuntimeException("Failed to inspect optimized archive entry #{$index}");
                }

                $name = (string) ($stat['name'] ?? '');
                $this->assertSafeArchiveEntryPath($name);
                $this->assertAllowedZipEntryType($zip, $index, $name);

                if (! str_ends_with($name, '/')) {
                    $totalBytes += (int) ($stat['size'] ?? 0);
                    $this->assertArchiveSizeLimit($totalBytes);
                }
            }

            $this->assertArchiveEntryCount($zip->numFiles);
        } finally {
            $zip->close();
        }
    }

    private function validateTarArchiveMembers(string $archivePath): void
    {
        $names = $this->runTarList($archivePath, verbose: false);
        $details = $this->runTarList($archivePath, verbose: true);

        if (count($names) !== count($details)) {
            throw new RuntimeException('Failed to inspect optimized archive entries');
        }

        $this->assertArchiveEntryCount(count($names));

        $totalBytes = 0;
        foreach ($names as $index => $name) {
            $this->assertSafeArchiveEntryPath($name);

            $detail = $details[$index] ?? '';
            $type = $detail[0] ?? '';
            if (! in_array($type, ['-', 'd'], true)) {
                throw new RuntimeException("Optimized archive entry is not a regular file or directory: {$name}");
            }

            if ($type === '-') {
                $parts = preg_split('/\s+/', $detail, 6);
                if (! is_array($parts) || ! isset($parts[2]) || ! is_numeric($parts[2])) {
                    throw new RuntimeException("Failed to inspect optimized archive entry size: {$name}");
                }

                $totalBytes += (int) $parts[2];
                $this->assertArchiveSizeLimit($totalBytes);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function runTarList(string $archivePath, bool $verbose): array
    {
        $extension = $this->archiveExtension($archivePath);
        $args = ['tar', '--list', '--file', $archivePath];
        if ($verbose) {
            $args[] = '--verbose';
        }

        match ($extension) {
            'tar.gz', 'tgz' => $args[] = '--gzip',
            'tar.bz2', 'tbz2' => $args[] = '--bzip2',
            'tar' => null,
            default => throw new RuntimeException("Unsupported optimized archive format: {$extension}"),
        };

        $process = new Process($args);
        $process->setTimeout(600);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Failed to inspect optimized archive: ' . $process->getErrorOutput());
        }

        return array_values(array_filter(preg_split('/\R/', trim($process->getOutput())) ?: [], static fn (string $line) => $line !== ''));
    }

    private function assertSafeArchiveEntryPath(string $path): void
    {
        $path = trim($path);
        if ($path === '') {
            throw new RuntimeException('Optimized archive contains an empty entry path');
        }

        $normalized = str_replace('\\', '/', $path);
        $segments = explode('/', $normalized);
        if (
            str_starts_with($normalized, '/')
            || preg_match('/^[A-Za-z]:\//', $normalized) === 1
            || in_array('..', $segments, true)
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            throw new RuntimeException("Optimized archive contains an unsafe entry path: {$path}");
        }
    }

    private function assertAllowedZipEntryType(ZipArchive $zip, int $index, string $name): void
    {
        if (! method_exists($zip, 'getExternalAttributesIndex')) {
            return;
        }

        $opsys = 0;
        $attributes = 0;
        if (! $zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
            throw new RuntimeException("Failed to inspect optimized archive entry attributes: {$name}");
        }

        if ($opsys !== ZipArchive::OPSYS_UNIX) {
            return;
        }

        $fileType = (($attributes >> 16) & 0170000);
        if ($fileType !== 0 && ! in_array($fileType, [0040000, 0100000], true)) {
            throw new RuntimeException("Optimized archive entry is not a regular file or directory: {$name}");
        }
    }

    private function assertArchiveEntryCount(int $entryCount): void
    {
        if ($entryCount > $this->maxArchiveEntries()) {
            throw new RuntimeException("Optimized archive contains too many entries: {$entryCount}");
        }
    }

    private function assertArchiveSizeLimit(int $totalBytes): void
    {
        if ($totalBytes > $this->maxExtractedBytes()) {
            throw new RuntimeException("Optimized archive expands beyond the configured byte limit: {$totalBytes}");
        }
    }

    private function maxArchiveEntries(): int
    {
        return max(1, (int) Config::get('services.denkit_stash.max_archive_entries', self::DEFAULT_MAX_ARCHIVE_ENTRIES));
    }

    private function maxExtractedBytes(): int
    {
        return max(1, (int) Config::get('services.denkit_stash.max_extracted_bytes', self::DEFAULT_MAX_EXTRACTED_BYTES));
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
        $this->validateOptimizedArchiveMembers($archivePath);

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
