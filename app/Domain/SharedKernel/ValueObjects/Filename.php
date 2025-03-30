<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Represents a filename with validation and utility methods.
 */
final class Filename implements Stringable
{
    private readonly string $value;
    private readonly ?FileExtension $extension;
    private readonly string $baseName;
    private readonly string $dirName;

    /**
     * Private constructor to enforce creation via factory methods.
     *
     * @param string $value The filename
     */
    private function __construct(string $value)
    {
        $value = trim($value);
        
        if (empty($value)) {
            throw new InvalidArgumentException("Filename cannot be empty");
        }

        // Basic validation for disallowed characters (Windows + Unix)
        $invalidChars = ['<', '>', ':', '"', '/', '\\', '|', '?', '*'];
        foreach ($invalidChars as $char) {
            if (str_contains($value, $char)) {
                throw new InvalidArgumentException("Filename contains invalid character: '{$char}'");
            }
        }

        $this->value = $value;
        $this->dirName = pathinfo($value, PATHINFO_DIRNAME);
        $this->baseName = pathinfo($value, PATHINFO_BASENAME);
        
        $ext = pathinfo($value, PATHINFO_EXTENSION);
        $this->extension = !empty($ext) ? FileExtension::fromString($ext) : null;
    }

    /**
     * Creates a Filename instance from a string.
     *
     * @param string $filename The filename string
     * @return self
     * @throws InvalidArgumentException If the filename is invalid
     */
    public static function fromString(string $filename): self
    {
        return new self($filename);
    }

    /**
     * Gets the filename string value.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Gets the file extension, if present.
     *
     * @return FileExtension|null The file extension or null if none
     */
    public function getExtension(): ?FileExtension
    {
        return $this->extension;
    }

    /**
     * Gets the basename (filename without directory path).
     *
     * @return string
     */
    public function getBaseName(): string
    {
        return $this->baseName;
    }

    /**
     * Gets the directory name.
     *
     * @return string
     */
    public function getDirName(): string
    {
        return $this->dirName;
    }

    /**
     * Gets the stem (filename without extension).
     *
     * @return string
     */
    public function getStem(): string
    {
        return pathinfo($this->value, PATHINFO_FILENAME);
    }

    /**
     * Returns the filename as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Checks if this Filename is equal to another.
     *
     * @param self $other The other Filename to compare with
     * @return bool True if the values are equal, false otherwise
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Checks if the filename matches a specific pattern.
     *
     * @param string $pattern The regex pattern to match
     * @return bool
     */
    public function matches(string $pattern): bool
    {
        return (bool) preg_match($pattern, $this->value);
    }

    /**
     * Checks if the filename or directory contains a specific string.
     *
     * @param string $needle The string to search for
     * @param bool $caseSensitive Whether to perform a case-sensitive search
     * @return bool
     */
    public function contains(string $needle, bool $caseSensitive = false): bool
    {
        if ($caseSensitive) {
            return str_contains($this->value, $needle);
        }
        
        return str_contains(strtolower($this->value), strtolower($needle));
    }

    /**
     * Checks if this filename has the given extension.
     *
     * @param string $extension The extension to check (with or without dot)
     * @return bool
     */
    public function hasExtension(string $extension): bool
    {
        if ($this->extension === null) {
            return false;
        }
        
        // Normalize the extension by removing any leading dot
        $extension = ltrim($extension, '.');
        
        return strtolower($this->extension->getValue()) === strtolower($extension);
    }

    /**
     * Gets a new Filename with a different extension.
     *
     * @param string $newExtension The new extension (with or without dot)
     * @return self
     */
    public function withExtension(string $newExtension): self
    {
        $newExtension = ltrim($newExtension, '.');
        $directory = $this->dirName !== '.' ? $this->dirName . '/' : '';
        $newFilename = $directory . $this->getStem() . '.' . $newExtension;
        
        return new self($newFilename);
    }

    /**
     * Gets a new Filename with a suffix added before the extension.
     *
     * @param string $suffix The suffix to add
     * @return self
     */
    public function withSuffix(string $suffix): self
    {
        $directory = $this->dirName !== '.' ? $this->dirName . '/' : '';
        $extension = $this->extension !== null ? '.' . $this->extension->getValue() : '';
        $newFilename = $directory . $this->getStem() . $suffix . $extension;
        
        return new self($newFilename);
    }
} 