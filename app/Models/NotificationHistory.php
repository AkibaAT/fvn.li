<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationHistory extends Model
{
    use MassPrunable;

    protected $table = 'notification_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'game_id',
        'game_version_id',
        'type',
        'success',
        'meta_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'success' => 'boolean',
        'meta_data' => 'array',
    ];

    public static function record(array $attributes): self
    {
        if (isset($attributes['meta_data']) && is_array($attributes['meta_data'])) {
            $attributes['meta_data'] = json_encode($attributes['meta_data'], JSON_THROW_ON_ERROR);
        }
        $attributes['created_at'] ??= now();
        $attributes['updated_at'] ??= now();
        static::query()->insertOrIgnore($attributes);

        return static::query()->where([
            'user_id' => $attributes['user_id'],
            'game_id' => $attributes['game_id'],
            'game_version_id' => $attributes['game_version_id'],
            'type' => $attributes['type'],
        ])->firstOrFail();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
        return static::query()->where('created_at', '<=', now()->subDays(90));
    }
}
