<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\SharedKernel\ValueObjects\GameSlug;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class GameSlugCast implements CastsAttributes
{
    /**
     * Cast the given value to a GameSlug object.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return GameSlug|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?GameSlug
    {
        if ($value === null || $value === '') {
            return null;
        }

        return GameSlug::fromString($value);
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

        if (!$value instanceof GameSlug) {
            throw new InvalidArgumentException('Value must be a GameSlug instance');
        }

        return $value->getValue();
    }
} 