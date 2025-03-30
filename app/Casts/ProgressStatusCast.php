<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\SharedKernel\ValueObjects\ProgressStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ProgressStatusCast implements CastsAttributes
{
    /**
     * Cast the given value to a ProgressStatus object.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return ProgressStatus|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?ProgressStatus
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ProgressStatus::fromString($value);
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

        if (!$value instanceof ProgressStatus) {
            throw new InvalidArgumentException('Value must be a ProgressStatus instance');
        }

        return $value->getValue();
    }
} 