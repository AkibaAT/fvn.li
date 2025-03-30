<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\SharedKernel\ValueObjects\WordCount;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class WordCountCast implements CastsAttributes
{
    /**
     * Cast the given value to a WordCount object.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return WordCount|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?WordCount
    {
        if ($value === null) {
            return null;
        }

        return WordCount::fromInt((int) $value);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return int|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof WordCount) {
            throw new InvalidArgumentException('Value must be a WordCount instance');
        }

        return $value->getValue();
    }
} 