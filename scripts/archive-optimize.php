#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Services\GameArchiveOptimizationService;
use Illuminate\Contracts\Console\Kernel;

[$script, $archivePath, $outputPath, $resultPath, $previousOptimizedArchivePath] = array_pad($argv, 5, null);

if (! is_string($archivePath) || ! is_file($archivePath)) {
    fwrite(STDERR, "Archive file is missing\n");
    exit(2);
}

if (! is_string($outputPath) || $outputPath === '') {
    fwrite(STDERR, "Output path is missing\n");
    exit(2);
}

if (! is_string($resultPath) || $resultPath === '') {
    fwrite(STDERR, "Result path is missing\n");
    exit(2);
}

if (! is_string($previousOptimizedArchivePath) || ! is_file($previousOptimizedArchivePath)) {
    $previousOptimizedArchivePath = null;
}

$basePath = rtrim(getenv('ARCHIVE_OPTIMIZER_APP_PATH') ?: '/app', '/');
if (! is_file($basePath . '/vendor/autoload.php') || ! is_file($basePath . '/bootstrap/app.php')) {
    fwrite(STDERR, "Application path is invalid\n");
    exit(2);
}

try {
    chdir($basePath);
    require $basePath . '/vendor/autoload.php';
    $app = require $basePath . '/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $result = $app->make(GameArchiveOptimizationService::class)
        ->optimizeArchiveFile($archivePath, $previousOptimizedArchivePath);

    if (($result['status'] ?? null) === 'optimized') {
        $optimizedPath = $result['optimized_path'] ?? null;
        if (! is_string($optimizedPath) || ! is_file($optimizedPath)) {
            throw new RuntimeException('Optimizer did not produce an optimized archive');
        }

        if (! copy($optimizedPath, $outputPath)) {
            throw new RuntimeException('Failed to copy optimized archive to output path');
        }

        $result['optimized_path'] = $outputPath;
    }

    file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
