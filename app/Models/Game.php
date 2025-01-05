<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'uploads' => 'array',
    ];

    /**
     * Get all game versions for this game.
     */
    public function gameVersions(): HasMany
    {
        return $this->hasMany(GameVersion::class)->orderByDesc('published_at');
    }

    /**
     * Get the latest version of the game.
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(GameVersion::class)->where('is_latest', true);
    }

    /**
     * Get all ratings for this game.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Get the supported languages collection for the latest version.
     */
    public function getSupportedLanguages(): Collection
    {
        if ($this->relationLoaded('latestVersion') &&
            $this->latestVersion?->relationLoaded('languageStats')) {
            return $this->latestVersion->languageStats->map(fn($stat) => [
                'iso_code' => $stat->iso_code,
                'ref_name' => $stat->language->ref_name,
                'flag_code' => $stat->language->flag_code
            ])->collect();
        }

        return collect();
    }

    /**
     * Get the English word count from the latest version.
     */
    public function getEnglishWordCount(): ?int
    {
        // First try to get from the english_word_count attribute which is pre-loaded in list views
        if (isset($this->attributes['english_word_count'])) {
            return $this->english_word_count;
        }

        // Otherwise load from the latest version
        if ($this->relationLoaded('latestVersion')) {
            $englishStats = $this->latestVersion?->getStatsForLanguage('eng');
            return $englishStats?->words;
        }

        return null;
    }

    /**
     * Get the platforms attribute.
     */
    protected function platforms(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => [
                'windows' => (bool) ($attributes['is_windows'] ?? false),
                'linux' => (bool) ($attributes['is_linux'] ?? false),
                'mac' => (bool) ($attributes['is_mac'] ?? false),
                'android' => (bool) ($attributes['is_android'] ?? false),
                'web' => (bool) ($attributes['is_web'] ?? false),
            ],
        );
    }
}
