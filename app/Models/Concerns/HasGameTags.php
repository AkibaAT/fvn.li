<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\GameJam;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Log;

trait HasGameTags
{
    /**
     * Temporary in-memory storage for pending associations (not persisted to database)
     */
    public array $pendingGameJamId = [];
    public array $pendingTagIds = [];

    /**
     * Get all tags associated with this game
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps()->orderBy('name');
    }

    /**
     * Get the game jams this game has participated in.
     * Default sorting is alphabetical by name.
     */
    public function gameJams(): BelongsToMany
    {
        return $this->belongsToMany(GameJam::class, 'game_game_jam')
            ->withPivot('ranking', 'criteria_rankings')
            ->withTimestamps()
            ->orderBy('name');
    }

    /**
     * Get tags as a simple array
     */
    public function getTagsListAttribute(): array
    {
        // Only load tags if they're already loaded to prevent N+1 queries
        if ($this->relationLoaded('tags')) {
            return $this->tags->pluck('name')->toArray();
        }

        return [];
    }

    /**
     * Get tags as a comma-separated string
     */
    public function getTagsStringAttribute(): string
    {
        // Only load tags if they're already loaded to prevent N+1 queries
        if ($this->relationLoaded('tags')) {
            return $this->tags->pluck('name')->implode(',');
        }

        return '';
    }

    /**
     * Set custom tags attribute, ensuring it's never null
     */
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
            $tag = Tag::firstOrCreate(['name' => $tagName]);
            $tagIds[] = $tag->id;
        }

        // If the game is already saved, sync tags immediately
        if ($this->exists && $this->id) {
            $this->tags()->sync($tagIds);
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

    /**
     * Process any pending game jam associations
     * This should be called after the game is saved
     */
    public function processPendingGameJams(): void
    {
        // Check if we have any pending game jam associations
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

        // Process each pending game jam
        foreach ($this->pendingGameJamId as $jamId) {
            // Check if the association already exists
            if (! $this->gameJams()->where('game_jam_id', $jamId)->exists()) {
                // Create the association
                $this->gameJams()->attach($jamId);

                Log::info('Associated game with game jam', [
                    'game_id' => $this->id,
                    'game_name' => $this->name,
                    'jam_id' => $jamId,
                ]);
            }
        }

        // Clear the pending list
        $this->pendingGameJamId = [];
    }

    /**
     * Process any pending tag associations
     * This should be called after the game is saved
     */
    public function processPendingTags(): void
    {
        // Check if we have any pending tag associations
        if (empty($this->pendingTagIds)) {
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

        // Sync the tags
        $this->tags()->sync($this->pendingTagIds);

        Log::info('Synced pending tags for game', [
            'game_id' => $this->id,
            'game_name' => $this->name,
            'tag_ids' => $this->pendingTagIds,
        ]);

        // Clear the pending list
        $this->pendingTagIds = [];
    }
}
