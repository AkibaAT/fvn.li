<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\SharedKernel\ValueObjects\EmailAddress;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts an Eloquent attribute to and from an EmailAddress Value Object.
 */
class EmailAddressCast implements CastsAttributes
{
    /**
     * Cast the stored value to an EmailAddress instance.
     *
     * @param  Model  $model
     * @param  string $key
     * @param  mixed  $value  The value from the database (string|null).
     * @param  array<string, mixed> $attributes
     * @return EmailAddress|null
     * @throws InvalidArgumentException If the database value is not a valid email format.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?EmailAddress
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            // Or handle this case differently, maybe log an error?
            throw new InvalidArgumentException("Expected string value for email cast, got ".gettype($value));
        }

        // Let the VO handle validation on retrieval
        return EmailAddress::fromString($value);
    }

    /**
     * Prepare the EmailAddress instance for storage.
     *
     * @param  Model  $model
     * @param  string $key
     * @param  mixed  $value  The EmailAddress instance or null.
     * @param  array<string, mixed> $attributes
     * @return string|null
     * @throws InvalidArgumentException If the value is not an EmailAddress instance or null.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof EmailAddress) {
            throw new InvalidArgumentException('The given value is not an EmailAddress instance.');
        }

        return $value->getValue();
    }
}