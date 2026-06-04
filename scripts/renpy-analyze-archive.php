#!/usr/bin/env php
<?php

declare(strict_types=1);

[$script, $archivePath, $outputPath, $sdkPath, $jsonStatsPath] = array_pad($argv, 5, null);

if (! is_string($archivePath) || ! is_file($archivePath)) {
    fwrite(STDERR, "Archive file is missing\n");
    exit(2);
}

if (! is_string($outputPath) || $outputPath === '') {
    fwrite(STDERR, "Output path is missing\n");
    exit(2);
}

if (! is_string($sdkPath) || ! is_file($sdkPath.'/renpy.sh')) {
    fwrite(STDERR, "Ren'Py SDK is missing\n");
    exit(2);
}

if (! is_string($jsonStatsPath) || $jsonStatsPath === '') {
    $jsonStatsPath = '/app/resources/renpy/json_stats.rpy';
}

if (! is_file($jsonStatsPath)) {
    fwrite(STDERR, "json_stats.rpy is missing\n");
    exit(2);
}

$workPath = rtrim(getenv('RENPY_ANALYZER_WORK_DIR') ?: '/work', '/');
$extractPath = $workPath.'/extract';
mkdir($extractPath, 0777, true);

try {
    extractArchive($archivePath, $extractPath);
    rejectSymlinks($extractPath);

    $gameDir = findGameDirectory($extractPath);
    if ($gameDir === null) {
        fwrite(STDERR, "No Ren'Py game directory found\n");
        exit(3);
    }

    if (! copy($jsonStatsPath, $gameDir.'/game/json_stats.rpy')) {
        throw new RuntimeException('Failed to copy json_stats.rpy');
    }

    $renpyHome = $workPath.'/home';
    $renpyTokens = $renpyHome.'/.renpy/tokens';
    if (! is_dir($renpyTokens)) {
        mkdir($renpyTokens, 0777, true);
    }

    $diagnostics = [];
    $statsPath = $gameDir.'/stats.json';

    $result = runProcess([$sdkPath.'/renpy.sh', 'game', 'test'], $gameDir, 300, [
        'HOME' => $renpyHome,
        'RENPY_PATH_TO_SAVES' => $renpyHome.'/.renpy',
    ]);
    if ($result['exit_code'] !== 0) {
        $diagnostics[] = "SDK analysis failed:\n".$result['stderr'].$result['stdout'];
    }

    if (copyValidStats($statsPath, $outputPath)) {
        exit(0);
    }

    $linuxExecutable = findLinuxExecutable($gameDir);
    if ($linuxExecutable !== null) {
        $nativeTest = runNativeStatsCommand($gameDir, $linuxExecutable, nativeCommand($linuxExecutable, ['game', 'test']), 'native test', [
            'HOME' => $renpyHome,
            'RENPY_PATH_TO_SAVES' => $renpyHome.'/.renpy',
        ]);
        if (copyValidStats($statsPath, $outputPath)) {
            exit(0);
        }
        if ($nativeTest !== '') {
            $diagnostics[] = $nativeTest;
        }

        if (! hasTranslationTree($gameDir)) {
            $nativeLauncher = runNativeStatsCommand($gameDir, $linuxExecutable, nativeCommand($linuxExecutable), 'native launcher', [
                'HOME' => $renpyHome,
                'RENPY_PATH_TO_SAVES' => $renpyHome.'/.renpy',
            ]);
            if (copyValidStats($statsPath, $outputPath)) {
                exit(0);
            }
            if ($nativeLauncher !== '') {
                $diagnostics[] = $nativeLauncher;
            }
        }
    }

    foreach ($diagnostics as $diagnostic) {
        writeDiagnosticOutput($diagnostic);
    }

    if (! is_file($statsPath)) {
        fwrite(STDERR, "Stats file not generated\n");
        exit(4);
    }

    $statsContent = (string) file_get_contents($statsPath);
    $stats = json_decode($statsContent, true);
    if (! is_array($stats) || ! isset($stats['languages']) || ! is_array($stats['languages'])) {
        fwrite(STDERR, "Invalid stats file format: ".invalidStatsDiagnostic($statsContent, $stats)."\n");
        exit(5);
    }

    copy($statsPath, $outputPath);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}

