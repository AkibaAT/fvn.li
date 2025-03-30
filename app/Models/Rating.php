<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Casts\RatingScoreCast;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'published_at',
        'event_id',
        'game_id',
        'rater_id',
        'rating',
        'review',
        'is_visible',
        'is_reviewed',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'rating' => RatingScoreCast::class,
        'is_visible' => 'boolean',
        'is_reviewed' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(Rater::class);
    }
}
