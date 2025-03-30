<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Represents an MD5 hash value.
 */
final class MD5Hash implements Stringable
{
    private readonly string $value;

    /**
     * Private constructor to enforce creation via factory methods.
     *
     * @param string $value The MD5 hash string
     */
    private function __construct(string $value)
    {
        $value = trim($value);
        
        if (empty($value)) {
            throw new InvalidArgumentException("MD5 hash cannot be empty");
        }

        // MD5 hashes are always 32 hex characters
        if (!preg_match('/^[0-9a-f]{32}$/i', $value)) {
            throw new InvalidArgumentException("Invalid MD5 hash format: '{$value}'");
        }

        // Store lowercase for consistency
        $this->value = strtolower($value);
    }

    /**
     * Creates an MD5Hash instance from a string.
     *
     * @param string $hash The MD5 hash string
     * @return self
     * @throws InvalidArgumentException If the hash format is invalid
     */
    public static function fromString(string $hash): self
    {
        return new self($hash);
    }

    /**
     * Creates an MD5Hash from a data string by computing its MD5 hash.
     *
     * @param string $data The data to hash
     * @return self
     */
    public static function fromData(string $data): self
    {
        return new self(md5($data));
    }

    /**
     * Creates an MD5Hash from file content by computing its MD5 hash.
     *
     * @param string $filePath The path to the file
     * @return self
     * @throws InvalidArgumentException If the file cannot be read
     */
    public static function fromFile(string $filePath): self
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("Cannot read file: '{$filePath}'");
        }
        
        return new self(md5_file($filePath));
    }

    /**
     * Gets the MD5 hash string value.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Returns the MD5 hash as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Checks if this MD5Hash is equal to another.
     *
     * @param self $other The other MD5Hash to compare with
     * @return bool True if the values are equal, false otherwise
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifies if this hash matches the MD5 of the given data.
     *
     * @param string $data The data to verify
     * @return bool True if the hash matches, false otherwise
     */
    public function verifyData(string $data): bool
    {
        return $this->value === md5($data);
    }

    /**
     * Verifies if this hash matches the MD5 of the given file.
     *
     * @param string $filePath The path to the file to verify
     * @return bool True if the hash matches, false otherwise
     * @throws InvalidArgumentException If the file cannot be read
     */
    public function verifyFile(string $filePath): bool
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("Cannot read file: '{$filePath}'");
        }
        
        return $this->value === md5_file($filePath);
    }
} 