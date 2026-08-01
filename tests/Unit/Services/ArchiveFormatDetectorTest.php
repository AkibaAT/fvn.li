<?php

declare(strict_types=1);

use App\Services\ArchiveFormatDetector;
use Illuminate\Support\Facades\File;

it('detects archive formats from content when the filename is incomplete', function () {
    $archivePath = tempnam(sys_get_temp_dir(), 'archive_format_');
    expect($archivePath)->not->toBeFalse();

    $misnamedArchivePath = $archivePath . '.bz2';
    File::move($archivePath, $misnamedArchivePath);
    File::put($misnamedArchivePath, 'BZh' . str_repeat("\0", 509));

    try {
        $detector = new ArchiveFormatDetector;

        expect($detector->detect($misnamedArchivePath))->toBe('tar.bz2')
            ->and($detector->detectFromFilename($misnamedArchivePath))->toBe('tar.bz2')
            ->and($detector->filenameSuffix($misnamedArchivePath))->toBe('bz2');
    } finally {
        File::delete($misnamedArchivePath);
    }
});

it('treats a bare bzip2 suffix as a tar bzip2 archive when content detection falls back to the filename', function () {
    $archivePath = tempnam(sys_get_temp_dir(), 'archive_format_');
    expect($archivePath)->not->toBeFalse();

    $misnamedArchivePath = $archivePath . '.bz2';
    File::move($archivePath, $misnamedArchivePath);
    File::put($misnamedArchivePath, 'header unavailable to format detection');

    try {
        $detector = new ArchiveFormatDetector;

        expect($detector->detect($misnamedArchivePath))->toBe('tar.bz2')
            ->and($detector->detectFromFilename($misnamedArchivePath))->toBe('tar.bz2')
            ->and($detector->filenameSuffix($misnamedArchivePath))->toBe('bz2');
    } finally {
        File::delete($misnamedArchivePath);
    }
});
