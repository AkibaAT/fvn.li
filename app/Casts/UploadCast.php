<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\SharedKernel\ValueObjects\Filename;
use App\Domain\SharedKernel\ValueObjects\MD5Hash;
use App\Domain\SharedKernel\ValueObjects\Upload;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class UploadCast implements CastsAttributes
{
    /**
     * Cast the given value to an Upload object.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return Upload|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Upload
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $data = json_decode($value, true);
            if (!is_array($data)) {
                throw new InvalidArgumentException("Cannot cast value to Upload: invalid JSON format");
            }
        } elseif (is_array($value)) {
            $data = $value;
        } else {
            throw new InvalidArgumentException("Cannot cast value to Upload: expected string or array");
        }

        // Ensure required fields exist
        $requiredFields = ['filename', 'updated_at'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field '{$field}' in upload data");
            }
        }

        $id = $data['id'] ?? 0; // Default to 0 if no ID is provided
        return Upload::fromArray($data, $id);
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

        if (!$value instanceof Upload) {
            throw new InvalidArgumentException('Value must be an Upload instance');
        }

        return json_encode($value->toArray());
    }
} 