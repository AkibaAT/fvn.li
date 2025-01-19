<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class GameVersion extends Model
{
    protected $fillable = [
        'published_at',
        'game_id',
        'version',
        'devlog',
        'is_windows',
        'is_linux',
        'is_mac',
        'is_android',
        'is_web',
        'rating',
        'rating_count',
        'is_latest',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_windows' => 'boolean',
        'is_linux' => 'boolean',
        'is_mac' => 'boolean',
        'is_android' => 'boolean',
        'is_web' => 'boolean',
        'rating' => 'float',
        'rating_count' => 'integer',
        'is_latest' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function characterStats(): HasMany
    {
        return $this->hasMany(VersionCharacterStats::class);
    }

    public function characterStatsWithoutPlaceholders(): HasMany
    {
        return $this->hasMany(VersionCharacterStats::class, 'game_version_id', 'id')
            ->where('iso_code', 'not like', 'q%')
            ->orderBy('character_id')
            ->orderBy('iso_code');
    }

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'version_character_stats')
            ->withPivot(['iso_code', 'blocks', 'words']);
    }

    public function getStatsForLanguage(string $isoCode)
    {
        return $this->languageStats()
            ->where('iso_code', $isoCode)
            ->first();
    }

    public function languageStats(): HasMany
    {
        return $this->hasMany(VersionLanguageStats::class);
    }

    public function getCharacterStatsForLanguage(string $isoCode): Collection
    {
        return $this->characterStats()
            ->where('iso_code', $isoCode)
            ->orderBy('character_id')
            ->get();
    }
}
