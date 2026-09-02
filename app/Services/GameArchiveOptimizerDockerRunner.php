<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class GameArchiveOptimizerDockerRunner
{
    public function __construct(
        private readonly ArchiveFormatDetector $archiveFormatDetector = new ArchiveFormatDetector
    ) {}

    /**
     * @return array{
     *     status: string,
     *     reason?: string,
     *     original_path?: string,
     *     optimized_path?: string,
     *     original_size?: int,
     *     optimized_size?: int,
     *     saved_bytes?: int,
     *     rpa_files?: int,
     *     rpyc_files?: int,
     *     images_optimized?: int,
     *     audio_optimized?: int,
     *     images_reused?: int,
     *     audio_reused?: int,
     *     references_updated?: int,
     *     rpyc_decompile_failed?: int
     * }
     */
    public function optimize(string $archivePath, ?string $previousOptimizedArchivePath = null): array
    {
        if (! File::exists($archivePath)) {
            throw new RuntimeException("Archive file not found: {$archivePath}");
        }

        $jobId = 'archive-optimizer-' . bin2hex(random_bytes(8));
        $containerWorkDir = rtrim((string) config('services.archive_optimizer.container_work_dir'), '/');
        $hostWorkDir = rtrim((string) config('services.archive_optimizer.host_work_dir'), '/');
        $containerJobDir = "{$containerWorkDir}/{$jobId}";
        $hostJobDir = "{$hostWorkDir}/{$jobId}";
        $archiveFilename = basename($archivePath);
        $previousFilename = $previousOptimizedArchivePath === null ? null : basename($previousOptimizedArchivePath);
        $extension = $this->archiveExtension($archivePath);
        $optimizedFilename = "optimized.{$extension}";

        File::ensureDirectoryExists($containerWorkDir, 0700);
        $this->cleanupStaleJobDirectories($containerWorkDir);

        File::makeDirectory("{$containerJobDir}/input", 0755, true);
        File::makeDirectory("{$containerJobDir}/output", 0777, true);
        File::makeDirectory("{$containerJobDir}/work", 0777, true);
        chmod("{$containerJobDir}/output", 0777);
        chmod("{$containerJobDir}/work", 0777);
        File::copy($archivePath, "{$containerJobDir}/input/{$archiveFilename}");
        File::copy(base_path('scripts/archive-optimize.php'), "{$containerJobDir}/input/archive-optimize.php");
        if ($previousOptimizedArchivePath !== null && File::exists($previousOptimizedArchivePath)) {
            File::copy($previousOptimizedArchivePath, "{$containerJobDir}/input/{$previousFilename}");
        }

        $keepJobDirForOptimizedArchive = false;
        try {
            $process = new Process($this->buildDockerRunCommand($jobId, $hostJobDir, $archiveFilename, $optimizedFilename, $previousFilename));
            $process->setTimeout((int) config('services.archive_optimizer.timeout', 900) + 30);
            $process->run();

            if (! $process->isSuccessful()) {
                $exitCode = $process->getExitCode();
                $diagnostic = trim($process->getErrorOutput()) ?: trim($process->getOutput());

                $message = "Archive optimizer container failed (exit {$exitCode})";
                if ($exitCode === 137) {
                    $message .= ': out of memory, container limit is '
                        . (string) config('services.archive_optimizer.memory');
                }

                // A killed container reports nothing of its own, so whatever it
                // logged before dying is the only record of how far it got.
                if ($diagnostic !== '') {
                    $message .= ': ' . $this->sanitizeDiagnosticOutput($diagnostic);
                }

                throw new RuntimeException($message);
            }

            $resultPath = "{$containerJobDir}/output/result.json";
            if (! File::exists($resultPath)) {
                throw new RuntimeException('Archive optimizer container did not produce result.json');
            }

            $result = json_decode(File::get($resultPath), true);
            if (! is_array($result) || ! isset($result['status']) || ! is_string($result['status'])) {
                throw new RuntimeException('Archive optimizer container produced invalid result.json');
            }

            if ($result['status'] === 'optimized') {
                $optimizedPath = "{$containerJobDir}/output/{$optimizedFilename}";
                if (! File::exists($optimizedPath)) {
                    throw new RuntimeException('Archive optimizer container did not produce optimized archive');
                }

                $result['optimized_path'] = $optimizedPath;
                $keepJobDirForOptimizedArchive = true;
            }

            return $result;
        } finally {
            if ($keepJobDirForOptimizedArchive) {
                File::deleteDirectory("{$containerJobDir}/input");
                File::deleteDirectory("{$containerJobDir}/work");
            } else {
                File::deleteDirectory($containerJobDir);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public function buildDockerRunCommand(
        string $jobId,
        string $hostJobDir,
        string $archiveFilename,
        string $optimizedFilename,
        ?string $previousFilename = null
    ): array {
        $command = [
            'docker',
            'run',
            '--rm',
            '--name',
            $jobId,
            '--network',
            'none',
            '--read-only',
            '--user',
            '33:33',
            '--cap-drop',
            'ALL',
            '--security-opt',
            'no-new-privileges',
            '--entrypoint',
            (string) config('services.archive_optimizer.php_binary', 'php'),
            '--pids-limit',
            (string) config('services.archive_optimizer.pids_limit', 128),
            '--cpus',
            (string) config('services.archive_optimizer.cpus', '1'),
            '--memory',
            (string) config('services.archive_optimizer.memory', '2g'),
            '--memory-swap',
            (string) config('services.archive_optimizer.memory', '2g'),
            '--tmpfs',
            '/tmp:rw,nosuid,nodev,noexec,mode=1777,size=' . (string) config('services.archive_optimizer.tmp_size', '512m'),
            '--env',
            'ARCHIVE_OPTIMIZER_APP_PATH=' . (string) config('services.archive_optimizer.app_path', '/app'),
            '--env',
            'ARCHIVE_OPTIMIZER_WORK_DIR=/work',
            '--env',
            'CACHE_DRIVER=array',
            '--env',
            'LOG_CHANNEL=stderr',
            '--env',
            // The application directory is mounted read-only and carries no
            // storage tree, so Blade compiles into the container's own tmpfs.
            'VIEW_COMPILED_PATH=/tmp/views',
            '--mount',
            // Extracting a game expands to gigabytes. A tmpfs here is charged to
            // the container's memory limit, so the staging area is disk-backed.
            "type=bind,source={$hostJobDir}/work,target=/work",
            '--mount',
            "type=bind,source={$hostJobDir}/input,target=/input,readonly",
            '--mount',
            "type=bind,source={$hostJobDir}/output,target=/output",
        ];

        // Mount only source and dependencies needed to bootstrap Laravel. The
        // deployment root also contains .env and runtime data, so it must never
        // cross the untrusted-archive sandbox boundary as one broad mount.
        $hostAppDir = rtrim((string) config('services.archive_optimizer.host_app_dir', ''), '/');
        if ($hostAppDir !== '') {
            $appPath = rtrim((string) config('services.archive_optimizer.app_path', '/app'), '/');
            foreach (['app', 'bootstrap', 'config', 'resources', 'routes', 'vendor'] as $path) {
                $command[] = '--mount';
                $command[] = "type=bind,source={$hostAppDir}/{$path},target={$appPath}/{$path},readonly";
            }
            $command[] = '--tmpfs';
            $command[] = "{$appPath}/bootstrap/cache:rw,nosuid,nodev,noexec,mode=0770,size=16m";
        }

        // The compiled scripts are regenerated with the game's own runtime, and
        // the SDK stands in for titles whose runtime cannot run here.
        $sdkHostPath = config('services.renpy.sdk_host_path');
        if (is_string($sdkHostPath) && $sdkHostPath !== '') {
            $command[] = '--mount';
            $command[] = 'type=bind,source=' . $sdkHostPath
                . ',target=' . (string) config('services.renpy.sdk_container_path', '/opt/renpy-sdk')
                . ',readonly';
        }

        $command[] = (string) config('services.archive_optimizer.image');
        $command[] = '/input/archive-optimize.php';
        $command[] = "/input/{$archiveFilename}";
        $command[] = "/output/{$optimizedFilename}";
        $command[] = '/output/result.json';

        if ($previousFilename !== null) {
            $command[] = "/input/{$previousFilename}";
        }

        return $command;
    }

    private function cleanupStaleJobDirectories(string $workDir): void
    {
        $maxAge = (int) config('services.archive_optimizer.stale_cleanup_seconds', 7200);
        $expiresBefore = time() - max(60, $maxAge);

        foreach (File::directories($workDir) as $directory) {
            if (! str_starts_with(basename($directory), 'archive-optimizer-')) {
                continue;
            }

            $modifiedAt = @filemtime($directory);
            if ($modifiedAt !== false && $modifiedAt < $expiresBefore) {
                File::deleteDirectory($directory);
            }
        }
    }

    private function sanitizeDiagnosticOutput(string $output): string
    {
        $output = str_replace("\0", '', $output);
        $limit = 4096;

        if (strlen($output) <= $limit) {
            return $output;
        }

        return substr($output, 0, $limit) . "\n[truncated]";
    }

    private function archiveExtension(string $archivePath): string
    {
        return $this->archiveFormatDetector->detect($archivePath);
    }
}
