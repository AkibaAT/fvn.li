<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    protected $fillable = [
        'game_id',
        'character_id',
        'display_names',
        'first_seen_in_version_id',
        'last_seen_in_version_id',
    ];

    protected $casts = [
        'display_names' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function firstSeenVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class, 'first_seen_in_version_id');
    }

    public function lastSeenVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class, 'last_seen_in_version_id');
    }

    public function versionStats(): HasMany
    {
        return $this->hasMany(VersionCharacterStats::class);
    }

    public function getDisplayName(string $isoCode): ?string
    {
        return $this->display_names[$isoCode] ?? null;
    }
}