function writeDiagnosticOutput(string $output): void
{
    $output = str_replace("\0", '', $output);
    $limit = 4096;

    if (strlen($output) > $limit) {
        $output = substr($output, 0, $limit)."\n[truncated]\n";
    }

    fwrite(STDERR, $output);
}

function invalidStatsDiagnostic(string $statsContent, mixed $decoded): string
{
    if (! is_array($decoded)) {
        return 'JSON decode failed or root value was not an object; preview='.substr(str_replace("\0", '', $statsContent), 0, 500);
    }

    return 'top-level keys='.implode(',', array_slice(array_map('strval', array_keys($decoded)), 0, 20));
}

function copyValidStats(string $statsPath, string $outputPath): bool
{
    if (! is_file($statsPath)) {
        return false;
    }

    $statsContent = (string) file_get_contents($statsPath);
    $stats = json_decode($statsContent, true);
    if (! is_array($stats) || ! isset($stats['languages']) || ! is_array($stats['languages'])) {
        return false;
    }

    copy($statsPath, $outputPath);

    return true;
}

function runNativeStatsCommand(string $gameDir, string $executablePath, array $command, string $mode, array $env): string
{
    $statsPath = $gameDir.'/stats.json';
    if (is_file($statsPath)) {
        unlink($statsPath);
    }

    $result = runProcess($command, dirname($executablePath), 300, $env);
    if (is_file($statsPath)) {
        return '';
    }

    $status = $result['exit_code'] === 0
        ? 'produced no stats'
        : 'failed';

    return "{$mode} {$status}:\n".$result['stderr'].$result['stdout'];
}

/**
 * @return array<int, string>
 */
function nativeCommand(string $executablePath, array $arguments = []): array
{
    if (preg_match('/\.sh$/i', basename($executablePath))) {
        return array_merge(['sh', $executablePath], $arguments);
    }

    return array_merge([$executablePath], $arguments);
}

function findLinuxExecutable(string $gameDir): ?string
{
    makeExecutables($gameDir);
    $executableFiles = [];

    foreach (findAllFiles($gameDir) as $file) {
        if (preg_match('/\.sh$/i', basename($file))) {
            return $file;
        }

        if (is_file($file) && is_executable($file)) {
            $executableFiles[] = $file;
        }
    }

    if ($executableFiles === []) {
        return null;
    }

    return $executableFiles[0];
}

function makeExecutables(string $dir): void
{
    foreach (glob($dir.'/*') ?: [] as $file) {
        if (is_file($file)) {
            chmod($file, 0755);
        }
    }

    $lib = $dir.DIRECTORY_SEPARATOR.'lib';
    if (! is_dir($lib)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($lib, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            chmod($file->getPathname(), 0755);
        }
    }
}

/**
 * @return array<int, string>
 */
function findAllFiles(string $dir): array
{
    $files = [];

    foreach (glob($dir.'/*') ?: [] as $path) {
        if (is_file($path)) {
            $files[] = $path;
        }
    }

    foreach (glob($dir.'/*', GLOB_ONLYDIR) ?: [] as $subdir) {
        if (basename($subdir) === 'game') {
            continue;
        }

        $files = array_merge($files, findAllFiles($subdir));
    }

    return $files;
}

function hasTranslationTree(string $gameDir): bool
{
    $translationPath = $gameDir.'/game/tl';
    if (! is_dir($translationPath)) {
        return false;
    }

    foreach (glob($translationPath.'/*', GLOB_ONLYDIR) ?: [] as $languageDir) {
        $language = basename($languageDir);
        if ($language !== 'None' && $language !== 'common') {
            return true;
        }
    }

    return false;
}

