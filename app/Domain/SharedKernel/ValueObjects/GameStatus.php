<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Represents the status of a game.
 */
final class GameStatus implements Stringable
{
    // Define valid statuses as constants
    public const COMPLETE = 'Complete';
    public const IN_DEVELOPMENT = 'In Development';
    public const ON_HOLD = 'On Hold';
    public const CANCELLED = 'Cancelled';
    public const DEMO = 'Demo';
    public const EARLY_ACCESS = 'Early Access';

    /**
     * Defines all valid statuses.
     *
     * @return array<string>
     */
    public static function validStatuses(): array
    {
        return [
            self::COMPLETE,
            self::IN_DEVELOPMENT,
            self::ON_HOLD,
            self::CANCELLED,
            self::DEMO,
            self::EARLY_ACCESS,
        ];
    }

    private readonly string $value;

    /**
     * Private constructor to enforce creation via factory methods.
     *
     * @param string $value The status string
     * @throws InvalidArgumentException If the status is not valid
     */
    private function __construct(string $value)
    {
        $value = trim($value);
        
        if (!in_array($value, self::validStatuses(), true) && !empty($value)) {
            throw new InvalidArgumentException("Invalid game status: '{$value}'");
        }
        
        $this->value = $value;
    }

    /**
     * Creates a GameStatus instance from a string.
     *
     * @param string $status The status string
     * @return self
     * @throws InvalidArgumentException If the status is not valid
     */
    public static function fromString(string $status): self
    {
        return new self($status);
    }

    /**
     * Creates a Complete status.
     *
     * @return self
     */
    public static function complete(): self
    {
        return new self(self::COMPLETE);
    }

    /**
     * Creates an In Development status.
     *
     * @return self
     */
    public static function inDevelopment(): self
    {
        return new self(self::IN_DEVELOPMENT);
    }

    /**
     * Creates an On Hold status.
     *
     * @return self
     */
    public static function onHold(): self
    {
        return new self(self::ON_HOLD);
    }

    /**
     * Creates a Cancelled status.
     *
     * @return self
     */
    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    /**
     * Creates a Demo status.
     *
     * @return self
     */
    public static function demo(): self
    {
        return new self(self::DEMO);
    }

    /**
     * Creates an Early Access status.
     *
     * @return self
     */
    public static function earlyAccess(): self
    {
        return new self(self::EARLY_ACCESS);
    }

    /**
     * Gets the status string value.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Returns the status as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Checks if this GameStatus is equal to another.
     *
     * @param self $other The other GameStatus to compare with
     * @return bool True if the values are equal, false otherwise
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Checks if the game is complete.
     *
     * @return bool
     */
    public function isComplete(): bool
    {
        return $this->value === self::COMPLETE;
    }

    /**
     * Checks if the game is in development.
     *
     * @return bool
     */
    public function isInDevelopment(): bool
    {
        return $this->value === self::IN_DEVELOPMENT;
    }

    /**
     * Checks if the game is on hold.
     *
     * @return bool
     */
    public function isOnHold(): bool
    {
        return $this->value === self::ON_HOLD;
    }

    /**
     * Checks if the game is cancelled.
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->value === self::CANCELLED;
    }

    /**
     * Checks if the game is a demo.
     *
     * @return bool
     */
    public function isDemo(): bool
    {
        return $this->value === self::DEMO;
    }

    /**
     * Checks if the game is in early access.
     *
     * @return bool
     */
    public function isEarlyAccess(): bool
    {
        return $this->value === self::EARLY_ACCESS;
    }

    /**
     * Checks if the game is still being actively developed.
     *
     * @return bool
     */
    public function isActivelyDeveloped(): bool
    {
        return $this->isInDevelopment() || $this->isEarlyAccess();
    }

    /**
     * Checks if the game development has stopped.
     *
     * @return bool
     */
    public function isDevelopmentStopped(): bool
    {
        return $this->isComplete() || $this->isCancelled() || $this->isOnHold();
    }
} 