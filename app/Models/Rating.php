<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'published_at',
        'event_id',
        'game_id',
        'rater_id',
        'user_id',
        'rating',
        'review',
        'is_visible',
        'is_moderation_hidden',
        'is_reviewed',
        'has_spoilers',
        'external_id',
        'source_platform',
        'external_metadata',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'rating' => 'float',
        'is_visible' => 'boolean',
        'is_moderation_hidden' => 'boolean',
        'is_reviewed' => 'boolean',
        'has_spoilers' => 'boolean',
        'external_metadata' => 'array',
    ];

    /**
     * Superseded rating counts for a game keyed by rater id, excluding
     * moderation-hidden rows. A non-zero count means the rater's current
     * rating has viewable history.
     */
    public static function previousRatingCountsForGame(int $gameId, iterable $raterIds): Collection
    {
        $raterIds = collect($raterIds)->filter()->unique()->values();

        if ($raterIds->isEmpty()) {
            return collect();
        }

        return static::query()
            ->where('game_id', $gameId)
            ->whereIn('rater_id', $raterIds)
            ->where('is_visible', false)
            ->where('is_moderation_hidden', false)
            ->groupBy('rater_id')
            ->selectRaw('rater_id, count(*) as aggregate')
            ->pluck('aggregate', 'rater_id');
    }

    /**
     * Native FVN.li reviews and imported ratings whose rater is linked to the user.
     */
    public function scopeAuthoredBy(Builder $query, int $userId): Builder
    {
        $raterIds = Rater::query()->where('user_id', $userId)->pluck('id');

        return $query->where(function ($ratings) use ($userId, $raterIds) {
            $ratings->where('user_id', $userId);
            if ($raterIds->isNotEmpty()) {
                $ratings->orWhereIn('rater_id', $raterIds);
            }
        });
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(Rater::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUserReview(): bool
    {
        return $this->user_id !== null;
    }

    public function authorUser(): ?User
    {
        return $this->user ?? $this->rater?->user;
    }

    public function assignAuthorUser(): void
    {
        if ($this->user !== null) {
            return;
        }

        $linked = $this->rater?->user;
        if ($linked) {
            $this->setRelation('user', $linked);
        }
    }
}
