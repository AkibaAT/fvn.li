<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewReport extends Model
{
    public const REASONS = [
        'hate_speech' => 'Hate speech or discrimination',
        'spam' => 'Spam or advertising',
        'harassment' => 'Harassment or personal attacks',
        'spoilers' => 'Unmarked spoilers',
        'off_topic' => 'Off-topic or irrelevant',
        'other' => 'Other',
    ];

    protected $fillable = [
        'rating_id',
        'reporter_id',
        'reason',
        'details',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'discord_notified_at' => 'datetime',
    ];

    public function rating(): BelongsTo
    {
        return $this->belongsTo(Rating::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
