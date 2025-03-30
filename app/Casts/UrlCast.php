<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\SharedKernel\ValueObjects\Url;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class UrlCast implements CastsAttributes
{
    /**
     * Cast the given value to a Url object.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return Url|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Url
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Url::fromString($value);
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

        if (!$value instanceof Url) {
            throw new InvalidArgumentException('Value must be a Url instance');
        }

        return $value->getValue();
    }
} 