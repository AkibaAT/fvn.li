<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordChannelAnnouncement extends Model
{
    public const MAX_ATTEMPTS = 3;

    protected $fillable = [
        'game_id',
        'game_version_id',
        'status',
        'batch_key',
        'attempts',
        'error',
        'processed_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the game this announcement is for.
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Get the game version this announcement is for.
     */
    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }
}
