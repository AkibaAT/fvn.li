<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Represents a game name with validation.
 */
final class GameName implements Stringable
{
    private readonly string $value;

    /**
     * Private constructor to enforce creation via factory methods.
     *
     * @param string $value The game name
     */
    private function __construct(string $value)
    {
        $value = trim($value);
        
        if (empty($value)) {
            throw new InvalidArgumentException("Game name cannot be empty");
        }
        
        if (mb_strlen($value) > 255) {
            throw new InvalidArgumentException("Game name cannot exceed 255 characters");
        }
        
        $this->value = $value;
    }

    /**
     * Creates a GameName instance from a string.
     *
     * @param string $name The game name
     * @return self
     * @throws InvalidArgumentException If the name is invalid
     */
    public static function fromString(string $name): self
    {
        return new self($name);
    }

    /**
     * Creates a GameName from a short string, with validation.
     *
     * @param string $name The short game name
     * @param int $maxLength The maximum allowed length
     * @return self
     * @throws InvalidArgumentException If the name is invalid
     */
    public static function fromShortString(string $name, int $maxLength = 50): self
    {
        $name = trim($name);
        
        if (mb_strlen($name) > $maxLength) {
            throw new InvalidArgumentException("Short game name cannot exceed {$maxLength} characters");
        }
        
        return new self($name);
    }

    /**
     * Gets the game name string value.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Returns the game name as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Checks if this GameName is equal to another.
     *
     * @param self $other The other GameName to compare with
     * @return bool True if the values are equal, false otherwise
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Creates a GameSlug from this game name.
     *
     * @return GameSlug
     */
    public function toSlug(): GameSlug
    {
        return GameSlug::fromName($this->value);
    }

    /**
     * Returns a shortened version of the game name.
     *
     * @param int $length Maximum length of the shortened name
     * @return string
     */
    public function shorten(int $length = 30): string
    {
        if (mb_strlen($this->value) <= $length) {
            return $this->value;
        }
        
        return mb_substr($this->value, 0, $length) . '...';
    }

    /**
     * Checks if this game name contains the given substring.
     *
     * @param string $substring The substring to search for
     * @param bool $caseSensitive Whether to perform a case-sensitive search
     * @return bool
     */
    public function contains(string $substring, bool $caseSensitive = false): bool
    {
        if ($caseSensitive) {
            return str_contains($this->value, $substring);
        }
        
        return str_contains(mb_strtolower($this->value), mb_strtolower($substring));
    }

    /**
     * Checks if this game name matches the given pattern.
     *
     * @param string $pattern The regex pattern to match
     * @return bool
     */
    public function matches(string $pattern): bool
    {
        return (bool) preg_match($pattern, $this->value);
    }
} 