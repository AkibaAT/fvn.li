<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\SharedKernel\ValueObjects\MD5Hash;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class MD5HashCast implements CastsAttributes
{
    /**
     * Cast the given value to an MD5Hash object.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return MD5Hash|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?MD5Hash
    {
        if ($value === null || $value === '') {
            return null;
        }

        return MD5Hash::fromString($value);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return string|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof MD5Hash) {
            throw new InvalidArgumentException('Value must be an MD5Hash instance');
        }

        return $value->getValue();
    }
} 