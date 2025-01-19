<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    public $incrementing = false;

    protected $table = 'iso_639_3_languages';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'part2b',
        'part2t',
        'part1',
        'scope',
        'type',
        'ref_name',
        'comment',
        'flag_code',
    ];

    public function getFlagCodeAttribute(?string $value): string
    {
        if ($value) {
            return $value;
        }

        // Fallback to part1 code if available and is 2 characters
        if ($this->part1 && strlen($this->part1) === 2) {
            return strtolower($this->part1);
        }

        // Final fallback - use first two characters of ISO 639-3 code
        return strtolower(substr($this->id, 0, 2));
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class, 'default_language_code', 'id');
    }

    public function languageMappings(): HasMany
    {
        return $this->hasMany(LanguageMapping::class, 'iso_code', 'id');
    }

    public function versionLanguageStats(): HasMany
    {
        return $this->hasMany(VersionLanguageStats::class, 'iso_code', 'id');
    }

    public function versionCharacterStats(): HasMany
    {
        return $this->hasMany(VersionCharacterStats::class, 'iso_code', 'id');
    }
}
