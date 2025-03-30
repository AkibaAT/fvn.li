<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Represents personal notes with text sanitization and length validation.
 */
final class PersonalNotes implements Stringable
{
    private readonly string $value;
    private readonly int $maxLength;

    /**
     * Private constructor to enforce creation via factory methods.
     *
     * @param string $value The notes text
     * @param int $maxLength Maximum allowed length
     */
    private function __construct(string $value, int $maxLength = 10000)
    {
        if ($maxLength <= 0) {
            throw new InvalidArgumentException("Maximum length must be positive");
        }

        $this->maxLength = $maxLength;
        
        // Trim whitespace
        $value = trim($value);
        
        // Validate length
        if (mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException("Notes exceed maximum length of {$maxLength} characters");
        }

        $this->value = $value;
    }

    /**
     * Creates a PersonalNotes instance from a string.
     *
     * @param string $notes The notes text
     * @param int $maxLength Maximum allowed length
     * @return self
     * @throws InvalidArgumentException If the notes are too long
     */
    public static function fromString(string $notes, int $maxLength = 10000): self
    {
        return new self($notes, $maxLength);
    }

    /**
     * Creates an empty PersonalNotes instance.
     *
     * @return self
     */
    public static function empty(): self
    {
        return new self('');
    }

    /**
     * Gets the notes string value.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Returns the notes as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Checks if this PersonalNotes is equal to another.
     *
     * @param self $other The other PersonalNotes to compare with
     * @return bool True if the values are equal, false otherwise
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Checks if these notes are empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    /**
     * Returns a summary of the notes (first few characters).
     *
     * @param int $length Length of the summary
     * @return string
     */
    public function getSummary(int $length = 50): string
    {
        if (mb_strlen($this->value) <= $length) {
            return $this->value;
        }
        
        return mb_substr($this->value, 0, $length) . '...';
    }

    /**
     * Get the length of the notes in characters.
     *
     * @return int
     */
    public function getLength(): int
    {
        return mb_strlen($this->value);
    }

    /**
     * Get the word count of the notes.
     *
     * @return WordCount
     */
    public function getWordCount(): WordCount
    {
        return WordCount::fromText($this->value);
    }

    /**
     * Returns a new PersonalNotes with the given text appended.
     *
     * @param string $text The text to append
     * @return self
     * @throws InvalidArgumentException If the resulting notes would be too long
     */
    public function append(string $text): self
    {
        $newValue = $this->value;
        
        if (!empty($newValue) && !empty($text)) {
            $newValue .= "\n\n";
        }
        
        $newValue .= $text;
        
        return new self($newValue, $this->maxLength);
    }

    /**
     * Returns a new PersonalNotes with a new maximum length.
     *
     * @param int $maxLength The new maximum length
     * @return self
     * @throws InvalidArgumentException If the current notes exceed the new maximum, or if max length is not positive
     */
    public function withMaxLength(int $maxLength): self
    {
        return new self($this->value, $maxLength);
    }
} 