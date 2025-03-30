<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\SharedKernel\ValueObjects\GameStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class GameStatusCast implements CastsAttributes
{
    /**
     * Cast the given value to a GameStatus object.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return GameStatus|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?GameStatus
    {
        if ($value === null || $value === '') {
            return null;
        }

        return GameStatus::fromString($value);
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

        if (!$value instanceof GameStatus) {
            throw new InvalidArgumentException('Value must be a GameStatus instance');
        }

        return $value->getValue();
    }
} 