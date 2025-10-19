<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class Rating extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'published_at',
        'event_id',
        'game_id',
        'rater_id',
        'rating',
        'review',
        'is_visible',
        'is_reviewed',
        'external_id',
        'source_platform',
        'external_metadata',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'rating' => 'float',
        'is_visible' => 'boolean',
        'is_reviewed' => 'boolean',
        'external_metadata' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(Rater::class);
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        // Load relationships if not already loaded
        if (! $this->relationLoaded('game') || ! $this->relationLoaded('rater')) {
            $this->load(['game', 'rater']);
        }

        return [
            'id' => $this->id,
            'review' => $this->review,
            'rating' => $this->rating,

            // Game data
            'game_id' => $this->game_id,
            'game_name' => $this->game?->name,
            'game_slug' => $this->game?->slug,
            'game_is_visible' => $this->game?->is_visible,

            // Rater data
            'rater_id' => $this->rater_id,
            'rater_name' => $this->rater?->name,

            // Event and timing
            'event_id' => $this->event_id,
            'published_at' => $this->published_at?->timestamp,
            'created_at' => $this->created_at?->timestamp,
            'updated_at' => $this->updated_at?->timestamp,

            // Status flags
            'is_visible' => $this->is_visible,
            'is_reviewed' => $this->is_reviewed,
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'reviews';
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        // Only index visible reviews with actual review text
        return $this->is_visible && $this->is_reviewed && ! empty(trim($this->review));
    }
}
