<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\SharedKernel\ValueObjects\GameName;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class GameNameCast implements CastsAttributes
{
    /**
     * Cast the given value to a GameName object.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return GameName|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?GameName
    {
        if ($value === null || $value === '') {
            return null;
        }

        return GameName::fromString($value);
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

        if (!$value instanceof GameName) {
            throw new InvalidArgumentException('Value must be a GameName instance');
        }

        return $value->getValue();
    }
} 