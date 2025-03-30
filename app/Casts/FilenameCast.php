<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\SharedKernel\ValueObjects\Filename;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class FilenameCast implements CastsAttributes
{
    /**
     * Cast the given value to a Filename object.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return Filename|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Filename
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Filename::fromString($value);
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

        if (!$value instanceof Filename) {
            throw new InvalidArgumentException('Value must be a Filename instance');
        }

        return $value->getValue();
    }
} 