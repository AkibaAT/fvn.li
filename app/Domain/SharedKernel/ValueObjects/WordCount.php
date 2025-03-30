<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Represents a word count statistic.
 */
final class WordCount implements Stringable
{
    private readonly int $value;

    /**
     * Private constructor to enforce creation via factory methods.
     *
     * @param int $value The word count value
     */
    private function __construct(int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException("Word count cannot be negative");
        }

        $this->value = $value;
    }

    /**
     * Creates a WordCount instance from an integer.
     *
     * @param int $count The word count
     * @return self
     * @throws InvalidArgumentException If the count is negative
     */
    public static function fromInt(int $count): self
    {
        return new self($count);
    }

    /**
     * Creates a WordCount from text by counting words.
     *
     * @param string $text The text to count words in
     * @return self
     */
    public static function fromText(string $text): self
    {
        // Basic word count algorithm - can be enhanced for different languages
        $wordCount = str_word_count($text);
        
        return new self($wordCount);
    }

    /**
     * Creates a zero WordCount.
     *
     * @return self
     */
    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Gets the word count value.
     *
     * @return int
     */
    public function getValue(): int
    {
        return $this->value;
    }

    /**
     * Returns the word count as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->value;
    }

    /**
     * Checks if this WordCount is equal to another.
     *
     * @param self $other The other WordCount to compare with
     * @return bool True if the values are equal, false otherwise
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Checks if this WordCount is greater than another.
     *
     * @param self $other The other WordCount to compare with
     * @return bool
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Checks if this WordCount is less than another.
     *
     * @param self $other The other WordCount to compare with
     * @return bool
     */
    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Adds another WordCount to this one.
     *
     * @param self $other The other WordCount to add
     * @return self
     */
    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    /**
     * Subtracts another WordCount from this one.
     *
     * @param self $other The other WordCount to subtract
     * @return self
     * @throws InvalidArgumentException If the result would be negative
     */
    public function subtract(self $other): self
    {
        $result = $this->value - $other->value;
        
        if ($result < 0) {
            throw new InvalidArgumentException("Word count cannot be negative");
        }
        
        return new self($result);
    }

    /**
     * Formats the word count with a thousands separator.
     *
     * @return string
     */
    public function format(): string
    {
        return number_format($this->value);
    }

    /**
     * Returns the read time estimate in minutes based on average reading speed.
     *
     * @param int $wordsPerMinute Average reading speed in words per minute
     * @return int Estimated reading time in minutes
     */
    public function getReadingTimeMinutes(int $wordsPerMinute = 200): int
    {
        if ($wordsPerMinute <= 0) {
            throw new InvalidArgumentException("Reading speed must be positive");
        }
        
        return (int)ceil($this->value / $wordsPerMinute);
    }

    /**
     * Returns a human-friendly description of the word count.
     *
     * @return string
     */
    public function getDescription(): string
    {
        if ($this->value === 0) {
            return 'No words';
        }
        
        if ($this->value === 1) {
            return '1 word';
        }
        
        return $this->format() . ' words';
    }
} 