<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'preferred_languages',
        'excluded_tags',
    ];

    protected $attributes = [
        'preferred_languages' => '[]',
        'excluded_tags' => '[]',
    ];

    protected $casts = [
        'preferred_languages' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function excludedTags(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): array => json_decode($value, true),
            set: fn (array $value): string => json_encode(array_values(array_map('intval', $value))),
        );
    }
}
