<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Represents a game upload with its metadata.
 */
final class Upload implements JsonSerializable
{
    private const array PROCESSABLE_EXTENSIONS = [
        'zip',
        'gz',
        'bz2',
        'tar',
    ];

    private ?Version $extractedVersion = null;

    /**
     * Create a new Upload instance.
     *
     * @param int $id The upload ID
     * @param Filename $filename The filename
     * @param string|null $displayName Optional display name
     * @param MD5Hash|null $md5Hash MD5 hash of the file, if available
     * @param DateTimeInterface $updatedAt When the upload was last updated
     * @param int|null $buildId Associated build ID, if any
     * @param DateTimeInterface|null $buildUpdatedAt When the associated build was updated
     * @param string|null $userVersion User-specified version, if provided
     * @param array $traits Array of traits/tags for this upload
     * @param string $type Upload type
     */
    public function __construct(
        private readonly int $id,
        private readonly Filename $filename,
        private readonly ?string $displayName,
        private readonly ?MD5Hash $md5Hash,
        private readonly DateTimeInterface $updatedAt,
        private readonly ?int $buildId,
        private readonly ?DateTimeInterface $buildUpdatedAt = null,
        private readonly ?string $userVersion = null,
        private readonly array $traits = [],
        private readonly string $type = ''
    ) {
    }

    /**
     * Creates an Upload instance from an array.
     *
     * @param array $data The upload data
     * @param int $id The upload ID
     * @return self
     */
    public static function fromArray(array $data, int $id): self
    {
        return new self(
            id: $id,
            filename: Filename::fromString($data['filename']),
            displayName: $data['display_name'] ?? null,
            md5Hash: isset($data['md5_hash']) ? MD5Hash::fromString($data['md5_hash']) : null,
            updatedAt: new \DateTime($data['updated_at']),
            buildId: $data['build_id'] ? intval($data['build_id']) : null,
            buildUpdatedAt: isset($data['build_updated_at']) ? new \DateTime($data['build_updated_at']) : null,
            userVersion: $data['user_version'] ?? null,
            traits: $data['traits'] ?? [],
            type: $data['type'] ?? ''
        );
    }

    /**
     * Get the upload ID.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the filename.
     *
     * @return Filename
     */
    public function getFilename(): Filename
    {
        return $this->filename;
    }

    /**
     * Get the display name.
     *
     * @return string|null
     */
    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    /**
     * Get the MD5 hash.
     *
     * @return MD5Hash|null
     */
    public function getMd5Hash(): ?MD5Hash
    {
        return $this->md5Hash;
    }

    /**
     * Get the updated at datetime.
     *
     * @return DateTimeInterface
     */
    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Get the build ID.
     *
     * @return int|null
     */
    public function getBuildId(): ?int
    {
        return $this->buildId;
    }

    /**
     * Get the build updated at datetime.
     *
     * @return DateTimeInterface|null
     */
    public function getBuildUpdatedAt(): ?DateTimeInterface
    {
        return $this->buildUpdatedAt;
    }

    /**
     * Get the user-specified version.
     *
     * @return string|null
     */
    public function getUserVersion(): ?string
    {
        return $this->userVersion;
    }

    /**
     * Get the upload traits.
     *
     * @return array
     */
    public function getTraits(): array
    {
        return $this->traits;
    }

    /**
     * Get the upload type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Check if the upload is processable.
     *
     * @return bool
     */
    public function isProcessable(): bool
    {
        // Web versions are never processable
        if ($this->isWeb() || $this->type === 'book') {
            return false;
        }

        $extension = $this->filename->getExtension();
        if ($extension === null) {
            return false;
        }

        $ext = $extension->getValue();

        // Special handling for tar.gz and tar.bz2
        if ($ext === 'gz' || $ext === 'bz2') {
            $basename = basename($this->filename->getValue(), ".{$ext}");
            if (strtolower(pathinfo($basename, PATHINFO_EXTENSION)) === 'tar') {
                return true;
            }
        }

        return in_array($ext, self::PROCESSABLE_EXTENSIONS);
    }

    /**
     * Check if this is a web upload.
     *
     * @return bool
     */
    public function isWeb(): bool
    {
        return $this->type === 'html';
    }

    /**
     * Check if this upload targets Linux.
     *
     * @return bool
     */
    public function isLinux(): bool
    {
        return in_array('p_linux', $this->traits);
    }

    /**
     * Check if this upload targets Windows.
     *
     * @return bool
     */
    public function isWindows(): bool
    {
        return in_array('p_windows', $this->traits);
    }

    /**
     * Check if this upload targets macOS.
     *
     * @return bool
     */
    public function isMac(): bool
    {
        return in_array('p_osx', $this->traits);
    }

    /**
     * Check if this upload targets Android.
     *
     * @return bool
     */
    public function isAndroid(): bool
    {
        return in_array('p_android', $this->traits);
    }

