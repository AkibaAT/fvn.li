<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\SharedKernel\ValueObjects\LanguageCode;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class LanguageCodeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?LanguageCode
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException("Language code must be a string");
        }

        return LanguageCode::fromString($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof LanguageCode) {
            throw new InvalidArgumentException("Value must be a LanguageCode instance");
        }

        return $value->getValue();
    }
}