/**
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runProcess(array $command, string $cwd, int $timeout, array $env = []): array
{
    $process = proc_open($command, [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $cwd, $env === [] ? null : array_replace($_ENV, $env));

    if (! is_resource($process)) {
        throw new RuntimeException('Failed to start process');
    }

    $startedAt = time();
    foreach ($pipes as $pipe) {
        stream_set_blocking($pipe, false);
    }

    $stdout = '';
    $stderr = '';
    do {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        $status = proc_get_status($process);

        if ($status['running'] && time() - $startedAt > $timeout) {
            proc_terminate($process, 9);
            throw new RuntimeException('Ren\'Py analysis timed out');
        }

        usleep(100000);
    } while ($status['running']);

    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);

    $exitCode = is_int($status['exitcode'] ?? null) ? $status['exitcode'] : null;

    foreach ($pipes as $pipe) {
        fclose($pipe);
    }

    $closedExitCode = proc_close($process);
    $exitCode ??= $closedExitCode;

    return [
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

function extractArchive(string $archivePath, string $extractPath): void
{
    $format = detectArchiveFormat($archivePath);

    if ($format === 'zip') {
        extractZip($archivePath, $extractPath);

        return;
    }

    if (in_array($format, ['tar', 'tar.gz', 'tar.bz2'], true)) {
        extractTar($archivePath, $extractPath, $format);

        return;
    }

    throw new RuntimeException("Unsupported archive format: {$format}");
}

function extractZip(string $archivePath, string $extractPath): void
{
    $zip = new ZipArchive;
    $result = $zip->open($archivePath);
    if ($result !== true) {
        throw new RuntimeException("Failed to open zip archive: {$result}");
    }

    try {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name)) {
                continue;
            }

            assertSafeArchiveEntry($name);

            $targetPath = $extractPath.'/'.$name;
            if (str_ends_with($name, '/')) {
                mkdir($targetPath, 0777, true);

                continue;
            }

            $targetDir = dirname($targetPath);
            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $source = $zip->getStream($name);
            if ($source === false) {
                throw new RuntimeException("Failed to read zip entry: {$name}");
            }

            $target = fopen($targetPath, 'wb');
            if ($target === false) {
                fclose($source);
                throw new RuntimeException("Failed to write zip entry: {$name}");
            }

            stream_copy_to_stream($source, $target);
            fclose($source);
            fclose($target);
        }
    } finally {
        $zip->close();
    }
}

function extractTar(string $archivePath, string $extractPath, string $format): void
{
    $listCommand = ['tar', '-tf', $archivePath];
    $list = runProcess($listCommand, '/', 120);
    if ($list['exit_code'] !== 0) {
        throw new RuntimeException('Failed to list tar archive: '.$list['stderr']);
    }

    foreach (preg_split('/\r?\n/', trim($list['stdout'])) ?: [] as $entry) {
        if ($entry === '') {
            continue;
        }

        assertSafeArchiveEntry($entry);
    }

    $extractFlag = match ($format) {
        'tar.gz' => '-xzf',
        'tar.bz2' => '-xjf',
        default => '-xf',
    };

    $extract = runProcess([
        'tar',
        $extractFlag,
        $archivePath,
        '-C',
        $extractPath,
        '--no-same-owner',
        '--no-same-permissions',
    ], '/', 300);

    if ($extract['exit_code'] !== 0) {
        throw new RuntimeException('Failed to extract tar archive: '.$extract['stderr']);
    }
}

function assertSafeArchiveEntry(string $entry): void
{
    $entry = str_replace('\\', '/', $entry);
    if (
        $entry === ''
        || str_starts_with($entry, '/')
        || str_contains($entry, "\0")
        || str_contains($entry, '/../')
        || str_starts_with($entry, '../')
        || $entry === '..'
    ) {
        throw new RuntimeException("Unsafe archive entry: {$entry}");
    }
}

function rejectSymlinks(string $path): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isLink()) {
            throw new RuntimeException('Archive contains symlink: '.$item->getPathname());
        }
    }
}

function detectArchiveFormat(string $archivePath): string
{
    $header = (string) file_get_contents($archivePath, false, null, 0, 512);

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

    return strtolower(pathinfo($archivePath, PATHINFO_EXTENSION));
}

function findGameDirectory(string $basePath): ?string
{
    if (is_dir($basePath.'/game')) {
        return $basePath;
    }

    foreach (glob($basePath.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
        if (is_dir($directory.'/game')) {
            return $directory;
        }
    }

    return null;
}
