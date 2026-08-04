<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\GameJam;
use App\Models\Tag;
use App\Services\GameFilterService;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait HasGameTags
{
    /**
     * Temporary in-memory storage for pending associations (not persisted to database)
     */
    public array $pendingGameJamId = [];

    public array $pendingTagIds = [];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps()->orderBy('name');
    }

    public function gameJams(): BelongsToMany
    {
        return $this->belongsToMany(GameJam::class, 'game_game_jam')
            ->withPivot('ranking', 'criteria_rankings')
            ->withTimestamps()
            ->orderBy('name');
    }

    public function getTagsListAttribute(): array
    {
        // Only load tags if they're already loaded to prevent N+1 queries
        if ($this->relationLoaded('tags')) {
            return $this->tags->pluck('name')->toArray();
        }

        return [];
    }

    public function getTagsStringAttribute(): string
    {
        // Only load tags if they're already loaded to prevent N+1 queries
        if ($this->relationLoaded('tags')) {
            return $this->tags->pluck('name')->implode(',');
        }

        return '';
    }

    public function setCustomTagsAttribute($value): void
    {
        $this->attributes['custom_tags'] = $value ?? '';
    }

    /**
     * Sync tags from a comma-separated string
     */
    public function syncTagsFromString(string $tags): void
    {
        $tagNames = array_filter(array_map('trim', explode(',', $tags)));
        $tagIds = [];

        foreach ($tagNames as $tagName) {
            $slug = Str::slug($tagName);
            $tag = Tag::firstOrCreate(['slug' => $slug], ['name' => $tagName]);
            $tagIds[] = $tag->id;
        }

        // Merge custom_tags into the tag list
        $tagIds = array_values(array_unique(array_merge($tagIds, $this->getCustomTagIds())));

        // If the game is already saved, sync tags immediately
        if ($this->exists && $this->id) {
            $this->tags()->sync($tagIds);
            $this->bumpRecommendationCacheVersion();
            Log::info('Synced tags for existing game', [
                'game_id' => $this->id,
                'game_name' => $this->name,
                'tag_ids' => $tagIds,
            ]);
        } else {
            // Otherwise, store them for later processing
            $this->pendingTagIds = $tagIds;
            Log::info('Stored pending tags for new game', [
                'game_name' => $this->name,
                'tag_ids' => $tagIds,
            ]);
        }
    }

    public function processPendingGameJams(): void
    {
        if (empty($this->pendingGameJamId)) {
            return;
        }

        // Make sure the game has been saved and has an ID
        if (! $this->exists || ! $this->id) {
            Log::warning('Cannot process pending game jams - game not saved', [
                'game_name' => $this->name,
                'game_id' => $this->id,
                'exists' => $this->exists,
            ]);

            return;
        }

        foreach ($this->pendingGameJamId as $jamId) {
            if (! $this->gameJams()->where('game_jam_id', $jamId)->exists()) {
                $this->gameJams()->attach($jamId);

                Log::info('Associated game with game jam', [
                    'game_id' => $this->id,
                    'game_name' => $this->name,
                    'jam_id' => $jamId,
                ]);

                GameFilterService::clearCache();

                if ($this->is_visible) {
                    $this->queueSearchIndexRefreshAfterCommit();
                }
            }
        }

        $this->pendingGameJamId = [];
    }

    public function processPendingTags(): void
    {
        if (empty($this->pendingTagIds)) {
            // No page tags were parsed; only attach custom tags without
            // removing existing ones
            $customTagIds = $this->getCustomTagIds();
            if (! empty($customTagIds) && $this->exists && $this->id) {
                $this->tags()->syncWithoutDetaching($customTagIds);
                $this->bumpRecommendationCacheVersion();
            }

            return;
        }

        // Make sure the game has been saved and has an ID
        if (! $this->exists || ! $this->id) {
            Log::warning('Cannot process pending tags - game not saved', [
                'game_name' => $this->name,
                'game_id' => $this->id,
                'exists' => $this->exists,
            ]);

            return;
        }

        // Merge custom_tags into pending tags before syncing
        $customTagIds = $this->getCustomTagIds();
        if (! empty($customTagIds)) {
            $this->pendingTagIds = array_values(array_unique(array_merge($this->pendingTagIds, $customTagIds)));
        }

        // Sync the tags
        $this->tags()->sync($this->pendingTagIds);
        $this->bumpRecommendationCacheVersion();

        Log::info('Synced pending tags for game', [
            'game_id' => $this->id,
            'game_name' => $this->name,
            'tag_ids' => $this->pendingTagIds,
        ]);

        $this->pendingTagIds = [];
    }

    private function getCustomTagIds(): array
    {
        $customTags = $this->custom_tags ?? '';
        if (empty(trim($customTags))) {
            return [];
        }

        $tagIds = [];
        $tagNames = array_filter(array_map('trim', explode(',', $customTags)));

        foreach ($tagNames as $tagName) {
            $slug = Str::slug($tagName);
            if (empty($slug)) {
                continue;
            }
            $tag = Tag::firstOrCreate(['slug' => $slug], ['name' => $tagName]);
            $tagIds[] = $tag->id;
        }

        return $tagIds;
    }

    private function bumpRecommendationCacheVersion(): void
    {
        Cache::add('games.recommendations.version', 1);
        Cache::increment('games.recommendations.version');
    }

    private function queueSearchIndexRefreshAfterCommit(): void
    {
        $gameId = $this->id;
        $gameClass = static::class;

        DB::afterCommit(static function () use ($gameClass, $gameId): void {
            $game = $gameClass::with(['tags', 'gameJams', 'gameVersions'])->find($gameId);
            if ($game?->is_visible) {
                $game->searchable();
            }
        });
    }
}
