<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Stats\NdjsonStatsPayload;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class RenpyAnalyzerDockerRunner
{
    private ?string $lastError = null;

    /**
     * Analyze an archive and place the resulting stats document at
     * $destinationPath.
     *
     * The document is moved rather than returned: for a large game it runs to
     * hundreds of megabytes, and decoding it here would cost several times that
     * in memory for a process that has no use for the contents.
     */
    public function analyze(string $archivePath, string $destinationPath): bool
    {
        $this->lastError = null;

        if (! File::exists($archivePath)) {
            throw new RuntimeException("Archive file not found: {$archivePath}");
        }

        $jobId = 'renpy-analyzer-' . bin2hex(random_bytes(8));
        $containerWorkDir = rtrim((string) config('services.renpy.analyzer_container_work_dir'), '/');
        $hostWorkDir = rtrim((string) config('services.renpy.analyzer_host_work_dir'), '/');
        $containerJobDir = "{$containerWorkDir}/{$jobId}";
        $hostJobDir = "{$hostWorkDir}/{$jobId}";
        $archiveFilename = basename($archivePath);

        File::ensureDirectoryExists($containerWorkDir, 0700);
        $this->cleanupStaleJobDirectories($containerWorkDir);

        File::makeDirectory("{$containerJobDir}/input", 0755, true);
        File::makeDirectory("{$containerJobDir}/output", 0777, true);
        File::makeDirectory("{$containerJobDir}/work", 0777, true);
        chmod("{$containerJobDir}/output", 0777);
        chmod("{$containerJobDir}/work", 0777);
        File::copy($archivePath, "{$containerJobDir}/input/{$archiveFilename}");
        File::copy(base_path('scripts/renpy-analyze-archive.php'), "{$containerJobDir}/input/renpy-analyze-archive.php");
        File::copy(resource_path('renpy/json_stats.rpy'), "{$containerJobDir}/input/json_stats.rpy");

        try {
            $process = new Process($this->buildDockerRunCommand($jobId, $hostJobDir, $archiveFilename));
            $process->setTimeout((int) config('services.renpy.analyzer_timeout', 300) + 30);
            $process->run();

            if (! $process->isSuccessful()) {
                $diagnostic = trim($process->getErrorOutput()) ?: trim($process->getOutput());
                $this->lastError = 'Analyzer container failed';
                if ($diagnostic !== '') {
                    $this->lastError .= ': ' . $this->sanitizeDiagnosticOutput($diagnostic);
                }
                Log::warning('RenPy analyzer container failed', [
                    'exit_code' => $process->getExitCode(),
                    'output' => $this->sanitizeDiagnosticOutput($process->getOutput()),
                    'error_output' => $this->sanitizeDiagnosticOutput($process->getErrorOutput()),
                ]);

                return false;
            }

            $statsPath = "{$containerJobDir}/output/stats.ndjson";
            if (! File::exists($statsPath)) {
                $this->lastError = 'Analyzer container did not produce stats.ndjson';
                Log::warning('RenPy analyzer container did not produce stats.ndjson');

                return false;
            }

            // Reads only the aggregate records at the head of the document.
            if ((new NdjsonStatsPayload($statsPath))->languages() === []) {
                $this->lastError = 'Analyzer container produced a stats document without languages';
                Log::warning('RenPy analyzer container produced a stats document without languages');

                return false;
            }

            File::ensureDirectoryExists(dirname($destinationPath));
            File::move($statsPath, $destinationPath);

            return true;
        } finally {
            File::deleteDirectory($containerJobDir);
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return array<int, string>
     */
    public function buildDockerRunCommand(string $jobId, string $hostJobDir, string $archiveFilename): array
    {
        $sdkHostPath = config('services.renpy.sdk_host_path');
        $sdkContainerPath = (string) config('services.renpy.sdk_container_path', '/opt/renpy-sdk');

        if (! is_string($sdkHostPath) || $sdkHostPath === '') {
            throw new RuntimeException('RenPy SDK host path is not configured for analyzer containers');
        }

        return [
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
            (string) config('services.renpy.analyzer_php_binary', 'php'),
            '--pids-limit',
            (string) config('services.renpy.analyzer_pids_limit', 128),
            '--cpus',
            (string) config('services.renpy.analyzer_cpus', '1'),
            '--memory',
            (string) config('services.renpy.analyzer_memory', '1g'),
            '--memory-swap',
            (string) config('services.renpy.analyzer_memory', '1g'),
            '--tmpfs',
            '/tmp:rw,nosuid,nodev,noexec,mode=1777,size=' . (string) config('services.renpy.analyzer_tmp_size', '256m'),
            '--env',
            'RENPY_ANALYZER_ALLOW_NATIVE=1',
            '--env',
            'RENPY_ANALYZER_WORK_DIR=/work',
            '--mount',
            // Extracting a game expands to gigabytes. A tmpfs here is charged to
            // the container's memory limit, so the staging area is disk-backed.
            "type=bind,source={$hostJobDir}/work,target=/work",
            '--mount',
            "type=bind,source={$hostJobDir}/input,target=/input,readonly",
            '--mount',
            "type=bind,source={$hostJobDir}/output,target=/output",
            '--mount',
            "type=bind,source={$sdkHostPath},target={$sdkContainerPath},readonly",
            (string) config('services.renpy.analyzer_image'),
            '/input/renpy-analyze-archive.php',
            "/input/{$archiveFilename}",
            '/output/stats.ndjson',
            $sdkContainerPath,
            '/input/json_stats.rpy',
        ];
    }

    private function sanitizeDiagnosticOutput(string $output): string
    {
        $output = preg_replace('/[^\P{C}\t\r\n]/u', '', str_replace("\0", '', $output));
        if (! is_string($output)) {
            $output = '';
        }

        $limit = 4096;

        if (strlen($output) <= $limit) {
            return $output;
        }

        return substr($output, 0, $limit) . "\n[truncated]";
    }

    private function cleanupStaleJobDirectories(string $workDir): void
    {
        $maxAge = (int) config('services.renpy.analyzer_stale_cleanup_seconds', 7200);
        $expiresBefore = time() - max(60, $maxAge);

        foreach (File::directories($workDir) as $directory) {
            if (! str_starts_with(basename($directory), 'renpy-analyzer-')) {
                continue;
            }

            $modifiedAt = @filemtime($directory);
            if ($modifiedAt !== false && $modifiedAt < $expiresBefore) {
                File::deleteDirectory($directory);
            }
        }
    }
}
