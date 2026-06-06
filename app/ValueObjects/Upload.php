<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Models\Game;
use DateTime;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Upload
{
    private const int TIMESTAMP_TIE_WINDOW_DAYS = 3;

    private const array PROCESSABLE_EXTENSIONS = [
        'zip',
        'gz',
        'bz2',
        'tar',
    ];

    public DateTimeInterface $updatedAt;

    public ?DateTimeInterface $buildUpdatedAt;

    private ?string $extractedVersion = null;

    public function __construct(
        public int $id,
        public string $filename,
        public ?string $displayName,
        public ?string $md5Hash,
        string $updatedAt,
        public ?int $buildId,
        ?string $buildUpdatedAt = null,
        public ?string $userVersion = null,
        public array $traits = [],
        public string $type = ''
    ) {
        $this->updatedAt = new DateTime($updatedAt);
        $this->buildUpdatedAt = $buildUpdatedAt ? new DateTime($buildUpdatedAt) : null;
    }

    public static function fromCollection(Collection $uploads): Collection
    {
        return $uploads->map(fn ($data, $id) => self::fromArray($data, $id));
    }

    public static function fromArray(array $data, int $id): self
    {
        $data['build_id'] = $data['build_id'] ?? null;

        return new self(
            id: $id,
            filename: $data['filename'] ?? '',
            displayName: $data['display_name'] ?? null,
            md5Hash: $data['md5_hash'] ?? null,
            updatedAt: $data['updated_at'],
            buildId: $data['build_id'] ? intval($data['build_id']) : null,
            buildUpdatedAt: $data['build_updated_at'] ?? null,
            userVersion: $data['user_version'] ?? null,
            traits: $data['traits'] ?? [],
            type: $data['type'] ?? ''
        );
    }

    public static function getBest(Collection $uploads): ?self
    {
        return self::sort($uploads)->first();
    }

    public static function sort(Collection $uploads): Collection
    {
        // Filter out non-processable uploads first, then sort the remainder
        return $uploads->filter(fn (self $upload) => $upload->isProcessable())
            ->sort(fn (self $a, self $b) => $a->compareTo($b));
    }

    public function isProcessable(): bool
    {
        // Web versions are never processable
        if ($this->isWeb() || $this->type === 'book') {
            return false;
        }

        if (! $this->isArchive()) {
            return false;
        }

        return $this->statsExtractionPriority() !== null;
    }

    public function isDemo(): bool
    {
        if (in_array('demo', $this->traits, true)) {
            return true;
        }

        $names = array_filter([$this->filename, $this->displayName]);

        return array_any($names, fn ($name) => preg_match('/(?:^|[^a-z0-9])(?:demo|free[-_\s]?version)(?:[^a-z0-9]|$)/i', $name) === 1);
    }

    public function isWeb(): bool
    {
        return $this->type === 'html';
    }

    public function compareTo(self $other): int
    {
        $criteria = [
            fn ($a, $b) => $a->statsExtractionPriority() <=> $b->statsExtractionPriority(),
            // itch.io upload timestamps are the most reliable signal for the
            // currently available downloadable file. Some games use non-semver
            // version schemes where PHP's version_compare() can rank an older
            // upload higher than a newer one. Treat uploads within a few days as
            // the same release batch so platform preference can choose Linux.
            fn ($a, $b) => $a->compareRelaxedTimestamps($b),
            fn ($a, $b) => $this->compareVersions(
                $b->getVersion(),
                $a->getVersion()
            ),
            fn ($a, $b) => $b->isLinux() <=> $a->isLinux(),
            fn ($a, $b) => $b->hasLinuxFileName() <=> $a->hasLinuxFileName(),
            fn ($a, $b) => $b->isWindows() <=> $a->isWindows(),
            fn ($a, $b) => $b->hasPcFileName() <=> $a->hasPcFileName(),
            fn ($a, $b) => $b->isZip() <=> $a->isZip(),
            fn ($a, $b) => ($b->buildUpdatedAt ?? $b->updatedAt) <=> ($a->buildUpdatedAt ?? $a->updatedAt),
            fn ($a, $b) => $b->updatedAt <=> $a->updatedAt,
        ];

        foreach ($criteria as $criterion) {
            $result = $criterion($this, $other);
            if ($result !== 0) {
                return $result;
            }
        }

        return 0;
    }

    public function isLinux(): bool
    {
        return in_array('p_linux', $this->traits);
    }

    public function isWindows(): bool
    {
        return in_array('p_windows', $this->traits);
    }

    public function hasLinuxFileName(): bool
    {
        $patterns = ['/linux/i'];
        $names = array_filter([$this->filename, $this->displayName]);

        return array_any($patterns, fn ($pattern) => array_any($names, fn ($name) => preg_match($pattern, $name)));
    }

    public function hasPcFileName(): bool
    {
        $patterns = ['/pc/i'];
        $names = array_filter([$this->filename, $this->displayName]);

        return array_any($patterns, fn ($pattern) => array_any($names, fn ($name) => preg_match($pattern, $name)));
    }

    public function isZip(): bool
    {
        return strtolower(pathinfo($this->filename, PATHINFO_EXTENSION)) === 'zip';
    }

    private function isArchive(): bool
    {
        $ext = strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));

        if (in_array($ext, self::PROCESSABLE_EXTENSIONS, true)) {
            return true;
        }

        // Special handling for tar.gz and tar.bz2
        if ($ext === 'gz' || $ext === 'bz2') {
            $basename = basename($this->filename, ".{$ext}");

            return strtolower(pathinfo($basename, PATHINFO_EXTENSION)) === 'tar';
        }

        return false;
    }

    private function statsExtractionPriority(): ?int
    {
        if ($this->isAndroid() || $this->hasAndroidFileName()) {
            return null;
        }

        if ($this->isLinux() || $this->hasLinuxFileName()) {
            return 0;
        }

        if ($this->isWindows() || $this->hasPcFileName()) {
            return 1;
        }

        if ($this->hasAnyPlatformTrait() || $this->hasMacFileName()) {
            return null;
        }

        return $this->isZip() ? 2 : null;
    }

    private function hasAnyPlatformTrait(): bool
    {
        return array_any($this->traits, fn ($trait) => str_starts_with((string) $trait, 'p_'));
    }

    private function hasAndroidFileName(): bool
    {
        return $this->matchesNamePattern('/(?:^|[^a-z0-9])android(?:[^a-z0-9]|$)/i');
    }

    private function hasMacFileName(): bool
    {
        return $this->matchesNamePattern('/(?:^|[^a-z0-9])(?:mac|macos|osx)(?:[^a-z0-9]|$)/i');
    }

    private function matchesNamePattern(string $pattern): bool
    {
        $names = array_filter([$this->filename, $this->displayName]);

        return array_any($names, fn ($name) => preg_match($pattern, $name) === 1);
    }

    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'display_name' => $this->displayName,
            'md5_hash' => $this->md5Hash,
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'build_id' => $this->buildId,
            'build_updated_at' => $this->buildUpdatedAt?->format('Y-m-d H:i:s'),
            'user_version' => $this->userVersion,
            'traits' => $this->traits,
            'type' => $this->type,
        ];
    }

    public function isMac(): bool
    {
        return in_array('p_osx', $this->traits);
    }

    public function isAndroid(): bool
    {
        return in_array('p_android', $this->traits);
    }

    private function compareRelaxedTimestamps(self $other): int
    {
        $timestamp = $this->buildUpdatedAt ?? $this->updatedAt;
        $otherTimestamp = $other->buildUpdatedAt ?? $other->updatedAt;
        $diffSeconds = abs($timestamp->getTimestamp() - $otherTimestamp->getTimestamp());

        if ($diffSeconds <= self::TIMESTAMP_TIE_WINDOW_DAYS * 86400) {
            return 0;
        }

        return $otherTimestamp <=> $timestamp;
    }

    private function compareVersions(?string $a, ?string $b): int
    {
        // If either version is null, treat it as older
        if ($a === null && $b === null) {
            return 0;
        }
        if ($a === null) {
            return -1;
        }
        if ($b === null) {
            return 1;
        }

        // Trim whitespace that might cause matching issues
        $a = trim($a);
        $b = trim($b);

        // Match version numbers and optional suffix
        $matchedA = preg_match('/^(\d+(?:\.\d+)*)([a-zA-Z]*)$/', $a, $matchesA);
        $matchedB = preg_match('/^(\d+(?:\.\d+)*)([a-zA-Z]*)$/', $b, $matchesB);

        // Log if either version didn't match the pattern (this shouldn't happen normally)
        if (! $matchedA || ! $matchedB) {
            Log::warning('Version string did not match expected pattern', [
                'version_a' => $a,
                'version_b' => $b,
                'matched_a' => $matchedA,
                'matched_b' => $matchedB,
                'upload_a_filename' => $this->filename,
            ]);
        }

        // If neither matched the pattern, fall back to string comparison
        if (! $matchedA && ! $matchedB) {
            return strcmp($a, $b);
        }

        // If only one matched, the one that matched is considered newer
        if (! $matchedA) {
            return -1;
        }
        if (! $matchedB) {
            return 1;
        }

        // Compare the numeric parts first
        $verCompare = version_compare($matchesA[1], $matchesB[1]);
        if ($verCompare !== 0) {
            return $verCompare;
        }

        // If numeric parts are equal, compare the suffixes
        $suffixA = $matchesA[2] ?? '';
        $suffixB = $matchesB[2] ?? '';

        // Having a suffix wins over no suffix (1.5c is newer than 1.5)
        if ($suffixA === '' && $suffixB !== '') {
            return -1;
        }
        if ($suffixB === '' && $suffixA !== '') {
            return 1;
        }

        // If both have suffixes, compare them
        return strcmp($suffixA, $suffixB);
    }

    private function getVersion(): ?string
    {
        if ($this->extractedVersion === null) {
            // Create a temporary Game instance to use its version extraction logic
            $game = new Game;
            $this->extractedVersion = $game->extractVersion([
                'filename' => $this->filename,
                'display_name' => $this->displayName,
                'build' => [
                    'user_version' => $this->buildId ? $this->userVersion : null,
                ],
                'user_version' => $this->userVersion,
                'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            ]);
        }

        return $this->extractedVersion;
    }
}
