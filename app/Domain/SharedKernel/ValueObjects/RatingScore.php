<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use InvalidArgumentException;
use Stringable;

final class RatingScore implements Stringable
{
    private readonly float $value;

    public function __construct(float $value)
    {
        if ($value === 0.0) {
            $this->value = 0.0;
            return;
        }

        if ($value < 1.0 || $value > 10.0) {
            throw new InvalidArgumentException("Rating score must be between 1-10");
        }

        $this->value = $value;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function __toString(): string
    {
        if ($this->value === 0.0) {
            return '-';
        }
        return number_format($this->value, 1);
    }

    public function equals(self $other): bool
    {
        return abs($this->value - $other->value) < 0.000001; // Float comparison with epsilon
    }
}