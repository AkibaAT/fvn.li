<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Represents a semantic version number with optional suffix.
 * Handles validation, comparison, and formatting of version strings.
 */
final class Version implements Stringable
{
    private readonly array $numericParts;
    private readonly string $suffix;
    private readonly string $originalVersion;

    /**
     * Private constructor to enforce creation via factory methods.
     *
     * @param array $numericParts The numeric parts of the version (e.g., [1, 2, 3] for "1.2.3")
     * @param string $suffix Optional letter suffix (e.g., "a" in "1.2.3a")
     * @param string $originalVersion The original version string for reference
     */
    private function __construct(array $numericParts, string $suffix, string $originalVersion)
    {
        if (empty($numericParts)) {
            throw new InvalidArgumentException("Version must have at least one numeric part");
        }

        // Ensure first part isn't a year or unreasonably large number
        if ($numericParts[0] > 2100 || ($numericParts[0] > 100 && strlen((string)$numericParts[0]) === 4)) {
            throw new InvalidArgumentException("First part of version cannot be a year or unreasonably large");
        }

        // Validate no parts are unreasonably large
        foreach ($numericParts as $part) {
            if ($part > 10000) {
                throw new InvalidArgumentException("Version parts cannot exceed 10000");
            }
        }

        $this->numericParts = $numericParts;
        $this->suffix = $suffix;
        $this->originalVersion = $originalVersion;
    }

    /**
     * Creates a Version instance from a string.
     *
     * @param string $version The version string (e.g., "1.2.3", "v1.2", "1.3a", etc.)
     * @return self|null Returns a Version object if valid, null if not a valid version
     */
    public static function tryFromString(string $version): ?self
    {
        // Remove leading 'v' or 'version'
        $version = preg_replace('/^[vV]ersion\s*/', '', $version);
        $version = preg_replace('/^[vV]\s*/', '', $version);

        // Match version pattern with optional letter suffix
        if (!preg_match('/^(\d+(?:\.\d+)*?)([a-zA-Z]+)?$/', $version, $matches)) {
            return null;
        }

        try {
            $parts = array_map('intval', explode('.', $matches[1]));
            $suffix = $matches[2] ?? '';

            return new self($parts, $suffix, $version);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Creates a Version instance from a string, throwing an exception if invalid.
     *
     * @param string $version The version string
     * @return self
     * @throws InvalidArgumentException If the version format is invalid
     */
    public static function fromString(string $version): self
    {
        $result = self::tryFromString($version);
        if ($result === null) {
            throw new InvalidArgumentException("Invalid version format: '{$version}'");
        }
        return $result;
    }

    /**
     * Creates a Version from year.month.day format based on a timestamp.
     *
     * @param \DateTimeInterface $dateTime The datetime to convert to a version
     * @return self The version in Y.m.d format
     */
    public static function fromDateTime(\DateTimeInterface $dateTime): self
    {
        $formatted = $dateTime->format('Y.m.d');
        $parts = array_map('intval', explode('.', $formatted));
        return new self($parts, '', $formatted);
    }

    /**
     * Gets the numeric parts of the version.
     *
     * @return array
     */
    public function getNumericParts(): array
    {
        return $this->numericParts;
    }

    /**
     * Gets the letter suffix of the version.
     *
     * @return string
     */
    public function getSuffix(): string
    {
        return $this->suffix;
    }

    /**
     * Gets the original version string.
     *
     * @return string
     */
    public function getOriginalString(): string
    {
        return $this->originalVersion;
    }

    /**
     * Returns the standardized version string (e.g., "1.2.3a").
     *
     * @return string
     */
    public function getValue(): string
    {
        return implode('.', $this->numericParts) . $this->suffix;
    }

    /**
     * Returns the version as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getValue();
    }

    /**
     * Compares this version with another version.
     *
     * @param self $other The other version to compare with
     * @return int Returns -1 if this version is older, 0 if equal, 1 if this version is newer
     */
    public function compareTo(self $other): int
    {
        // Compare each numeric part
        $maxLength = max(count($this->numericParts), count($other->numericParts));
        
        for ($i = 0; $i < $maxLength; $i++) {
            $thisValue = $i < count($this->numericParts) ? $this->numericParts[$i] : 0;
            $otherValue = $i < count($other->numericParts) ? $other->numericParts[$i] : 0;
            
            if ($thisValue !== $otherValue) {
                return $thisValue <=> $otherValue;
            }
        }
        
        // If numeric parts are equal, compare the suffixes
        if ($this->suffix === '' && $other->suffix !== '') {
            return -1; // Without suffix is older than with suffix
        }
        if ($other->suffix === '' && $this->suffix !== '') {
            return 1; // With suffix is newer than without suffix
        }
        
        // Both have suffixes, compare them
        return strcmp($this->suffix, $other->suffix);
    }

    /**
     * Checks if this version is equal to another.
     *
     * @param self $other The other version to compare with
     * @return bool True if versions are equal
     */
    public function equals(self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    /**
     * Checks if this version is greater than another.
     *
     * @param self $other The other version to compare with
     * @return bool True if this version is newer
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    /**
     * Checks if this version is less than another.
     *
     * @param self $other The other version to compare with
     * @return bool True if this version is older
     */
    public function isLessThan(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }
} 