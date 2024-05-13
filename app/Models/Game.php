<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'initially_published_at',
        'version_published_at',
        'game_id',
        'name',
        'status',
        'visible',
        'nsfw',
        'description',
        'url',
        'thumb_url',
        'version',
        'tags',
        'rating',
        'rating_count',
        'devlog',
        'languages',
        'platform_windows',
        'platform_linux',
        'platform_mac',
        'platform_android',
        'platform_web',
        'stats_blocks',
        'stats_menus',
        'stats_options',
        'stats_words',
        'game_engine',
    ];

    protected $hidden = [
        'error',
    ];

    public function gameVersions(): HasMany
    {
        return $this->hasMany(GameVersion::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    protected function platforms(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => [
                'windows' => (bool) $attributes['platform_windows'],
                'linux' => (bool) $attributes['platform_linux'],
                'mac' => (bool) $attributes['platform_mac'],
                'android' => (bool) $attributes['platform_android'],
                'web' => (bool) $attributes['platform_web'],
            ],
        );
    }
}
