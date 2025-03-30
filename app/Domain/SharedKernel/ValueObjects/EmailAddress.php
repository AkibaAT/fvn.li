<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Represents an email address.
 * Ensures the email format is valid upon creation.
 */
final class EmailAddress implements Stringable
{
    private readonly string $value;

    /**
     * Private constructor to enforce creation via factory method.
     *
     * @param string $value The email address string.
     * @throws InvalidArgumentException If the email format is invalid.
     */
    private function __construct(string $value)
    {
        $email = filter_var($value, FILTER_SANITIZE_EMAIL);
        if ($email === false || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email address format: '{$value}'");
        }
        $this->value = $email;
    }

    /**
     * Creates an EmailAddress instance from a string.
     *
     * @param string $email The email address string.
     * @return self
     * @throws InvalidArgumentException If the email format is invalid.
     */
    public static function fromString(string $email): self
    {
        return new self($email);
    }

    /**
     * Gets the email address string value.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Returns the email address as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Checks if this EmailAddress is equal to another.
     *
     * @param self $other The other EmailAddress to compare with.
     * @return bool True if the values are equal, false otherwise.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}