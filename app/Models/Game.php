<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Game extends Model
{
    protected $casts = [
        'initially_published_at' => 'datetime',
        'latest_version_published_at' => 'datetime',
        'rating' => 'float',
        'rating_count' => 'integer',
        'is_windows' => 'boolean',
        'is_linux' => 'boolean',
        'is_mac' => 'boolean',
        'is_android' => 'boolean',
        'is_web' => 'boolean',
        'is_nsfw' => 'boolean',
        'is_visible' => 'boolean',
        'supported_languages' => 'collection',
    ];

    public function latestVersion(): HasOne
    {
        return $this->hasOne(GameVersion::class)->where('is_latest', true);
    }

    public function getLatestSupportedLanguages(): Collection
    {
        return $this->supported_languages ?? collect();
    }

    public function getEnglishWordCount(): ?int
    {
        return $this->english_word_count;
    }

    protected function platforms(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => [
                'windows' => (bool) $attributes['is_windows'],
                'linux' => (bool) $attributes['is_linux'],
                'mac' => (bool) $attributes['is_mac'],
                'android' => (bool) $attributes['is_android'],
                'web' => (bool) $attributes['is_web'],
            ],
        );
    }
}
