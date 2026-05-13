<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class ButlerServerPersistenceService
{
    private const OPTIMIZATION_METADATA_SCHEMA = 'fvn.archive_optimization.v1';

    public function __construct(
        private readonly GameArchiveService $archiveService
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

        $apiKey = $this->ensureApiUser($username);
        if (! $force && $this->versionAlreadyPersisted($username, $targetName, $channel, (string) $version->version)) {
            return [
                'status' => 'skipped',
                'target' => $target,
                'channel' => $channel,
            ];
        }

        $workDir = storage_path('app/temp/'.uniqid('butler_push_', true));
        try {
            File::makeDirectory($workDir, 0755, true);
            $this->extractArchive($archivePath, $workDir);

            $process = new Process([
                $this->butlerBinary(),
                '--address='.$this->serverUrl(),
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

    private function assertOptimizedArchive(string $archivePath): void
    {
        $metadata = $this->archiveService->readArchiveMetadata($archivePath);
        if (($metadata['schema'] ?? null) !== self::OPTIMIZATION_METADATA_SCHEMA) {
            throw new RuntimeException("Archive is not an optimized fvn.li archive: {$archivePath}");
        }
    }

    private function username(): string
    {
        return (string) Config::get('services.butler_server.username', 'fvn-li');
    }

    private function serverUrl(): string
    {
        return rtrim((string) Config::get('services.butler_server.url', 'http://butler-server:8081'), '/');
    }

    private function targetName(Game $game): string
    {
        $slug = (string) ($game->slug ?: Str::slug((string) $game->name));

        return $slug !== '' ? $slug : 'game-'.$game->id;
    }

    private function butlerBinary(): string
    {
        $clientPath = rtrim((string) Config::get('services.butler_server.client_path', '/var/www/butler-client'), '/');
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

    private function ensureApiUser(string $username): string
    {
        $connection = $this->butlerConnection();
        $user = $connection->table('users')->where('username', $username)->first();
        if ($user !== null) {
            if (! (bool) $user->is_active) {
                $connection->table('users')->where('id', $user->id)->update([
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            }

            return (string) $user->api_key;
        }

        $apiKey = 'fvn_'.Str::random(48);
        $connection->table('users')->insert([
            'username' => $username,
            'display_name' => $username,
            'api_key' => $apiKey,
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $apiKey;
    }

    private function versionAlreadyPersisted(string $username, string $targetName, string $channel, string $userVersion): bool
    {
        return $this->latestBuildId($username, $targetName, $channel, $userVersion) !== null;
    }

    private function latestBuildId(string $username, string $targetName, string $channel, string $userVersion): ?int
    {
        $id = $this->butlerConnection()
            ->table('builds')
            ->join('uploads', 'uploads.id', '=', 'builds.upload_id')
            ->join('games', 'games.id', '=', 'uploads.game_id')
            ->join('users', 'users.id', '=', 'games.user_id')
            ->where('users.username', $username)
            ->where('games.title', $targetName)
            ->where('builds.channel_name', $channel)
            ->where('builds.user_version', $userVersion)
            ->where('builds.state', 'completed')
            ->orderBy('builds.id', 'desc')
            ->value('builds.id');

        return $id === null ? null : (int) $id;
    }

    private function butlerConnection(): ConnectionInterface
    {
        $name = 'butler_server';
        if (! Config::has("database.connections.{$name}")) {
            $base = Config::get('database.connections.pgsql');
            $base['host'] = Config::get('services.butler_server.postgres.host', 'db');
            $base['port'] = Config::get('services.butler_server.postgres.port', 5432);
            $base['database'] = Config::get('services.butler_server.postgres.database', 'butler');
            $base['username'] = Config::get('services.butler_server.postgres.username', 'db');
            $base['password'] = Config::get('services.butler_server.postgres.password', 'db');
            Config::set("database.connections.{$name}", $base);
        }

        return DB::connection($name);
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
            throw new RuntimeException('Failed to extract optimized archive: '.$process->getErrorOutput());
        }
    }

    private function pushDirectory(string $extractPath): string
    {
        if (File::isDirectory($extractPath.'/game')) {
            return $extractPath;
        }

        $directories = File::directories($extractPath);
        if (count($directories) === 1 && File::isDirectory($directories[0].'/game')) {
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
