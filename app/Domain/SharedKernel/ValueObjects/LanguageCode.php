<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Represents an ISO 639 language code (either 2-letter or 3-letter format).
 * Validates code format and provides type information.
 */
final class LanguageCode implements Stringable
{
    private readonly string $value;
    private readonly string $type;

    /**
     * @param string $value The ISO language code
     * @throws InvalidArgumentException If the code format is invalid
     */
    private function __construct(string $value)
    {
        $value = strtolower(trim($value));
        
        if (!preg_match('/^[a-z]{2,3}$/', $value)) {
            throw new InvalidArgumentException("Invalid ISO language code format: '{$value}'");
        }

        $this->value = $value;
        $this->type = strlen($value) === 2 ? 'alpha2' : 'alpha3';
    }

    public static function fromString(string $code): self
    {
        return new self($code);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isAlpha2(): bool
    {
        return $this->type === 'alpha2';
    }

    public function isAlpha3(): bool
    {
        return $this->type === 'alpha3';
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}