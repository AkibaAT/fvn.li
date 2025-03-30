<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\SharedKernel\ValueObjects\PlatformSupport;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class PlatformSupportCast implements CastsAttributes
{
    /**
     * Cast the given value to a PlatformSupport object.
     * This assumes the model has separate boolean columns for each platform.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return PlatformSupport
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): PlatformSupport
    {
        return new PlatformSupport(
            windows: $attributes['is_windows'] ?? false,
            linux: $attributes['is_linux'] ?? false,
            mac: $attributes['is_mac'] ?? false,
            android: $attributes['is_android'] ?? false,
            web: $attributes['is_web'] ?? false
        );
    }

    /**
     * Prepare the given value for storage by setting each platform boolean column.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return array
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (!$value instanceof PlatformSupport) {
            throw new InvalidArgumentException('Value must be a PlatformSupport instance');
        }

        return [
            'is_windows' => $value->isWindows(),
            'is_linux' => $value->isLinux(),
            'is_mac' => $value->isMac(),
            'is_android' => $value->isAndroid(),
            'is_web' => $value->isWeb(),
        ];
    }
} 