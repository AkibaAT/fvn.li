<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameVersion extends Model
{
    use HasFactory;

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
        'stats_blocks',
        'stats_menus',
        'stats_options',
        'stats_words',
        'rating',
        'rating_count',
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
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function languageStats(): HasMany
    {
        return $this->hasMany(VersionLanguageStats::class);
    }

    public function characterStats(): HasMany
    {
        return $this->hasMany(VersionCharacterStats::class);
    }

    public function getStatsForLanguage(string $isoCode)
    {
        return $this->languageStats()
            ->where('iso_code', $isoCode)
            ->first();
    }
}
