<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\SharedKernel\ValueObjects\Version;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class VersionCast implements CastsAttributes
{
    /**
     * Cast the given value to a Version object.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return Version|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Version
    {
        if ($value === null) {
            return null;
        }

        // Try to parse the string as a version
        $version = Version::tryFromString($value);
        
        if ($version === null) {
            throw new InvalidArgumentException("Cannot cast value '{$value}' to Version");
        }
        
        return $version;
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

        if (!$value instanceof Version) {
            throw new InvalidArgumentException('Value must be a Version instance');
        }

        return $value->getValue();
    }
} 