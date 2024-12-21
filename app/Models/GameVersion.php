<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'published_at',
        'game_id',
        'version',
        'devlog',
        'platform_windows',
        'platform_linux',
        'platform_mac',
        'platform_android',
        'platform_web',
        'stats_blocks',
        'stats_menus',
        'stats_options',
        'stats_words',
        'rating',
        'rating_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'rating' => 'float',
        'rating_count' => 'integer',
        'stats_blocks' => 'integer',
        'stats_menus' => 'integer',
        'stats_options' => 'integer',
        'stats_words' => 'integer',
        'platform_windows' => 'boolean',
        'platform_linux' => 'boolean',
        'platform_mac' => 'boolean',
        'platform_android' => 'boolean',
        'platform_web' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
