<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class GameArchiveOptimizerDockerRunner
{
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
        chmod("{$containerJobDir}/output", 0777);
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
                $diagnostic = trim($process->getErrorOutput()) ?: trim($process->getOutput());
                $message = 'Archive optimizer container failed';
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
            '--tmpfs',
            '/work:rw,nosuid,nodev,noexec,mode=1777,size=' . (string) config('services.archive_optimizer.work_size', '6g'),
            '--env',
            'ARCHIVE_OPTIMIZER_APP_PATH=' . (string) config('services.archive_optimizer.app_path', '/app'),
            '--env',
            'ARCHIVE_OPTIMIZER_WORK_DIR=/work',
            '--env',
            'LOG_CHANNEL=stderr',
            '--mount',
            "type=bind,source={$hostJobDir}/input,target=/input,readonly",
            '--mount',
            "type=bind,source={$hostJobDir}/output,target=/output",
        ];

        // The image does not contain the application code; it is bind-mounted at runtime.
        $hostAppDir = rtrim((string) config('services.archive_optimizer.host_app_dir', ''), '/');
        if ($hostAppDir !== '') {
            $command[] = '--mount';
            $command[] = "type=bind,source={$hostAppDir},target=" . (string) config('services.archive_optimizer.app_path', '/app') . ',readonly';
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
