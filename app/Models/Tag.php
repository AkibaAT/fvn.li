<?php

declare(strict_types=1);

namespace App\Models;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Tag extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Minimum games a tag must appear on before it is shown in public UI.
     */
    public static function minPublicGameCount(): int
    {
        return max(1, (int) config('fvn.min_tag_game_count', 5));
    }

    /**
     * Cached map of popular tag id => game count.
     *
     * Built with a single GROUP BY over game_tag instead of per-tag correlated counts.
     *
     * @return array<int, int>
     */
    public static function popularGameCounts(): array
    {
        return Cache::remember(self::popularCacheKey(), 3600, function () {
            return DB::table('game_tag')
                ->select('tag_id', DB::raw('COUNT(*) as games_count'))
                ->groupBy('tag_id')
                ->havingRaw('COUNT(*) >= ?', [self::minPublicGameCount()])
                ->pluck('games_count', 'tag_id')
                ->map(fn ($count) => (int) $count)
                ->all();
        });
    }

    /**
     * @return list<int>
     */
    public static function popularIds(): array
    {
        return array_map('intval', array_keys(self::popularGameCounts()));
    }

    public static function clearPopularCache(): void
    {
        Cache::forget(self::popularCacheKey());
    }

    /**
     * Eager-load constraint that keeps only tags used by enough games.
     */
    public static function constrainToPopular(): Closure
    {
        return static function ($query): void {
            $ids = self::popularIds();

            if ($ids === []) {
                $query->whereRaw('0 = 1');

                return;
            }

            $query->whereIn($query->getModel()->getQualifiedKeyName(), $ids);
        };
    }

    protected static function booted(): void
    {
        static::creating(function ($tag) {
            $tag->slug = Str::slug($tag->name);
        });

        static::updating(function ($tag) {
            if ($tag->isDirty('name')) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    private static function popularCacheKey(): string
    {
        return 'popular-tag-game-counts:min-' . self::minPublicGameCount();
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class);
    }

    /**
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
     */
    public function scopeUsedAtLeast(Builder $query, ?int $min = null): Builder
    {
        $min ??= self::minPublicGameCount();

        // Custom thresholds skip the shared popularity cache.
        if ($min !== self::minPublicGameCount()) {
            return $query->has('games', '>=', $min);
        }

        $ids = self::popularIds();

        if ($ids === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn($query->getModel()->getQualifiedKeyName(), $ids);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'game_count' => $this->games()->count(),
            'created_at' => $this->created_at?->timestamp,
            'updated_at' => $this->updated_at?->timestamp,
        ];
    }

    public function searchableAs(): string
    {
        return 'tags';
    }

    public function shouldBeSearchable(): bool
    {
        // Only index tags that have a name
        return ! empty(trim($this->name));
    }
}
