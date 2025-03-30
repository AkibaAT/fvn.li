<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Represents a file extension.
 */
final class FileExtension implements Stringable
{
    // Common archive extensions
    public const ARCHIVE_EXTENSIONS = [
        'zip', 'rar', 'tar', 'gz', 'bz2', '7z',
    ];

    // Common image extensions
    public const IMAGE_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg',
    ];

    // Common document extensions
    public const DOCUMENT_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'txt', 'rtf', 'md',
    ];

    // Common executable extensions
    public const EXECUTABLE_EXTENSIONS = [
        'exe', 'bat', 'com', 'cmd', 'sh', 'app',
    ];

    // Common web extensions
    public const WEB_EXTENSIONS = [
        'html', 'htm', 'xhtml', 'js', 'css', 'php',
    ];

    private readonly string $value;

    /**
     * Private constructor to enforce creation via factory methods.
     *
     * @param string $value The extension string (without the dot)
     */
    private function __construct(string $value)
    {
        $this->value = strtolower(trim($value));
        
        if (empty($this->value)) {
            throw new InvalidArgumentException("File extension cannot be empty");
        }
        
        // Ensure there's no leading dot in the stored value
        if (str_starts_with($this->value, '.')) {
            throw new InvalidArgumentException("Extension should not include the leading dot: '{$value}'");
        }
    }

    /**
     * Creates a FileExtension instance from a string.
     *
     * @param string $extension The extension string
     * @return self
     * @throws InvalidArgumentException If the extension is invalid
     */
    public static function fromString(string $extension): self
    {
        // Remove leading dot if present
        $extension = ltrim($extension, '.');
        
        return new self($extension);
    }

    /**
     * Creates a FileExtension instance from a filename.
     *
     * @param string $filename The full filename
     * @return self
     * @throws InvalidArgumentException If the filename has no extension
     */
    public static function fromFilename(string $filename): self
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (empty($extension)) {
            throw new InvalidArgumentException("Unable to extract extension from filename: '{$filename}'");
        }
        
        return new self($extension);
    }

    /**
     * Gets the extension string value (without the dot).
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Returns the extension as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Returns the extension with a leading dot.
     *
     * @return string
     */
    public function withDot(): string
    {
        return '.' . $this->value;
    }

    /**
     * Checks if this FileExtension is equal to another.
     *
     * @param self $other The other FileExtension to compare with
     * @return bool True if the values are equal, false otherwise
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Checks if this extension is in a list of extensions.
     *
     * @param array $extensions List of extension strings
     * @return bool
     */
    public function isIn(array $extensions): bool
    {
        return in_array($this->value, array_map('strtolower', $extensions), true);
    }

    /**
     * Checks if this is an archive extension.
     *
     * @return bool
     */
    public function isArchive(): bool
    {
        return $this->isIn(self::ARCHIVE_EXTENSIONS);
    }

    /**
     * Checks if this is an image extension.
     *
     * @return bool
     */
    public function isImage(): bool
    {
        return $this->isIn(self::IMAGE_EXTENSIONS);
    }

    /**
     * Checks if this is a document extension.
     *
     * @return bool
     */
    public function isDocument(): bool
    {
        return $this->isIn(self::DOCUMENT_EXTENSIONS);
    }

    /**
     * Checks if this is an executable extension.
     *
     * @return bool
     */
    public function isExecutable(): bool
    {
        return $this->isIn(self::EXECUTABLE_EXTENSIONS);
    }

    /**
     * Checks if this is a web extension.
     *
     * @return bool
     */
    public function isWeb(): bool
    {
        return $this->isIn(self::WEB_EXTENSIONS);
    }

    /**
     * Checks if this is a tar archive with additional compression.
     *
     * @return bool
     */
    public function isTarCompressed(): bool
    {
        return in_array($this->value, ['gz', 'bz2']) && $this->hasParentExtension('tar');
    }

    /**
     * Checks if the filename this extension belongs to has a parent extension.
     * For example, "file.tar.gz" has "tar" as parent extension of "gz".
     *
     * @param string $parentExt The expected parent extension
     * @return bool
     */
    public function hasParentExtension(string $parentExt): bool
    {
        // Cannot determine from just the extension, this is a best guess
        // Proper implementation would need the full filename
        return false;
    }
} 