    /**
     * Check if the filename contains Linux indicators.
     *
     * @return bool
     */
    public function hasLinuxFileName(): bool
    {
        $patterns = ['/linux/i', '/.tar/i'];
        $names = array_filter([$this->filename->getValue(), $this->displayName]);

        foreach ($patterns as $pattern) {
            foreach ($names as $name) {
                if (preg_match($pattern, $name)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if the filename contains PC indicators.
     *
     * @return bool
     */
    public function hasPcFileName(): bool
    {
        $patterns = ['/pc/i'];
        $names = array_filter([$this->filename->getValue(), $this->displayName]);

        foreach ($patterns as $pattern) {
            foreach ($names as $name) {
                if (preg_match($pattern, $name)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if this is a ZIP archive.
     *
     * @return bool
     */
    public function isZip(): bool
    {
        $extension = $this->filename->getExtension();
        return $extension !== null && $extension->getValue() === 'zip';
    }

    /**
     * Compare this upload to another upload for sorting.
     *
     * @param self $other The other upload to compare with
     * @return int
     */
    public function compareTo(self $other): int
    {
        $criteria = [
            // Compare versions first (higher version wins)
            fn ($a, $b) => Version::compareVersionStrings(
                $b->getVersionString(),
                $a->getVersionString()
            ),
            // Then fall back to other criteria
            fn ($a, $b) => $b->isLinux() <=> $a->isLinux(),
            fn ($a, $b) => $b->isWindows() <=> $a->isWindows(),
            fn ($a, $b) => $b->hasLinuxFileName() <=> $a->hasLinuxFileName(),
            fn ($a, $b) => $b->hasPcFileName() <=> $a->hasPcFileName(),
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

    /**
     * Get the version string.
     *
     * @return string|null
     */
    public function getVersionString(): ?string
    {
        if ($this->userVersion !== null) {
            return $this->userVersion;
        }

        // Extract version from filename
        $filenameStr = $this->filename->getValue();
        $displayName = $this->displayName;

        // First, try to extract from direct filename
        if (preg_match('/[bB]uild[_\s-]*(\d+)/', $filenameStr, $matches)) {
            if ($this->isProbableVersion($matches[1])) {
                return $matches[1];
            }
        }

        // Look for version patterns in filename
        preg_match_all('/(?:[vV](?:ersion)?)?\s*(\d+(?:\.\d+)*[a-zA-Z]*)(?=[-_. ]|$)/i',
            $filenameStr, $matches);
        foreach ($matches[1] as $version) {
            if ($this->isProbableVersion($version)) {
                return $version;
            }
        }

        // Try the display name if available
        if ($displayName !== null) {
            if (preg_match('/[bB]uild[_\s-]*(\d+)/', $displayName, $matches)) {
                if ($this->isProbableVersion($matches[1])) {
                    return $matches[1];
                }
            }

            preg_match_all('/(?:[vV](?:ersion)?)?\s*(\d+(?:\.\d+)*[a-zA-Z]*)(?=[-_. ]|$)/i',
                $displayName, $matches);
            foreach ($matches[1] as $version) {
                if ($this->isProbableVersion($version)) {
                    return $version;
                }
            }
        }

        // Fall back to date-based version
        return $this->updatedAt->format('Y.m.d');
    }

    /**
     * Convert to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'filename' => $this->filename->getValue(),
            'display_name' => $this->displayName,
            'md5_hash' => $this->md5Hash?->getValue(),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'build_id' => $this->buildId,
            'build_updated_at' => $this->buildUpdatedAt?->format('Y-m-d H:i:s'),
            'user_version' => $this->userVersion,
            'traits' => $this->traits,
            'type' => $this->type,
        ];
    }

    /**
     * Provide data for JSON serialization.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Helper method to check if a string looks like a probable version number.
     *
     * @param string $version The version string to check
     * @return bool
     */
    private function isProbableVersion(string $version): bool
    {
        if (empty($version)) {
            return false;
        }

        // Try to create a Version object
        $parsedVersion = Version::tryFromString($version);
        
        return $parsedVersion !== null;
    }
}

// Add a static helper method to Version class for comparing version strings
if (!method_exists(Version::class, 'compareVersionStrings')) {
    class_alias(Version::class, 'VersionOriginal');
    
    /**
     * @mixin VersionOriginal
     */
    class Version extends VersionOriginal
    {
        /**
         * Compare two version strings.
         *
         * @param string|null $a First version string
         * @param string|null $b Second version string
         * @return int
         */
        public static function compareVersionStrings(?string $a, ?string $b): int
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

            $versionA = self::tryFromString($a);
            $versionB = self::tryFromString($b);

            // If either can't be parsed, fall back to string comparison
            if ($versionA === null || $versionB === null) {
                return strcmp($a, $b);
            }

            return $versionA->compareTo($versionB);
        }
    }
} 