<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'initially_published_at',
        'game_id',
        'name',
        'status',
        'is_visible',
        'is_nsfw',
        'description',
        'url',
        'thumb_url',
        'tags',
        'rating',
        'rating_count',
        'devlog',
        'is_windows',
        'is_linux',
        'is_mac',
        'is_android',
        'is_web',
        'game_engine',
        'authors',
        'custom_tags',
    ];

    protected $hidden = [
        'error',
    ];

    protected $casts = [
        'initially_published_at' => 'datetime',
        'rating' => 'float',
        'rating_count' => 'integer',
        'is_windows' => 'boolean',
        'is_linux' => 'boolean',
        'is_mac' => 'boolean',
        'is_android' => 'boolean',
        'is_web' => 'boolean',
        'is_nsfw' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function gameVersions(): HasMany
    {
        return $this->hasMany(GameVersion::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function latestVersion()
    {
        return $this->gameVersions()
            ->orderByDesc('published_at')
            ->first();
    }

    public function getLatestSupportedLanguages(): Collection
    {
        $latestVersion = $this->latestVersion();
        if (! $latestVersion) {
            return collect();
        }

        return $latestVersion->languageStats()
            ->with('language')
            ->get()
            ->map(fn ($stat) => $stat->language);
    }

    public function getEnglishWordCount(): ?int
    {
        $latestVersion = $this->latestVersion();
        if (! $latestVersion) {
            return null;
        }

        $englishStats = $latestVersion->getStatsForLanguage('eng');

        return $englishStats?->words;
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
