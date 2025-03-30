<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\SharedKernel\ValueObjects\PersonalNotes;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class PersonalNotesCast implements CastsAttributes
{
    /**
     * The maximum length allowed for notes.
     *
     * @var int
     */
    private int $maxLength;

    /**
     * Create a new cast instance.
     *
     * @param int $maxLength Maximum allowed length for notes
     */
    public function __construct(int $maxLength = 10000)
    {
        $this->maxLength = $maxLength;
    }

    /**
     * Cast the given value to a PersonalNotes object.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return PersonalNotes|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?PersonalNotes
    {
        if ($value === null || $value === '') {
            return PersonalNotes::empty();
        }

        return PersonalNotes::fromString($value, $this->maxLength);
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

        if (!$value instanceof PersonalNotes) {
            throw new InvalidArgumentException('Value must be a PersonalNotes instance');
        }

        return $value->getValue();
    }
} 