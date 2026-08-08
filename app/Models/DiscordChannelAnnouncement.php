<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordChannelAnnouncement extends Model
{
    use MassPrunable;

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

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    public function prunable(): Builder
    {
        return static::query()->whereIn('status', ['sent', 'failed'])->where('updated_at', '<=', now()->subDays(90));
    }
}
