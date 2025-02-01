<?php

declare(strict_types=1);

namespace App\ValueObjects;

use DateTime;
use DateTimeInterface;
use Illuminate\Support\Collection;

readonly class Upload
{
    public DateTimeInterface $updatedAt;
    public ?DateTimeInterface $buildUpdatedAt;

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

    public static function fromArray(array $data, int $id): self
    {
        return new self(
            id: $id,
            filename: $data['filename'],
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

    public static function fromCollection(Collection $uploads): Collection
    {
        return $uploads->map(fn ($data, $id) => self::fromArray($data, $id));
    }

    public static function sort(Collection $uploads): Collection
    {
        return $uploads->sort(fn (self $a, self $b) => $a->compareTo($b));
    }

    public static function getBest(Collection $uploads): ?self
    {
        return self::sort($uploads)->first();
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

    public function isWindows(): bool
    {
        return in_array('p_windows', $this->traits);
    }

    public function isLinux(): bool
    {
        return in_array('p_linux', $this->traits);
    }

    public function isMac(): bool
    {
        return in_array('p_osx', $this->traits);
    }

    public function isAndroid(): bool
    {
        return in_array('p_android', $this->traits);
    }

    public function isWeb(): bool
    {
        return $this->type === 'html';
    }

    public function isZip(): bool
    {
        return strtolower(pathinfo($this->filename, PATHINFO_EXTENSION)) === 'zip';
    }

    public function hasDesktopFileName(): bool
    {
        $patterns = ['/pc/i', '/linux/i'];
        $names = array_filter([$this->filename, $this->displayName]);

        return array_any($patterns, fn ($pattern) => array_any($names, fn ($name) => preg_match($pattern, $name)));
    }

    public function compareTo(self $other): int
    {
        // Never prefer web uploads
        if ($this->isWeb() !== $other->isWeb()) {
            return $other->isWeb() ? -1 : 1;
        }

        $criteria = [
            fn ($a, $b) => $b->isLinux() <=> $a->isLinux(),
            fn ($a, $b) => $b->isWindows() <=> $a->isWindows(),
            fn ($a, $b) => $b->hasDesktopFileName() <=> $a->hasDesktopFileName(),
            fn ($a, $b) => $b->isZip() <=> $a->isZip(),
            fn ($a, $b) => $b->updatedAt <=> $a->updatedAt,
            fn ($a, $b) => ($b->buildUpdatedAt ?? $b->updatedAt) <=> ($a->buildUpdatedAt ?? $a->updatedAt),
        ];

        foreach ($criteria as $criterion) {
            $result = $criterion($this, $other);
            if ($result !== 0) {
                return $result;
            }
        }

        return 0;
    }
}
