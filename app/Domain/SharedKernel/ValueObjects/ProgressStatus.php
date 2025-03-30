<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Represents the progress status of a user's gameplay.
 */
final class ProgressStatus implements Stringable
{
    // Define valid statuses as constants
    public const PLAYED = 'played';
    public const PLAYING = 'playing';
    public const COMPLETED = 'completed';
    public const DROPPED = 'dropped';
    public const ON_HOLD = 'on_hold';
    public const PLAN_TO_PLAY = 'plan_to_play';

    /**
     * Defines all valid statuses.
     *
     * @return array<string>
     */
    public static function validStatuses(): array
    {
        return [
            self::PLAYED,
            self::PLAYING,
            self::COMPLETED,
            self::DROPPED,
            self::ON_HOLD,
            self::PLAN_TO_PLAY,
        ];
    }

    /**
     * Get user-friendly labels for statuses.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::PLAYED => 'Played',
            self::PLAYING => 'Playing',
            self::COMPLETED => 'Completed',
            self::DROPPED => 'Dropped',
            self::ON_HOLD => 'On Hold',
            self::PLAN_TO_PLAY => 'Plan to Play',
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
            throw new InvalidArgumentException("Invalid progress status: '{$value}'");
        }
        
        $this->value = $value;
    }

    /**
     * Creates a ProgressStatus instance from a string.
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
     * Creates a Played status.
     *
     * @return self
     */
    public static function played(): self
    {
        return new self(self::PLAYED);
    }

    /**
     * Creates a Playing status.
     *
     * @return self
     */
    public static function playing(): self
    {
        return new self(self::PLAYING);
    }

    /**
     * Creates a Completed status.
     *
     * @return self
     */
    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    /**
     * Creates a Dropped status.
     *
     * @return self
     */
    public static function dropped(): self
    {
        return new self(self::DROPPED);
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
     * Creates a Plan to Play status.
     *
     * @return self
     */
    public static function planToPlay(): self
    {
        return new self(self::PLAN_TO_PLAY);
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
     * Gets the human-friendly label for the status.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return self::statusLabels()[$this->value] ?? $this->value;
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
     * Checks if this ProgressStatus is equal to another.
     *
     * @param self $other The other ProgressStatus to compare with
     * @return bool True if the values are equal, false otherwise
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Checks if the status is played.
     *
     * @return bool
     */
    public function isPlayed(): bool
    {
        return $this->value === self::PLAYED;
    }

    /**
     * Checks if the status is playing.
     *
     * @return bool
     */
    public function isPlaying(): bool
    {
        return $this->value === self::PLAYING;
    }

    /**
     * Checks if the status is completed.
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->value === self::COMPLETED;
    }

    /**
     * Checks if the status is dropped.
     *
     * @return bool
     */
    public function isDropped(): bool
    {
        return $this->value === self::DROPPED;
    }

    /**
     * Checks if the status is on hold.
     *
     * @return bool
     */
    public function isOnHold(): bool
    {
        return $this->value === self::ON_HOLD;
    }

    /**
     * Checks if the status is plan to play.
     *
     * @return bool
     */
    public function isPlanToPlay(): bool
    {
        return $this->value === self::PLAN_TO_PLAY;
    }

    /**
     * Checks if the game has been started.
     *
     * @return bool
     */
    public function hasStarted(): bool
    {
        return $this->isPlayed() || $this->isPlaying() || $this->isCompleted() || $this->isOnHold();
    }

    /**
     * Checks if the game progress is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isPlaying();
    }

    /**
     * Checks if the game progress is inactive.
     *
     * @return bool
     */
    public function isInactive(): bool
    {
        return $this->isOnHold() || $this->isDropped() || $this->isPlanToPlay();
    }

    /**
     * Checks if the game has been finished.
     *
     * @return bool
     */
    public function isFinished(): bool
    {
        return $this->isCompleted() || $this->isDropped();
    }
} 