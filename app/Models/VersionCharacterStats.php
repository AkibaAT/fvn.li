<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Casts\LanguageCodeCast;

class VersionCharacterStats extends Model
{
    protected $fillable = [
        'game_version_id',
        'character_id',
        'iso_code',
        'blocks',
        'words',
    ];

    protected $casts = [
        'blocks' => 'integer',
        'words' => 'integer',
        'iso_code' => LanguageCodeCast::class,
    ];

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'iso_code', 'id')
            ->withoutGlobalScopes(); // Ensure we bypass any global scopes that might interfere
    }

    /**
     * Get the raw iso_code value (before casting)
     */
    public function getRawIsoCodeAttribute(): string
    {
        return $this->attributes['iso_code'];
    }
}
