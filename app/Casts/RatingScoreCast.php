<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\SharedKernel\ValueObjects\RatingScore;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class RatingScoreCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): RatingScore
    {
        if ($value === null) {
            return new RatingScore(0.0);
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Rating value must be a number");
        }

        return new RatingScore((float) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): float
    {
        if (!$value instanceof RatingScore) {
            throw new InvalidArgumentException("Value must be a RatingScore instance");
        }

        return $value->getValue();
    }
}