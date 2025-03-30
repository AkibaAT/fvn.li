<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use InvalidArgumentException;
use Stringable;
use Illuminate\Support\Str;

/**
 * Represents a URL-friendly slug for games.
 */
final class GameSlug implements Stringable
{
    private readonly string $value;

    /**
     * Private constructor to enforce creation via factory methods.
     *
     * @param string $value The slug value
     */
    private function __construct(string $value)
    {
        $value = trim($value);
        
        if (empty($value)) {
            throw new InvalidArgumentException("Game slug cannot be empty");
        }
        
        if (mb_strlen($value) > 255) {
            throw new InvalidArgumentException("Game slug cannot exceed 255 characters");
        }
        
        // Ensure slug only contains URL-safe characters
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            throw new InvalidArgumentException("Game slug contains invalid characters. Use only lowercase letters, numbers, and hyphens");
        }
        
        $this->value = $value;
    }

    /**
     * Creates a GameSlug instance from a string.
     *
     * @param string $slug The game slug
     * @return self
     * @throws InvalidArgumentException If the slug is invalid
     */
    public static function fromString(string $slug): self
    {
        return new self($slug);
    }

    /**
     * Creates a GameSlug from a game name.
     *
     * @param string $name The game name to convert to a slug
     * @return self
     * @throws InvalidArgumentException If the resulting slug is invalid
     */
    public static function fromName(string $name): self
    {
        $slug = Str::slug($name);
        
        if (empty($slug)) {
            throw new InvalidArgumentException("Cannot create a valid slug from the given name");
        }
        
        return new self($slug);
    }

    /**
     * Creates a random GameSlug.
     *
     * @param int $length The length of the random string (default: 12)
     * @return self
     */
    public static function random(int $length = 12): self
    {
        $length = max(4, $length); // Ensure minimum length
        $randomString = Str::lower(Str::random($length));
        $randomString = preg_replace('/[^a-z0-9]/', '', $randomString);
        
        return new self($randomString);
    }

    /**
     * Gets the slug string value.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Returns the slug as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Checks if this GameSlug is equal to another.
     *
     * @param self $other The other GameSlug to compare with
     * @return bool True if the values are equal, false otherwise
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Checks if this slug matches a given string pattern.
     *
     * @param string $pattern The string pattern to match against
     * @return bool
     */
    public function matches(string $pattern): bool
    {
        return $this->value === $pattern || Str::is($pattern, $this->value);
    }
    
    /**
     * Creates a concatenated slug with this slug and another string.
     *
     * @param string $addition The string to append
     * @return self
     */
    public function concat(string $addition): self
    {
        $newSlug = $this->value . '-' . Str::slug($addition);
        
        if (mb_strlen($newSlug) > 255) {
            $newSlug = mb_substr($newSlug, 0, 255);
            // Ensure we don't end with a hyphen
            $newSlug = rtrim($newSlug, '-');
        }
        
        return new self($newSlug);
    }
    
    /**
     * Returns the full URL path for this game slug.
     *
     * @param string $prefix Optional prefix to prepend to the path
     * @return string The URL path component
     */
    public function toPath(string $prefix = 'games'): string
    {
        return trim($prefix, '/') . '/' . $this->value;
    }
} 